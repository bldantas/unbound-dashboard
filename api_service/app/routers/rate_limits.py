"""
/api/v1/rate-limits — admin lê/edita configuração de rate-limits.

NOTA: nesta release editamos só as settings persistidas (visualmente).
As mudanças aplicam após restart do API (slowapi instancia o Limiter
no startup). Hot-reload seria possível mas exigiria recriar o limiter
e re-aplicar nos handlers — fora do escopo.
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request

from app.core.config import settings
from app.core.deps import require_admin
from app.repositories.duckdb import settings_repo
from app.services import admin_audit_service

router = APIRouter(prefix="/api/v1/rate-limits", tags=["rate-limits"])

# Settings persistidas (DuckDB) — sobrescrevem env defaults na próxima inicialização
SETTING_KEYS = ("rate_limit_default", "rate_limit_auth", "rate_limit_token")


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


@router.get("/config")
async def get_config(_: Annotated[dict, Depends(require_admin)]) -> dict:
    """Retorna config atual (env + settings DB) — UI mostra qual está vigente."""
    out = {
        "active": {
            "default": settings.rate_limit_default,
            "auth": settings.rate_limit_auth,
            "enabled": settings.rate_limit_enabled,
        },
        "db_overrides": {},
        "key_strategy": "token-first (Authorization Bearer / X-Api-Token); fallback IP",
    }
    for k in SETTING_KEYS:
        v = await settings_repo.get(k, "")
        if v:
            out["db_overrides"][k] = v
    return out


@router.put("/config")
async def update_config(
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    """Persiste novos limites em settings. Aplica após restart do API."""
    entries = []
    for k in SETTING_KEYS:
        if k not in body:
            continue
        v = str(body[k]).strip()
        # Valida formato slowapi: "<n>/<unit>" (ex: "200/minute")
        if v and "/" not in v:
            raise HTTPException(status_code=400, detail=f"{k} formato inválido (esperado 'N/unit', ex: '200/minute')")
        entries.append({"setting_key": k, "setting_value": v})
    if not entries:
        raise HTTPException(status_code=400, detail="nenhum campo válido fornecido")
    await settings_repo.bulk_upsert(entries)
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="rate_limits.update",
        category="config",
        details={"fields": {e["setting_key"]: e["setting_value"] for e in entries}},
    )
    return {"updated": len(entries), "note": "Aplica após restart do unbound-dashboard-api"}
