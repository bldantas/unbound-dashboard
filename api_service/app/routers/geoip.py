"""Endpoints /api/v1/geoip — lookup IP→país + agregação top países."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability
from app.services import geoip_service

router = APIRouter(prefix="/api/v1/geoip", tags=["geoip"])


@router.get("/lookup")
async def lookup(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    ip: str = Query(..., min_length=1, max_length=64),
) -> dict:
    return await geoip_service.lookup(ip)


@router.post("/lookup-bulk")
async def lookup_bulk(
    body: dict,
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    ips = body.get("ips") or []
    if not isinstance(ips, list):
        return {"results": {}}
    return {"results": await geoip_service.lookup_many([str(x) for x in ips])}


@router.get("/top-countries")
async def top_countries(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    hours: int = Query(24, ge=1, le=720),
    limit: int = Query(20, ge=1, le=100),
) -> dict:
    """Top países dos clientes BLOCKED (compat — mantido pra /threats.php)."""
    rows = await geoip_service.top_countries_blocked(hours=hours, limit=limit)
    return {"hours": hours, "countries": rows}


@router.get("/distribution")
async def distribution(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    hours: int = Query(24, ge=1, le=720),
    limit: int = Query(50, ge=1, le=250),
    action: str = Query("", max_length=30),
) -> dict:
    """
    Distribuição global de queries por país.

    action vazio = todas; 'blocked'/'resolved'/'cached'/'nxdomain_upstream' filtra.
    """
    rows = await geoip_service.top_countries(
        hours=hours, limit=limit, action=action or None
    )
    return {"hours": hours, "action": action or "all", "countries": rows}


@router.get("/top-asns")
async def top_asns(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    hours: int = Query(24, ge=1, le=720),
    limit: int = Query(20, ge=1, le=100),
    action: str = Query("blocked", max_length=30),
) -> dict:
    """Top ASNs (provedores/redes) por hits. action='' = todas."""
    rows = await geoip_service.top_asns(
        hours=hours, limit=limit, action=action or None
    )
    return {"hours": hours, "action": action or "all", "asns": rows}
