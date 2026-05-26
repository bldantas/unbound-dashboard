"""
backup_offsite_service — upload de backups pra S3-compatible (C.7).

Engloba AWS S3 / MinIO / Wasabi / Cloudflare R2 / Backblaze B2 — todos
falam mesma API. Settings vivem em `settings` (chaves `backup_s3_*`) pra
não criar nova migration.

Estratégia:
- Backup = tar.gz com `unbound_dash.duckdb` + `/etc/unbound/unbound.conf` +
  `/etc/unbound/includes/*.conf` + `src/data/settings.json`. Mesmo conjunto
  que o `restore-backup.sh` espera.
- Retenção remota: lista objects com prefixo, ordena por LastModified, deleta
  todos exceto os N mais recentes.
- Credenciais armazenadas plaintext em `settings` (mesmo padrão de SMTP).
  DuckDB com permissão `www-data:www-data 660`.
"""

from __future__ import annotations

import io
import shutil
import subprocess
import tarfile
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import boto3
import structlog
from botocore.client import Config as BotoConfig
from botocore.exceptions import BotoCoreError, ClientError

from app.core.config import settings as app_settings
from app.repositories.duckdb import settings_repo

log = structlog.get_logger(__name__)

SETTINGS_KEYS = [
    "backup_s3_enabled",
    "backup_s3_endpoint",
    "backup_s3_bucket",
    "backup_s3_region",
    "backup_s3_prefix",
    "backup_s3_access_key",
    "backup_s3_secret_key",
    "backup_s3_retention_count",
    "backup_s3_schedule_hours",
    # Auto restore-test (RestoreTestRunner v2.73)
    "backup_s3_restore_test_enabled",
    "backup_s3_restore_test_interval_hours",
]

DEFAULTS = {
    "backup_s3_enabled": "0",
    "backup_s3_endpoint": "",
    "backup_s3_bucket": "",
    "backup_s3_region": "us-east-1",
    "backup_s3_prefix": "unbound-dashboard/",
    "backup_s3_access_key": "",
    "backup_s3_secret_key": "",
    "backup_s3_retention_count": "10",
    "backup_s3_schedule_hours": "24",
}

STATUS_KEYS = [
    "backup_s3_last_upload_at",
    "backup_s3_last_status",
    "backup_s3_last_error",
    "backup_s3_last_size_bytes",
    "backup_s3_last_key",
    # Auto restore-test (RestoreTestRunner v2.73)
    "backup_s3_restore_test_enabled",
    "backup_s3_restore_test_interval_hours",
    "backup_s3_last_restore_test_at",
    "backup_s3_last_restore_test_ok",
    "backup_s3_last_restore_test_error",
    "backup_s3_last_restore_test_key",
]

# Paths incluídos no tarball
INCLUDED_PATHS = [
    Path("/etc/unbound/unbound.conf"),
    Path("/etc/unbound/includes"),
    Path("/var/www/html/unbound-dashboard/src/data/settings.json"),
    Path("/var/www/html/unbound-dashboard/src/data/blocklist.json"),
    Path("/var/www/html/unbound-dashboard/src/data/local_records.json"),
]


# ============================================================
# Config
# ============================================================


async def load_config() -> dict[str, str]:
    """Lê todos os settings backup_s3_*. Falta = default."""
    cfg: dict[str, str] = {}
    for k in SETTINGS_KEYS:
        cfg[k] = await settings_repo.get(k, DEFAULTS.get(k, "")) or ""
    return cfg


async def save_config(values: dict[str, str]) -> int:
    """Persiste somente chaves conhecidas."""
    entries = []
    for k, v in values.items():
        if k in SETTINGS_KEYS:
            entries.append({"setting_key": k, "setting_value": str(v)})
    if not entries:
        return 0
    return await settings_repo.bulk_upsert(entries)


async def save_status(*, status: str, error: str | None = None, size: int | None = None, key: str | None = None) -> None:
    entries: list[dict[str, str]] = [
        {"setting_key": "backup_s3_last_status", "setting_value": status},
        {"setting_key": "backup_s3_last_upload_at", "setting_value": datetime.now(timezone.utc).isoformat()},
    ]
    if error is not None:
        entries.append({"setting_key": "backup_s3_last_error", "setting_value": error})
    if size is not None:
        entries.append({"setting_key": "backup_s3_last_size_bytes", "setting_value": str(size)})
    if key is not None:
        entries.append({"setting_key": "backup_s3_last_key", "setting_value": key})
    await settings_repo.bulk_upsert(entries)


# ============================================================
# Boto3
# ============================================================


def _client(cfg: dict[str, str]):
    """Cria boto3 client S3 c/ endpoint custom (compat MinIO/R2/etc)."""
    kwargs: dict[str, Any] = {
        "aws_access_key_id": cfg.get("backup_s3_access_key", "").strip(),
        "aws_secret_access_key": cfg.get("backup_s3_secret_key", "").strip(),
        "region_name": cfg.get("backup_s3_region", "us-east-1").strip() or "us-east-1",
        "config": BotoConfig(
            signature_version="s3v4",
            retries={"max_attempts": 3, "mode": "standard"},
            connect_timeout=10,
            read_timeout=120,
        ),
    }
    endpoint = cfg.get("backup_s3_endpoint", "").strip()
    if endpoint:
        kwargs["endpoint_url"] = endpoint
    return boto3.client("s3", **kwargs)


def _normalize_prefix(p: str) -> str:
    p = (p or "").strip().lstrip("/")
    if p and not p.endswith("/"):
        p += "/"
    return p


# ============================================================
# Operações
# ============================================================


def test_connection(cfg: dict[str, str]) -> dict:
    """Tenta head_bucket pra validar credenciais + bucket existe."""
    bucket = cfg.get("backup_s3_bucket", "").strip()
    if not bucket:
        return {"success": False, "error": "bucket vazio"}
    if not cfg.get("backup_s3_access_key") or not cfg.get("backup_s3_secret_key"):
        return {"success": False, "error": "access_key/secret_key vazios"}
    try:
        c = _client(cfg)
        c.head_bucket(Bucket=bucket)
        return {"success": True, "bucket": bucket, "endpoint": cfg.get("backup_s3_endpoint") or "aws"}
    except ClientError as e:
        code = e.response.get("Error", {}).get("Code", "Unknown")
        msg = e.response.get("Error", {}).get("Message", str(e))
        return {"success": False, "error": f"{code}: {msg}"}
    except BotoCoreError as e:
        return {"success": False, "error": f"BotoCoreError: {e}"}
    except Exception as e:  # noqa: BLE001
        return {"success": False, "error": f"{type(e).__name__}: {e}"}


def _create_archive() -> tuple[str, int]:
    """Gera tar.gz em /tmp e retorna (path, size_bytes)."""
    ts = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    archive_path = f"/tmp/unbound-dashboard-backup-{ts}.tar.gz"
    db_path = app_settings.db_path

    with tarfile.open(archive_path, "w:gz") as tar:
        # DuckDB (snapshot via cp pra evitar lock contention seria ideal,
        # mas DuckDB tolera leitura concorrente — adicionamos direto).
        if Path(db_path).exists():
            tar.add(db_path, arcname=f"duckdb/{Path(db_path).name}")
        for p in INCLUDED_PATHS:
            if p.exists():
                # arcname preserva estrutura relativa ao /
                tar.add(p, arcname=str(p).lstrip("/"))
    size = Path(archive_path).stat().st_size
    return archive_path, size


def _apply_retention(cfg: dict[str, str], keep: int) -> int:
    """Lista objects no prefix e deleta os mais antigos além do limite. Retorna count deletados."""
    bucket = cfg["backup_s3_bucket"]
    prefix = _normalize_prefix(cfg.get("backup_s3_prefix", ""))
    c = _client(cfg)

    objects: list[dict] = []
    continuation = None
    while True:
        kwargs = {"Bucket": bucket, "Prefix": prefix}
        if continuation:
            kwargs["ContinuationToken"] = continuation
        resp = c.list_objects_v2(**kwargs)
        for obj in resp.get("Contents", []):
            # Só considera nossos arquivos (tar.gz com prefixo conhecido)
            if obj["Key"].endswith(".tar.gz"):
                objects.append(obj)
        if not resp.get("IsTruncated"):
            break
        continuation = resp.get("NextContinuationToken")

    # Mais recentes primeiro
    objects.sort(key=lambda o: o["LastModified"], reverse=True)
    to_delete = objects[keep:]
    if not to_delete:
        return 0

    delete_resp = c.delete_objects(
        Bucket=bucket,
        Delete={"Objects": [{"Key": o["Key"]} for o in to_delete], "Quiet": True},
    )
    return len(delete_resp.get("Deleted", []) or to_delete)


def list_remote(cfg: dict[str, str], limit: int = 100) -> list[dict]:
    """Lista até N backups remotos ordenados por LastModified desc."""
    bucket = cfg["backup_s3_bucket"]
    prefix = _normalize_prefix(cfg.get("backup_s3_prefix", ""))
    c = _client(cfg)
    objs: list[dict] = []
    continuation = None
    while True:
        kwargs = {"Bucket": bucket, "Prefix": prefix}
        if continuation:
            kwargs["ContinuationToken"] = continuation
        resp = c.list_objects_v2(**kwargs)
        for obj in resp.get("Contents", []):
            if obj["Key"].endswith(".tar.gz"):
                objs.append(
                    {
                        "key": obj["Key"],
                        "size": int(obj.get("Size") or 0),
                        "last_modified": obj["LastModified"].isoformat() if obj.get("LastModified") else None,
                    }
                )
        if not resp.get("IsTruncated"):
            break
        continuation = resp.get("NextContinuationToken")

    objs.sort(key=lambda o: o["last_modified"] or "", reverse=True)
    return objs[:limit]


def restore_test(cfg: dict[str, str], key: str | None = None) -> dict:
    """Download de um backup S3 + verifica integridade do DuckDB extraído.

    Sem `key`: pega o mais recente do bucket/prefix.
    Não restaura no DB de produção — apenas valida em /tmp/restore_test_*.

    Validação:
    1. Archive baixa e extrai sem erro
    2. Contém `duckdb/unbound_dash.duckdb` (path do arcname em _create_archive)
    3. DuckDB abre read-only e `SELECT COUNT(*) FROM schema_migrations` retorna > 0
    4. Tabela `users` existe e tem >= 1 row

    Sync (chamado via run_in_executor).
    """
    import shutil
    import tempfile
    import duckdb

    bucket = cfg.get("backup_s3_bucket", "").strip()
    if not bucket:
        return {"success": False, "error": "bucket vazio"}

    try:
        c = _client(cfg)
        # Resolve a key mais recente se não passada
        if not key:
            prefix = _normalize_prefix(cfg.get("backup_s3_prefix", ""))
            resp = c.list_objects_v2(Bucket=bucket, Prefix=prefix)
            objs = [o for o in resp.get("Contents", []) if o["Key"].endswith(".tar.gz")]
            if not objs:
                return {"success": False, "error": "nenhum backup encontrado no bucket"}
            objs.sort(key=lambda o: o["LastModified"], reverse=True)
            key = objs[0]["Key"]
    except ClientError as e:
        return {"success": False, "error": f"S3 list: {e}"}

    tmpdir = tempfile.mkdtemp(prefix="restore_test_")
    archive_local = f"{tmpdir}/{Path(key).name}"

    try:
        c.download_file(bucket, key, archive_local)
        size = Path(archive_local).stat().st_size

        with tarfile.open(archive_local, "r:gz") as tar:
            tar.extractall(tmpdir, filter="data")

        # arcname em _create_archive é `duckdb/<nome>` (Path.name do db_path)
        db_files = list(Path(tmpdir).glob("duckdb/*.duckdb"))
        if not db_files:
            return {"success": False, "error": "archive não contém duckdb/*.duckdb"}
        db_extracted = db_files[0]

        # Valida DuckDB
        with duckdb.connect(str(db_extracted), read_only=True) as conn:
            n_mig = int(conn.execute("SELECT COUNT(*) FROM schema_migrations").fetchone()[0])
            if n_mig == 0:
                return {"success": False, "error": "schema_migrations vazio"}
            n_users = int(conn.execute("SELECT COUNT(*) FROM users").fetchone()[0])
            if n_users < 1:
                return {"success": False, "error": "tabela users vazia"}

            tables_row = conn.execute(
                "SELECT COUNT(DISTINCT table_name) FROM information_schema.tables WHERE table_schema='main'"
            ).fetchone()
            n_tables = int(tables_row[0]) if tables_row else 0

        return {
            "success": True,
            "key": key,
            "size_bytes": size,
            "migrations_applied": n_mig,
            "users_count": n_users,
            "tables_count": n_tables,
        }
    except Exception as e:  # noqa: BLE001
        return {"success": False, "error": f"{type(e).__name__}: {e}"}
    finally:
        # Limpa tmpdir
        try:
            shutil.rmtree(tmpdir, ignore_errors=True)
        except Exception:  # noqa: BLE001
            pass


def upload_backup(cfg: dict[str, str]) -> dict:
    """Cria archive + faz upload + aplica retenção. Sync (chamado via run_in_executor)."""
    bucket = cfg.get("backup_s3_bucket", "").strip()
    if not bucket:
        return {"success": False, "error": "bucket vazio"}

    try:
        archive_path, size = _create_archive()
    except Exception as e:  # noqa: BLE001
        return {"success": False, "error": f"falha ao gerar archive: {e}"}

    try:
        prefix = _normalize_prefix(cfg.get("backup_s3_prefix", ""))
        ts = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
        key = f"{prefix}unbound-dashboard-backup-{ts}.tar.gz"

        c = _client(cfg)
        c.upload_file(archive_path, bucket, key, ExtraArgs={"ContentType": "application/gzip"})

        # Retenção
        retention = int(cfg.get("backup_s3_retention_count") or "10")
        deleted = 0
        if retention > 0:
            try:
                deleted = _apply_retention(cfg, retention)
            except Exception as e:  # noqa: BLE001
                log.warning("backup_offsite.retention_failed", error=str(e))

        return {"success": True, "key": key, "size_bytes": size, "retention_deleted": deleted}
    except ClientError as e:
        code = e.response.get("Error", {}).get("Code", "Unknown")
        return {"success": False, "error": f"{code}: {e.response.get('Error', {}).get('Message', str(e))}"}
    except Exception as e:  # noqa: BLE001
        return {"success": False, "error": f"{type(e).__name__}: {e}"}
    finally:
        # Limpa archive local — fica no S3
        try:
            Path(archive_path).unlink(missing_ok=True)
        except Exception:  # noqa: BLE001
            pass
