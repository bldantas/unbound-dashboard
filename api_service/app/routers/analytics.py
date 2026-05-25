"""Endpoints analíticos /api/v1/analytics (B.3).

Todos os endpoints aceitam ?window=1h|24h|7d|30d. Default 24h.
Capability `dashboard.read` (qualquer usuário autenticado) — sem dados sensíveis.
"""

from __future__ import annotations

from typing import Annotated, Literal

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability
from app.repositories.duckdb import analytics_repo

router = APIRouter(prefix="/api/v1/analytics", tags=["analytics"])

WindowParam = Literal["1h", "24h", "7d", "30d"]


@router.get("/summary")
async def get_summary(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    return await analytics_repo.summary(window)


@router.get("/timeseries")
async def get_timeseries(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    points = await analytics_repo.timeseries(window)
    return {
        "window": window,
        "bucket_seconds": analytics_repo.bucket_seconds(window),
        "points": points,
    }


@router.get("/by-query-type")
async def get_by_query_type(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    return {"window": window, "items": await analytics_repo.by_query_type(window)}


@router.get("/top-domains")
async def get_top_domains(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    limit: int = Query(20, ge=1, le=200),
    action: Literal["blocked", "resolved", "cached", "nxdomain_upstream"] | None = Query(None),
) -> dict:
    return {
        "window": window,
        "limit": limit,
        "action": action,
        "items": await analytics_repo.top_domains(window, limit=limit, action=action),
    }


@router.get("/top-clients")
async def get_top_clients(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    limit: int = Query(20, ge=1, le=200),
) -> dict:
    return {
        "window": window,
        "limit": limit,
        "items": await analytics_repo.top_clients(window, limit=limit),
    }


@router.get("/action-breakdown")
async def get_action_breakdown(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    return {"window": window, "items": await analytics_repo.action_breakdown(window)}
