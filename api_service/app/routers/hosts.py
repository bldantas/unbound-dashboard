"""
/api/v1/hosts/* — gerência de agents pelo master no multi-host.

CRUD + poll on-demand. Capability `config.write` (admin only).

Endpoints:
  GET    /api/v1/hosts                — lista com último status
  POST   /api/v1/hosts                — adiciona host {label, base_url, api_token, notes}
  PUT    /api/v1/hosts/{id}           — atualiza label/api_token/notes
  DELETE /api/v1/hosts/{id}           — remove
  POST   /api/v1/hosts/{id}/poll      — força poll agora (atualiza estado)
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel, Field

from app.core.deps import require_capability
from app.services import managed_hosts

router = APIRouter(prefix="/api/v1/hosts", tags=["hosts"])


def _scrub(h: dict) -> dict:
    """Remove campos sensíveis da resposta (api_token)."""
    out = dict(h)
    out.pop("api_token", None)
    return out


@router.get("")
async def list_hosts(_: Annotated[dict, Depends(require_capability("config.write"))]) -> dict:
    items = await managed_hosts.list_all()
    return {"hosts": items, "count": len(items)}


class HostCreate(BaseModel):
    label: str = Field(min_length=1, max_length=100)
    base_url: str = Field(min_length=8, max_length=255, description="https://host:port (sem /api/...)")
    api_token: str = Field(min_length=20, max_length=255, description="Token gerado em Settings → API Tokens do agent")
    notes: str | None = Field(default=None, max_length=500)


@router.post("", status_code=status.HTTP_201_CREATED)
async def create_host(
    body: HostCreate,
    payload: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    if not (body.base_url.startswith("http://") or body.base_url.startswith("https://")):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="base_url precisa começar com http:// ou https://",
        )
    try:
        user_id = int(payload.get("sub", 0))
    except (TypeError, ValueError):
        user_id = None
    try:
        new_id = await managed_hosts.create(
            label=body.label,
            base_url=body.base_url,
            api_token=body.api_token,
            notes=body.notes,
            added_by=user_id,
        )
    except managed_hosts.DuplicateHost as exc:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail=str(exc),
        ) from None
    return {"id": new_id, "label": body.label}


class HostUpdate(BaseModel):
    label: str | None = Field(default=None, min_length=1, max_length=100)
    api_token: str | None = Field(default=None, max_length=255, description="Vazio = manter o atual")
    notes: str | None = Field(default=None, max_length=500)


@router.put("/{host_id}", status_code=status.HTTP_204_NO_CONTENT)
async def update_host(
    host_id: int,
    body: HostUpdate,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> None:
    ok = await managed_hosts.update(
        host_id,
        label=body.label,
        api_token=body.api_token,
        notes=body.notes,
    )
    if not ok:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado")


@router.delete("/{host_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_host(
    host_id: int,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> None:
    ok = await managed_hosts.delete(host_id)
    if not ok:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado")


@router.post("/{host_id}/poll")
async def poll_now(
    host_id: int,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Força poll imediato do host. Retorna resultado."""
    try:
        result = await managed_hosts.poll_host(host_id)
    except managed_hosts.HostNotFound:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Host não encontrado") from None
    return result
