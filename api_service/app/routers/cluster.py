"""
Endpoints /api/v1/cluster — comunicação inter-peer autenticada.

Hoje: só `peer-ping` (healthcheck autenticado com X-Api-Token).
Futuro: endpoints de sync de config, failover programático, etc.

Auth: X-Api-Token (ou Authorization: Bearer) validado via bcrypt.checkpw
contra QUALQUER `ha_peers.api_token_hash` registrado localmente. Modelo
"shared secret por link": cada par A↔B compartilha o mesmo token,
gerado em um lado e colado no outro via `existing_token` no create_peer.
"""

from __future__ import annotations

import bcrypt
import structlog
from fastapi import APIRouter, Header, HTTPException, Request

from app.core.config import settings
from app.repositories.duckdb.connection import db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

router = APIRouter(prefix="/api/v1/cluster", tags=["cluster"])


def _extract_token(x_api_token: str | None, authorization: str | None) -> str | None:
    """Aceita X-Api-Token ou Authorization: Bearer."""
    if x_api_token:
        return x_api_token.strip()
    if authorization and authorization.startswith("Bearer "):
        return authorization[len("Bearer "):].strip() or None
    return None


async def _validate_peer_token(token: str) -> dict | None:
    """Verifica o token contra TODOS os api_token_hash de ha_peers.
    Retorna o row do peer que casou ou None.

    Custo: bcrypt.checkpw por peer. Em deploys típicos (2-5 peers) é
    desprezível (~10ms total).
    """
    if not token:
        return None
    rows = await db_fetchall(
        "SELECT id, label, api_token_hash, role FROM ha_peers WHERE enabled = true AND api_token_hash IS NOT NULL"
    )
    token_b = token.encode("utf-8")
    for r in rows:
        hashed = r.get("api_token_hash") or ""
        if not hashed:
            continue
        try:
            if bcrypt.checkpw(token_b, hashed.encode("utf-8")):
                return r
        except Exception:  # noqa: BLE001
            continue
    return None


@router.get("/peer-ping")
async def peer_ping(
    request: Request,
    x_api_token: str | None = Header(default=None, alias="X-Api-Token"),
    authorization: str | None = Header(default=None),
) -> dict:
    """Healthcheck autenticado entre peers HA.

    Validado contra ha_peers.api_token_hash (bcrypt). Retorna info do
    servidor pra o peer chamador saber com quem está falando.
    """
    token = _extract_token(x_api_token, authorization)
    if not token:
        raise HTTPException(status_code=401, detail="X-Api-Token ausente")

    matched = await _validate_peer_token(token)
    if not matched:
        log.warning(
            "cluster.peer_ping.unauthorized",
            ip=request.client.host if request.client else None,
        )
        raise HTTPException(status_code=401, detail="Token não corresponde a nenhum peer")

    # Carrega quaisquer peers locais pra responder com role agregado
    row = await db_fetchone(
        "SELECT role FROM ha_peers WHERE label = ? LIMIT 1",
        [matched["label"]],
    )
    local_role = row.get("role") if row else None

    return {
        "ok": True,
        "version": settings.api_version,
        "matched_peer_label": matched["label"],
        "matched_peer_role": local_role,
    }
