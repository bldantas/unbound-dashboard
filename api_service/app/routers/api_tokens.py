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


@router.post("", status_code=status.HTTP_201_CREATED)
async def create_token(
    body: CreateTokenRequest,
    payload: Annotated[dict, Depends(require_global_admin)],
) -> dict:
    """
    Cria token novo. Retorna o **raw_token** UMA VEZ — admin tem que copiar
    agora. Subsequentes calls só mostram o hash + metadata.
    """
    user_id_raw = payload.get("sub", 0)
    try:
        user_id = int(user_id_raw)
    except (TypeError, ValueError):
        user_id = None
    new_id, raw = await api_tokens.create(body.label, created_by=user_id)
    return {"id": new_id, "label": body.label, "raw_token": raw}


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
