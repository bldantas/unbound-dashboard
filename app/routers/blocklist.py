"""Blocklist endpoints — gerenciamento de fontes e domínios bloqueados."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel

from app.core.deps import require_admin, require_auth
from app.services.blocklist_service import BlocklistService

router = APIRouter(prefix="/api/v2/blocklist", tags=["blocklist"])


def _get_service() -> BlocklistService:
    return BlocklistService()


class AddSourceBody(BaseModel):
    name: str
    url: str


class DomainBody(BaseModel):
    domain: str


@router.get("/sources")
async def list_sources(
    _: dict = Depends(require_auth),
    svc: BlocklistService = Depends(_get_service),
) -> list[dict]:
    return await svc.get_sources()


@router.post("/sources", status_code=status.HTTP_201_CREATED)
async def add_source(
    body: AddSourceBody,
    _: dict = Depends(require_admin),
    svc: BlocklistService = Depends(_get_service),
) -> dict:
    try:
        await svc.add_source(body.name, body.url)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    return {"ok": True}


@router.delete("/sources")
async def remove_source(
    url: str,
    _: dict = Depends(require_admin),
    svc: BlocklistService = Depends(_get_service),
) -> dict:
    await svc.remove_source(url)
    return {"ok": True}


@router.post("/sync")
async def sync(
    _: dict = Depends(require_admin),
    svc: BlocklistService = Depends(_get_service),
) -> dict:
    """Dispara sincronização de todas as fontes habilitadas."""
    return await svc.sync_all()


@router.get("/domains")
async def list_blocked(
    _: dict = Depends(require_auth),
    svc: BlocklistService = Depends(_get_service),
) -> list[str]:
    return await svc.list_blocked()


@router.post("/domains", status_code=status.HTTP_201_CREATED)
async def block_domain(
    body: DomainBody,
    _: dict = Depends(require_admin),
    svc: BlocklistService = Depends(_get_service),
) -> dict:
    try:
        await svc.add_domain(body.domain)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    return {"ok": True, "domain": body.domain}


@router.delete("/domains/{domain}", status_code=status.HTTP_204_NO_CONTENT)
async def unblock_domain(
    domain: str,
    _: dict = Depends(require_admin),
    svc: BlocklistService = Depends(_get_service),
) -> None:
    await svc.remove_domain(domain)
