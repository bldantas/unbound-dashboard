"""Unbound config/control endpoints."""

from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel

from app.core.deps import require_admin, require_auth
from app.services.unbound_service import UnboundService

router = APIRouter(prefix="/api/v2/unbound", tags=["unbound"])


def _get_service() -> UnboundService:
    return UnboundService()


class FlushBody(BaseModel):
    domain: str


@router.get("/stats")
async def get_stats(
    force: bool = False,
    _: dict = Depends(require_auth),
    svc: UnboundService = Depends(_get_service),
) -> dict:
    return await svc.get_stats(force_refresh=force)


@router.get("/status")
async def get_status(
    _: dict = Depends(require_auth),
    svc: UnboundService = Depends(_get_service),
) -> dict:
    return await svc.get_status()


@router.get("/version")
async def get_version(
    _: dict = Depends(require_auth),
    svc: UnboundService = Depends(_get_service),
) -> dict:
    return {"version": await svc.get_version()}


@router.post("/reload", status_code=status.HTTP_204_NO_CONTENT)
async def reload(
    _: dict = Depends(require_admin),
    svc: UnboundService = Depends(_get_service),
) -> None:
    await svc.reload_config()


@router.post("/flush")
async def flush_domain(
    body: FlushBody,
    _: dict = Depends(require_admin),
    svc: UnboundService = Depends(_get_service),
) -> dict:
    await svc.flush_domain(body.domain)
    return {"ok": True, "domain": body.domain}


@router.post("/flush-all", status_code=status.HTTP_204_NO_CONTENT)
async def flush_all(
    _: dict = Depends(require_admin),
    svc: UnboundService = Depends(_get_service),
) -> None:
    await svc.flush_all()
