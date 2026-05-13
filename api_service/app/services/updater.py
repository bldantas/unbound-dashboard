"""
Self-update via UI — núcleo do orquestrador.

Responsabilidades:
  - Consultar GitHub Releases / API REST pra última versão disponível
  - Baixar tarball + .sha256, validar checksum
  - Spawnar `sudo bash tools/update.sh <tarball>` via subprocess
  - Manter estado do job em Redis (status, log_path, exit_code)
  - Lock global pra impedir 2 updates simultâneos

Endpoints (`routers/updates.py`) consomem este módulo.

Estados de um job (Redis `udash:update:job:<job_id>`):
  - `running`     : subprocess vivo
  - `succeeded`   : exit 0
  - `failed`      : exit != 0/2/3 (erro de aplicação)
  - `rolled_back` : exit 2 (update.sh detectou falha e rollback OK)
  - `rollback_failed` : exit 3 (rollback também falhou — gravíssimo)

Lock global: `udash:update:running` (TTL 30min). Endpoint /apply
checa antes de iniciar; retorna 409 se outro update está rodando.
"""

from __future__ import annotations

import asyncio
import hashlib
import json
import re
import subprocess
import time
import uuid
from pathlib import Path
from typing import Any

import httpx
import structlog

from app.core.config import settings
from app.infrastructure.redis_client import get_redis

log = structlog.get_logger(__name__)

# ============================================================
# Constantes
# ============================================================

GITHUB_REPO = "bldantas/unbound-dashboard"
GITHUB_API_URL = f"https://api.github.com/repos/{GITHUB_REPO}/releases/latest"
UPDATES_DIR = Path("/var/lib/unbound-dashboard/updates")
LOG_DIR = Path("/var/log/unbound-dashboard")
UPDATE_SCRIPT = "/var/www/html/unbound-dashboard/tools/update.sh"
VERSION_FILE = Path("/var/www/html/unbound-dashboard/VERSION")

REDIS_LATEST_KEY = "udash:update:latest"
REDIS_LOCK_KEY = "udash:update:running"
REDIS_JOB_KEY_FMT = "udash:update:job:{job_id}"

LATEST_CACHE_SECONDS = 300  # 5min
LOCK_TTL_SECONDS = 1800  # 30min
JOB_TTL_SECONDS = 86400  # 1 dia (audit trail)
HTTP_TIMEOUT = 15.0

# Regex pra extrair semver da tag "v2.16.3" ou "2.16.3"
_TAG_RE = re.compile(r"^v?(\d+)\.(\d+)\.(\d+)$")


# ============================================================
# Exceções de domínio
# ============================================================


class UpdaterError(Exception):
    """Base de erros do updater — capturada pelo router."""


class GitHubUnavailable(UpdaterError):
    pass


class TarballDownloadFailed(UpdaterError):
    pass


class ChecksumMismatch(UpdaterError):
    pass


class UpdateLocked(UpdaterError):
    """Outro update já está em andamento."""


class VersionMismatch(UpdaterError):
    """Versão pedida não bate com a última no GitHub (anti-replay)."""


class MissingBreakingAck(UpdaterError):
    """Major bump exige acknowledge_breaking=True."""


# ============================================================
# Utilitários
# ============================================================


def _read_local_version() -> str:
    try:
        return VERSION_FILE.read_text().strip()
    except Exception:  # noqa: BLE001
        return "0.0.0"


def _parse_semver(s: str) -> tuple[int, int, int] | None:
    m = _TAG_RE.match(s.strip())
    if not m:
        return None
    return (int(m.group(1)), int(m.group(2)), int(m.group(3)))


def _is_newer(latest: str, current: str) -> bool:
    a = _parse_semver(latest)
    b = _parse_semver(current)
    if a is None or b is None:
        return False
    return a > b


def _is_major_bump(latest: str, current: str) -> bool:
    a = _parse_semver(latest)
    b = _parse_semver(current)
    if a is None or b is None:
        return False
    return a[0] > b[0]


# ============================================================
# GitHub API
# ============================================================


async def fetch_latest_release(force_refresh: bool = False) -> dict[str, Any]:
    """
    Consulta GitHub `/releases/latest`, retorna metadata. Cacheia em Redis
    por `LATEST_CACHE_SECONDS`. Em caso de falha de rede, devolve cache
    se houver — senão raise GitHubUnavailable.
    """
    r = await get_redis()
    if not force_refresh:
        try:
            cached_raw = await r.get(REDIS_LATEST_KEY)
            if cached_raw:
                return json.loads(cached_raw)
        except Exception:  # noqa: BLE001
            pass

    try:
        async with httpx.AsyncClient(timeout=HTTP_TIMEOUT) as client:
            resp = await client.get(
                GITHUB_API_URL,
                headers={"Accept": "application/vnd.github+json"},
            )
        resp.raise_for_status()
        data = resp.json()
    except Exception as exc:  # noqa: BLE001
        # Fallback: tenta retornar cache mesmo expirado se houver
        try:
            stale = await r.get(REDIS_LATEST_KEY)
            if stale:
                log.warning("updater.github_unavailable_using_stale_cache", error=str(exc))
                return json.loads(stale)
        except Exception:  # noqa: BLE001
            pass
        raise GitHubUnavailable(f"GitHub indisponível: {exc}") from None

    # Reduz pra só o que interessa
    assets = []
    for a in data.get("assets", []):
        assets.append({
            "name": a.get("name", ""),
            "browser_download_url": a.get("browser_download_url", ""),
            "size": a.get("size", 0),
        })

    payload = {
        "tag_name": data.get("tag_name", ""),
        "name": data.get("name") or data.get("tag_name", ""),
        "body": data.get("body", ""),
        "published_at": data.get("published_at", ""),
        "html_url": data.get("html_url", ""),
        "assets": assets,
    }
    try:
        await r.setex(REDIS_LATEST_KEY, LATEST_CACHE_SECONDS, json.dumps(payload))
    except Exception:  # noqa: BLE001
        pass
    return payload


async def check_for_updates() -> dict[str, Any]:
    """
    Compara última release com VERSION local. Retorna:
        {current, latest, has_update, is_major_bump, release_url, body,
         published_at, tag_name, assets}
    Ou {current, has_update: false, error: ...} se GitHub off.
    """
    current = _read_local_version()
    try:
        rel = await fetch_latest_release()
    except GitHubUnavailable as exc:
        return {
            "current": current,
            "has_update": False,
            "error": str(exc),
        }
    latest = rel["tag_name"].lstrip("v")
    return {
        "current": current,
        "latest": latest,
        "tag_name": rel["tag_name"],
        "has_update": _is_newer(latest, current),
        "is_major_bump": _is_major_bump(latest, current),
        "release_url": rel["html_url"],
        "body": rel["body"],
        "published_at": rel["published_at"],
        "assets": rel["assets"],
    }


# ============================================================
# Download + verificação
# ============================================================


def _find_assets(release: dict[str, Any]) -> tuple[dict, dict] | tuple[None, None]:
    """Encontra (tarball_asset, sha256_asset). Ambos precisam existir."""
    tarball = None
    sha = None
    for a in release.get("assets", []):
        name = a.get("name", "")
        if name.endswith(".tar.gz") and "unbound-dashboard-update" in name:
            tarball = a
        elif name.endswith(".tar.gz.sha256"):
            sha = a
    if tarball is None or sha is None:
        return None, None
    return tarball, sha


async def _download(url: str, dest: Path) -> None:
    """Baixa URL pra dest. Atômico via .part + rename."""
    part = dest.with_suffix(dest.suffix + ".part")
    try:
        async with httpx.AsyncClient(timeout=300.0, follow_redirects=True) as client:
            async with client.stream("GET", url) as resp:
                resp.raise_for_status()
                with part.open("wb") as f:
                    async for chunk in resp.aiter_bytes(chunk_size=64 * 1024):
                        f.write(chunk)
        part.replace(dest)
    except Exception as exc:
        part.unlink(missing_ok=True)
        raise TarballDownloadFailed(f"Falha ao baixar {url}: {exc}") from None


def _verify_sha256(file_path: Path, sha_file_path: Path) -> bool:
    """Compara sha256(file_path) com a primeira coluna de sha_file_path."""
    try:
        expected_line = sha_file_path.read_text().strip().split()
    except Exception:  # noqa: BLE001
        return False
    if not expected_line:
        return False
    expected = expected_line[0].lower()

    hasher = hashlib.sha256()
    with file_path.open("rb") as f:
        for chunk in iter(lambda: f.read(64 * 1024), b""):
            hasher.update(chunk)
    actual = hasher.hexdigest().lower()
    return actual == expected


async def download_and_verify(release: dict[str, Any]) -> Path:
    """
    Baixa tarball + .sha256 pra UPDATES_DIR, valida checksum.
    Retorna path do tarball. Raise ChecksumMismatch se falhar.
    """
    tarball_asset, sha_asset = _find_assets(release)
    if tarball_asset is None or sha_asset is None:
        raise TarballDownloadFailed(
            "Release não tem tarball+sha256 — release malformada"
        )

    UPDATES_DIR.mkdir(parents=True, exist_ok=True)
    tarball_path = UPDATES_DIR / tarball_asset["name"]
    sha_path = UPDATES_DIR / sha_asset["name"]

    log.info("updater.downloading", tarball=tarball_asset["name"], size=tarball_asset.get("size"))
    await _download(tarball_asset["browser_download_url"], tarball_path)
    await _download(sha_asset["browser_download_url"], sha_path)

    if not _verify_sha256(tarball_path, sha_path):
        # Limpa arquivos suspeitos pra evitar reuso acidental
        tarball_path.unlink(missing_ok=True)
        sha_path.unlink(missing_ok=True)
        raise ChecksumMismatch(
            f"SHA256 do tarball ({tarball_asset['name']}) não bate com .sha256"
        )

    log.info("updater.download_verified", tarball=str(tarball_path))
    return tarball_path


# ============================================================
# Lock + Spawn
# ============================================================


async def acquire_lock(job_id: str) -> bool:
    """SET NX EX — só um update por vez."""
    r = await get_redis()
    try:
        # redis-py async retorna True se setou, False se já existe
        return bool(await r.set(REDIS_LOCK_KEY, job_id, nx=True, ex=LOCK_TTL_SECONDS))
    except Exception:  # noqa: BLE001
        return False


async def release_lock() -> None:
    r = await get_redis()
    try:
        await r.delete(REDIS_LOCK_KEY)
    except Exception:  # noqa: BLE001
        pass


async def get_running_job_id() -> str | None:
    r = await get_redis()
    try:
        val = await r.get(REDIS_LOCK_KEY)
        return val if isinstance(val, str) else (val.decode() if val else None)
    except Exception:  # noqa: BLE001
        return None


async def _save_job_state(job_id: str, **fields) -> None:
    """Grava estado parcial do job em Redis (TTL 1 dia)."""
    r = await get_redis()
    try:
        existing_raw = await r.get(REDIS_JOB_KEY_FMT.format(job_id=job_id))
        existing = json.loads(existing_raw) if existing_raw else {}
        existing.update(fields)
        await r.setex(
            REDIS_JOB_KEY_FMT.format(job_id=job_id),
            JOB_TTL_SECONDS,
            json.dumps(existing),
        )
    except Exception as exc:  # noqa: BLE001
        log.warning("updater.save_job_state_failed", job_id=job_id, error=str(exc))


async def get_job_state(job_id: str) -> dict[str, Any] | None:
    r = await get_redis()
    try:
        raw = await r.get(REDIS_JOB_KEY_FMT.format(job_id=job_id))
        return json.loads(raw) if raw else None
    except Exception:  # noqa: BLE001
        return None


# Mapeia exit code de update.sh → status final do job
_EXIT_TO_STATUS = {
    0: "succeeded",
    2: "rolled_back",
    3: "rollback_failed",
}


def _spawn_update_process(tarball_path: Path, log_path: Path) -> int:
    """
    Spawna `sudo bash tools/update.sh <tarball>`, redireciona stdout/stderr
    pro log_path, NÃO espera (background). Retorna PID.
    """
    log_path.parent.mkdir(parents=True, exist_ok=True)
    # 'append' mode pra não perder log se já existir
    log_fd = log_path.open("ab", buffering=0)
    try:
        # sudo precisa ser sem-tty (-n) pra falhar limpo se sudoers não permitir
        proc = subprocess.Popen(  # noqa: S603
            ["sudo", "-n", "/usr/bin/bash", UPDATE_SCRIPT, str(tarball_path)],
            stdout=log_fd,
            stderr=subprocess.STDOUT,
            stdin=subprocess.DEVNULL,
            start_new_session=True,  # detacha do parent (sobrevive ao reload do uvicorn)
        )
        return proc.pid
    finally:
        log_fd.close()


async def apply_update(version: str, acknowledge_breaking: bool = False) -> str:
    """
    Pipeline completo: valida → download → spawn → registra job.
    Retorna job_id pro caller poder consultar status / log.

    Levanta:
      - UpdateLocked          : outro update já rodando
      - VersionMismatch       : version pedida ≠ latest no GitHub
      - MissingBreakingAck    : major bump sem ack
      - GitHubUnavailable
      - TarballDownloadFailed
      - ChecksumMismatch
    """
    # 1. Checa lock ANTES de qualquer trabalho pesado
    job_id = uuid.uuid4().hex[:12]
    if not await acquire_lock(job_id):
        current = await get_running_job_id()
        raise UpdateLocked(f"Update {current} já está em andamento")

    try:
        # 2. Refresh forçado pra evitar race com cache de 5min
        release = await fetch_latest_release(force_refresh=True)
        latest = release["tag_name"].lstrip("v")
        if latest != version:
            raise VersionMismatch(
                f"Versão pedida v{version} ≠ última publicada v{latest}"
            )

        # 3. Major bump exige ack
        current_local = _read_local_version()
        if _is_major_bump(latest, current_local) and not acknowledge_breaking:
            raise MissingBreakingAck(
                f"Update de {current_local} → {latest} é major version — "
                "marque a caixa 'Estou ciente das breaking changes'"
            )

        # 4. Download + verificação
        tarball_path = await download_and_verify(release)

        # 5. Spawn detachado
        log_path = LOG_DIR / f"update-{job_id}.log"
        pid = _spawn_update_process(tarball_path, log_path)

        # 6. Registra job no Redis
        await _save_job_state(
            job_id,
            status="running",
            from_version=current_local,
            to_version=latest,
            pid=pid,
            log_path=str(log_path),
            tarball=str(tarball_path),
            started_at=int(time.time()),
        )

        # 7. Monitor em background — atualiza status quando processo terminar
        asyncio.create_task(_monitor_job(job_id, pid, log_path))

        log.info("updater.apply_started", job_id=job_id, version=latest, pid=pid)
        return job_id
    except Exception:
        # Qualquer falha pré-spawn libera o lock
        await release_lock()
        raise


async def _monitor_job(job_id: str, pid: int, log_path: Path) -> None:
    """
    Polleia `/proc/<pid>` a cada 2s. Quando processo termina, lê exit code
    via wait_for_exit (que faz `waitpid` ou checa log final) e grava status
    final em Redis.

    Como o subprocess foi spawnado com start_new_session, ele NÃO é mais
    filho deste processo Python (detached). Não podemos usar Popen.wait().
    Em vez disso, polleia `/proc/<pid>` e parseia a última linha do log
    pra deduzir status.
    """
    proc_path = Path(f"/proc/{pid}")
    while proc_path.exists():
        await asyncio.sleep(2)

    # Processo terminou. Como é detachado, não temos exit code direto.
    # Heurística: parsea o log pra detectar marcadores de update.sh.
    status = _infer_status_from_log(log_path)
    await _save_job_state(job_id, status=status, finished_at=int(time.time()))
    await release_lock()
    log.info("updater.job_finished", job_id=job_id, status=status)


def _infer_status_from_log(log_path: Path) -> str:
    """
    Infere status final lendo as últimas linhas do log do update.sh.
    Procura por strings marcadoras emitidas pelo restart_and_smoke +
    rollback_from_backup. Default: failed se nada bater.
    """
    try:
        # Lê as últimas 50 linhas (suficiente pra capturar conclusão)
        text = log_path.read_text(errors="replace")
        tail = "\n".join(text.splitlines()[-50:])
    except Exception:  # noqa: BLE001
        return "failed"

    if "ROLLBACK FAILED" in tail:
        return "rollback_failed"
    if "ROLLBACK CONCLUÍDO" in tail or "rollback executado" in tail.lower():
        return "rolled_back"
    if "Update concluído" in tail and "DRY-RUN" not in tail:
        return "succeeded"
    return "failed"
