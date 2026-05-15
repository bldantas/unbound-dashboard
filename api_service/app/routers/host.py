"""
/api/v1/host/* — endpoints específicos pra master multi-host consumir.

Diferente de /healthz (público, mínimo) e /metrics (Prometheus, machine-
readable bare), estes retornam JSON estruturado pro master agregar e
exibir em /hosts.php.

Auth: aceita JWT OU X-Api-Token (require_auth normalizado).

Endpoints:
  GET /api/v1/host/status — snapshot completo: versão, uptime, healthz,
    contadores Unbound (qps, hit ratio), alertas ativos, queries 24h,
    sessões ativas, total de users.
  GET /api/v1/host/info — info estática: hostname, OS, kernel, IP local.
"""

from __future__ import annotations

import platform
import socket
import subprocess
import time
from typing import Annotated

import structlog
from fastapi import APIRouter, Depends, HTTPException, status

from app.core.config import settings
from app.core.deps import require_auth, require_capability
from app.repositories.duckdb.connection import db_fetchone

router = APIRouter(prefix="/api/v1/host", tags=["host"])
log = structlog.get_logger()

_START_TIME = time.time()  # Aproximação do uptime do api_service


def _read_version() -> str:
    try:
        with open("/var/www/html/unbound-dashboard/VERSION") as f:
            return f.read().strip()
    except Exception:  # noqa: BLE001
        return "?"


@router.get("/info")
async def host_info(_: Annotated[dict, Depends(require_auth)]) -> dict:
    """
    Info estática do host. Cacheável aggressively no master — só muda
    se a máquina for renomeada ou OS for atualizado.
    """
    return {
        "hostname": socket.gethostname(),
        "fqdn": socket.getfqdn(),
        "system": platform.system(),
        "release": platform.release(),
        "machine": platform.machine(),
        "python_version": platform.python_version(),
        "version": _read_version(),
        "api_version": settings.api_version,
    }


@router.get("/status")
async def host_status(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """
    Snapshot agregado pro master polar. Inclui métricas que mudam em
    tempo real — não cachear no master.

    Campos:
      - version: VERSION local
      - uptime_seconds: tempo desde boot do api_service
      - alerts_active: count de alertas não-resolvidos
      - users_total: total de users cadastrados
      - sessions_active: total de sessões trackadas (Redis ou DuckDB)
      - queries_24h: total de query_logs nas últimas 24h
      - hit_ratio_24h: % de cache hits últimas 24h
      - duckdb_ok: True se SELECT 1 rolou
      - auth_kind: como o caller se autenticou (jwt | api_token)
    """
    out: dict = {
        "version": _read_version(),
        "uptime_seconds": int(time.time() - _START_TIME),
        "auth_kind": payload.get("auth_kind", "jwt"),
        "duckdb_ok": False,
    }

    # DuckDB básico (best-effort em cada um — falha não derruba o resto)
    try:
        await db_fetchone("SELECT 1 AS ok")
        out["duckdb_ok"] = True
    except Exception:  # noqa: BLE001
        pass

    try:
        row = await db_fetchone(
            "SELECT COUNT(*) AS n FROM alerts WHERE resolved_at IS NULL"
        )
        out["alerts_active"] = int(row["n"]) if row else 0
    except Exception:  # noqa: BLE001
        out["alerts_active"] = None

    try:
        row = await db_fetchone("SELECT COUNT(*) AS n FROM users")
        out["users_total"] = int(row["n"]) if row else 0
    except Exception:  # noqa: BLE001
        out["users_total"] = None

    try:
        row = await db_fetchone(
            "SELECT COUNT(*) AS n FROM auth_sessions "
            "WHERE revoked_at IS NULL AND exp > ?",
            [int(time.time())],
        )
        out["sessions_active"] = int(row["n"]) if row else 0
    except Exception:  # noqa: BLE001
        out["sessions_active"] = None

    try:
        row = await db_fetchone(
            "SELECT COUNT(*) AS n, "
            "SUM(CASE WHEN action='cache_hit' THEN 1 ELSE 0 END) AS hits "
            "FROM query_logs WHERE timestamp > ?",
            [int(time.time()) - 86400],
        )
        total = int(row["n"]) if row else 0
        hits = int(row["hits"]) if row and row.get("hits") else 0
        out["queries_24h"] = total
        out["hit_ratio_24h"] = round((hits / total * 100), 1) if total else 0.0
    except Exception:  # noqa: BLE001
        out["queries_24h"] = None
        out["hit_ratio_24h"] = None

    return out


# Whitelist explícito — qualquer outro valor é rejeitado antes de tocar systemctl.
_RESTART_SERVICE_MAP = {
    "api": "unbound-dashboard-api",
    "unbound": "unbound",
}


@router.post("/restart/{service}", status_code=status.HTTP_202_ACCEPTED)
async def restart_service(
    service: str,
    payload: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """
    Reinicia um serviço whitelisted (api | unbound). Spawn detachado:
    o systemctl roda em session group novo, sobrevive se o caller for o
    próprio api_service sendo morto.

    Pedida pelo master multi-host nos batch ops; também útil localmente.
    """
    systemd_unit = _RESTART_SERVICE_MAP.get(service)
    if systemd_unit is None:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Serviço inválido: {service}. Permitidos: {sorted(_RESTART_SERVICE_MAP)}",
        )

    actor = payload.get("auth_kind", "jwt")
    log.info("host.restart_service", service=service, unit=systemd_unit, actor=actor)

    try:
        subprocess.Popen(  # noqa: S603
            ["sudo", "-n", "/usr/bin/systemctl", "restart", systemd_unit],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            stdin=subprocess.DEVNULL,
            start_new_session=True,
        )
    except Exception as exc:  # noqa: BLE001
        log.error("host.restart_spawn_failed", service=service, error=str(exc))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Falha ao disparar restart: {exc}",
        ) from None

    return {"ok": True, "service": service, "unit": systemd_unit}
