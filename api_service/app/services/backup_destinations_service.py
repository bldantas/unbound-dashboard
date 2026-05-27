"""
backup_destinations_service — CRUD + upload pra múltiplos destinos S3 (v2.74).

Reusa o helper síncrono `backup_offsite_service.upload_backup(cfg)` por
cada destination enabled, gerando o archive **1x** e fazendo N uploads
em paralelo (via thread executor — boto3 é sync).

secret_key fica cifrado via cipher_service (v2.71). Endpoint /list
nunca retorna o secret_key bruto.
"""

from __future__ import annotations

import asyncio
import functools
import json
from datetime import datetime
from pathlib import Path
from typing import Any

import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone
from app.services import cipher_service

log = structlog.get_logger(__name__)


def _to_iso(v: Any) -> str | None:
    if isinstance(v, datetime):
        return v.isoformat()
    return str(v) if v else None


def _mask(s: str) -> str:
    if not s or len(s) < 8:
        return "***"
    return s[:3] + "***" + s[-2:]


def _row_to_dict(r: dict, include_secret: bool = False) -> dict:
    secret_enc = r.get("secret_key") or ""
    out = {
        "id": int(r["id"]),
        "label": r["label"],
        "endpoint": r.get("endpoint") or "",
        "bucket": r["bucket"],
        "region": r.get("region") or "us-east-1",
        "prefix": r.get("prefix") or "",
        "access_key": r.get("access_key") or "",
        "secret_key_masked": _mask(cipher_service.decrypt(secret_enc)) if secret_enc else "",
        "retention_count": int(r.get("retention_count") or 10),
        "enabled": bool(r.get("enabled", True)),
        "priority": int(r.get("priority") or 100),
        "last_upload_at": _to_iso(r.get("last_upload_at")),
        "last_status": r.get("last_status"),
        "last_error": r.get("last_error"),
        "last_size_bytes": r.get("last_size_bytes"),
        "last_key": r.get("last_key"),
        "created_at": _to_iso(r.get("created_at")),
        "updated_at": _to_iso(r.get("updated_at")),
    }
    if include_secret:
        out["secret_key"] = cipher_service.decrypt(secret_enc) if secret_enc else ""
    return out


# ---------- CRUD ----------

async def list_destinations() -> list[dict]:
    rows = await db_fetchall(
        "SELECT * FROM backup_destinations ORDER BY priority DESC, label ASC", []
    )
    return [_row_to_dict(r) for r in rows]


async def get_destination(dest_id: int, include_secret: bool = False) -> dict | None:
    row = await db_fetchone(
        "SELECT * FROM backup_destinations WHERE id = ?", [int(dest_id)]
    )
    return _row_to_dict(row, include_secret=include_secret) if row else None


async def create_destination(body: dict) -> dict:
    label = str(body.get("label", "")).strip()
    bucket = str(body.get("bucket", "")).strip()
    if not label or not bucket:
        raise ValueError("label e bucket obrigatórios")

    secret_raw = str(body.get("secret_key", ""))
    secret_enc = cipher_service.encrypt(secret_raw) if secret_raw else ""

    await db_execute(
        """
        INSERT INTO backup_destinations
            (label, endpoint, bucket, region, prefix, access_key, secret_key,
             retention_count, enabled, priority)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        [
            label,
            str(body.get("endpoint", "")).strip(),
            bucket,
            str(body.get("region", "us-east-1")).strip() or "us-east-1",
            str(body.get("prefix", "")).strip(),
            str(body.get("access_key", "")).strip(),
            secret_enc,
            int(body.get("retention_count", 10)),
            bool(body.get("enabled", True)),
            max(0, min(1000, int(body.get("priority", 100)))),
        ],
    )
    row = await db_fetchone("SELECT * FROM backup_destinations WHERE label = ?", [label])
    return _row_to_dict(row)


async def update_destination(dest_id: int, body: dict) -> bool:
    sets = []
    params: list = []
    allowed = {
        "label": "STR", "endpoint": "STR", "bucket": "STR", "region": "STR",
        "prefix": "STR", "access_key": "STR", "retention_count": "INT",
        "enabled": "BOOL", "priority": "INT",
    }
    for k, typ in allowed.items():
        if k not in body:
            continue
        v = body[k]
        if typ == "BOOL":
            v = bool(v)
        elif typ == "INT":
            v = int(v)
            if k == "priority":
                v = max(0, min(1000, v))
            elif k == "retention_count":
                v = max(0, v)
        else:
            v = str(v or "").strip()
        sets.append(f"{k} = ?")
        params.append(v)

    # secret_key tratado separadamente — vazio preserva o atual
    if "secret_key" in body:
        secret_raw = str(body["secret_key"])
        if secret_raw:
            sets.append("secret_key = ?")
            params.append(cipher_service.encrypt(secret_raw))

    if not sets:
        return False
    sets.append("updated_at = NOW()")
    params.append(int(dest_id))
    await db_execute(f"UPDATE backup_destinations SET {', '.join(sets)} WHERE id = ?", params)
    return True


async def delete_destination(dest_id: int) -> bool:
    row = await db_fetchone(
        "SELECT id FROM backup_destinations WHERE id = ?", [int(dest_id)]
    )
    if not row:
        return False
    await db_execute("DELETE FROM backup_destinations WHERE id = ?", [int(dest_id)])
    return True


# ---------- Upload helpers ----------

def _dest_to_cfg(dest: dict) -> dict:
    """Converte row do backup_destinations → cfg que upload_backup espera."""
    return {
        "backup_s3_endpoint": dest.get("endpoint") or "",
        "backup_s3_bucket": dest["bucket"],
        "backup_s3_region": dest.get("region") or "us-east-1",
        "backup_s3_prefix": dest.get("prefix") or "",
        "backup_s3_access_key": dest.get("access_key") or "",
        "backup_s3_secret_key": dest["secret_key"],  # já decifrado pelo caller
        "backup_s3_retention_count": str(dest.get("retention_count", 10)),
    }


async def upload_to_all() -> dict:
    """Sobe pra todos destinations enabled, reusando UM tarball compartilhado.

    Compila o archive uma vez via `backup_offsite_service.create_archive()`
    e passa o path pré-construído pra cada destination (via parâmetro
    `prebuilt_archive` em `upload_backup`). O cleanup é centralizado aqui
    no final pra evitar que um destination apague o arquivo enquanto
    outros ainda estão fazendo upload.

    Resultado: 1x build (gargalo CPU+I/O) vs N builds anteriormente.
    Útil pra DuckDBs grandes onde a parte do tarball domina o tempo total.
    """
    from pathlib import Path as _Path

    from app.services import backup_offsite_service as legacy_svc

    rows = await db_fetchall(
        "SELECT * FROM backup_destinations WHERE enabled = true ORDER BY priority DESC",
        [],
    )
    if not rows:
        return {"results": [], "count": 0}

    loop = asyncio.get_running_loop()

    # Build do tarball 1x (sync, em executor pra não bloquear o event loop)
    try:
        archive_path, archive_size = await loop.run_in_executor(
            None, legacy_svc.create_archive
        )
    except Exception as exc:  # noqa: BLE001
        log.error("backup_destinations.archive_failed", error=str(exc))
        return {"results": [], "count": 0, "error": f"build archive: {exc}"}

    log.info(
        "backup_destinations.archive_built",
        size_bytes=archive_size, destinations=len(rows),
    )

    results: list[dict] = []
    try:
        for row in rows:
            secret = cipher_service.decrypt(row.get("secret_key") or "")
            dest_with_secret = {**dict(row), "secret_key": secret}
            cfg = _dest_to_cfg(dest_with_secret)
            try:
                # Passa o archive pré-construído; cleanup=False mantém o
                # arquivo vivo entre destinos. Cleanup central no finally.
                result = await loop.run_in_executor(
                    None,
                    functools.partial(
                        legacy_svc.upload_backup,
                        cfg,
                        prebuilt_archive=(archive_path, archive_size),
                        cleanup=False,
                    ),
                )
            except Exception as exc:  # noqa: BLE001
                result = {"success": False, "error": f"{type(exc).__name__}: {exc}"}

            # Persiste status na row
            await db_execute(
                """
                UPDATE backup_destinations
                SET last_upload_at = NOW(),
                    last_status = ?,
                    last_error = ?,
                    last_size_bytes = ?,
                    last_key = ?,
                    updated_at = NOW()
                WHERE id = ?
                """,
                [
                    "ok" if result.get("success") else "error",
                    (result.get("error") or "")[:1000] or None,
                    int(result.get("size_bytes") or 0) if result.get("size_bytes") else None,
                    str(result.get("key") or "")[:500] or None,
                    int(row["id"]),
                ],
            )
            results.append({
                "id": int(row["id"]),
                "label": row["label"],
                "success": bool(result.get("success")),
                "error": result.get("error"),
                "size_bytes": result.get("size_bytes"),
                "key": result.get("key"),
            })
            log.info(
                "backup_destinations.upload_done",
                id=int(row["id"]), label=row["label"], ok=result.get("success"),
            )
    finally:
        # Cleanup central — só depois que TODOS os destinations terminaram
        try:
            _Path(archive_path).unlink(missing_ok=True)
        except Exception:  # noqa: BLE001
            pass

    return {"results": results, "count": len(results), "archive_size_bytes": archive_size}


async def test_destination(dest_id: int) -> dict:
    """test_connection do svc legacy aplicado a esta destination."""
    from app.services import backup_offsite_service as legacy_svc

    dest = await get_destination(dest_id, include_secret=True)
    if not dest:
        return {"success": False, "error": "destination não encontrada"}
    cfg = _dest_to_cfg(dest)
    loop = asyncio.get_running_loop()
    try:
        return await loop.run_in_executor(None, legacy_svc.test_connection, cfg)
    except Exception as exc:  # noqa: BLE001
        return {"success": False, "error": f"{type(exc).__name__}: {exc}"}


async def count_enabled() -> int:
    """Quantos destinations enabled — BackupUploader usa pra decidir
    se entra em modo multi-destination ou cai pro legacy single-bucket."""
    row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM backup_destinations WHERE enabled = true", []
    )
    return int(row["n"]) if row else 0
