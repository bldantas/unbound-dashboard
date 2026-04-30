"""Source Balance endpoints — gerencia múltiplos upstreams Unbound."""

from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel

from app.core.deps import require_admin, require_auth
from app.services.source_balance_service import SourceBalanceManager

router = APIRouter(prefix="/api/v2/balance", tags=["source-balance"])


def _get_svc() -> SourceBalanceManager:
    return SourceBalanceManager()


class AddUpstreamBody(BaseModel):
    host: str
    port: int = 8953
    label: str = ""


class SetEnabledBody(BaseModel):
    host: str
    port: int = 8953
    enabled: bool


@router.get("/upstreams")
async def list_upstreams(
    _: dict = Depends(require_auth),
    svc: SourceBalanceManager = Depends(_get_svc),
) -> list[dict]:
    return [u.to_dict() for u in await svc.list_upstreams()]


@router.post("/upstreams", status_code=status.HTTP_201_CREATED)
async def add_upstream(
    body: AddUpstreamBody,
    _: dict = Depends(require_admin),
    svc: SourceBalanceManager = Depends(_get_svc),
) -> dict:
    try:
        await svc.add_upstream(body.host, body.port, body.label)
    except ValueError as e:
        raise HTTPException(status_code=409, detail=str(e))
    return {"ok": True}


@router.delete("/upstreams")
async def remove_upstream(
    host: str,
    port: int = 8953,
    _: dict = Depends(require_admin),
    svc: SourceBalanceManager = Depends(_get_svc),
) -> dict:
    await svc.remove_upstream(host, port)
    return {"ok": True}


@router.put("/upstreams/enabled")
async def set_enabled(
    body: SetEnabledBody,
    _: dict = Depends(require_admin),
    svc: SourceBalanceManager = Depends(_get_svc),
) -> dict:
    await svc.set_enabled(body.host, body.port, body.enabled)
    return {"ok": True}


@router.get("/health")
async def health_check(
    _: dict = Depends(require_auth),
    svc: SourceBalanceManager = Depends(_get_svc),
) -> list[dict]:
    statuses = await svc.check_all()
    return [
        {
            "host": s.host,
            "port": s.port,
            "healthy": s.healthy,
            "latency_ms": s.latency_ms,
            "error": s.error,
        }
        for s in statuses
    ]


@router.get("/stats")
async def aggregate_stats(
    _: dict = Depends(require_auth),
    svc: SourceBalanceManager = Depends(_get_svc),
) -> dict:
    return await svc.aggregate_stats()
