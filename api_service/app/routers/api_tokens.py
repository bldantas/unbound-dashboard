"""Endpoints /api/v1/api-tokens — CRUD de tokens long-lived pra master."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel, Field

from app.core.deps import require_global_admin
from app.services import api_tokens

router = APIRouter(prefix="/api/v1/api-tokens", tags=["api-tokens"])


@router.get("")
async def list_tokens(
    _: Annotated[dict, Depends(require_global_admin)],
    include_revoked: bool = False,
) -> dict:
    items = await api_tokens.list_active(include_revoked=include_revoked)
    return {"tokens": items, "count": len(items)}


class CreateTokenRequest(BaseModel):
    label: str = Field(min_length=1, max_length=100, description="Identificação do token, ex: 'master-orchestrator'")
    capabilities: list[str] | None = Field(
        default=None,
        description="Lista de capabilities concedidas. Vazio/None = admin global "
                    "(backward-compat). Lista não vazia = token restrito a essas caps. "
                    "Caps válidas: ver /api/v1/api-tokens/capabilities-catalog",
    )


@router.get("/capabilities-catalog")
async def list_capabilities(
    _: Annotated[dict, Depends(require_global_admin)],
) -> dict:
    """Lista capabilities disponíveis pra atribuir a tokens.

    Usado pela UI pra montar checkboxes na criação de token escopado.
    """
    from app.core.rbac import CAPABILITIES
    return {
        "capabilities": sorted(CAPABILITIES.keys()),
        "details": {
            cap: {"allowed_roles": sorted(roles)}
            for cap, roles in CAPABILITIES.items()
        },
    }


@router.post("", status_code=status.HTTP_201_CREATED)
async def create_token(
    body: CreateTokenRequest,
    payload: Annotated[dict, Depends(require_global_admin)],
) -> dict:
    """
    Cria token novo. Retorna o **raw_token** UMA VEZ — admin tem que copiar
    agora. Subsequentes calls só mostram o hash + metadata.

    `capabilities` (v2.110+):
    - Omitido/null/[] → admin global (sem restrições)
    - Lista de strings → token só pode chamar endpoints que pedem essas caps
    """
    user_id_raw = payload.get("sub", 0)
    try:
        user_id = int(user_id_raw)
    except (TypeError, ValueError):
        user_id = None
    # Valida caps contra o catálogo conhecido pra evitar typos
    if body.capabilities:
        from app.core.rbac import CAPABILITIES
        unknown = set(body.capabilities) - set(CAPABILITIES.keys())
        if unknown:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Capabilities desconhecidas: {sorted(unknown)}. "
                       f"Catálogo em /api/v1/api-tokens/capabilities-catalog",
            )
    new_id, raw = await api_tokens.create(
        body.label, created_by=user_id, capabilities=body.capabilities,
    )
    return {
        "id": new_id,
        "label": body.label,
        "raw_token": raw,
        "capabilities": body.capabilities or [],
        "is_scoped": bool(body.capabilities),
    }


@router.delete("/{token_id}", status_code=status.HTTP_204_NO_CONTENT)
async def revoke_token(
    token_id: int,
    _: Annotated[dict, Depends(require_global_admin)],
) -> None:
    ok = await api_tokens.revoke(token_id)
    if not ok:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Token não encontrado ou já revogado",
        )
