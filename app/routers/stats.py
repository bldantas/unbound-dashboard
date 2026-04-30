from __future__ import annotations

from typing import Annotated, Optional

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_auth
from app.domain.stats import DailyStat, LiveStats, QueryLog
from app.repositories.duckdb.stats_repo import StatsRepository

router = APIRouter(prefix="/api/v2/stats", tags=["stats"])


def _get_repo() -> StatsRepository:
    return StatsRepository()


@router.get("/live")
async def get_live_stats(
    hours: Annotated[int, Query(ge=1, le=168)] = 24,
    _: dict = Depends(require_auth),
    repo: StatsRepository = Depends(_get_repo),
) -> dict:
    stats = await repo.get_live_stats(window_hours=hours)
    return {
        "window_hours": stats.window_hours,
        "total": stats.total,
        "blocked": stats.blocked,
        "resolved": stats.resolved,
        "cache_hits": stats.cache_hits,
        "block_rate": stats.block_rate,
        "top_domains": stats.top_domains,
        "top_clients": stats.top_clients,
    }


@router.get("/history")
async def get_history(
    days: Annotated[int, Query(ge=1, le=365)] = 30,
    _: dict = Depends(require_auth),
    repo: StatsRepository = Depends(_get_repo),
) -> list[dict]:
    stats = await repo.get_daily_stats(days=days)
    return [
        {
            "date": str(s.date),
            "total": s.total,
            "blocked": s.blocked,
            "resolved": s.resolved,
            "cache_hits": s.cache_hits,
            "block_rate": s.block_rate,
        }
        for s in stats
    ]


@router.get("/logs")
async def get_logs(
    limit: Annotated[int, Query(ge=1, le=1000)] = 100,
    action: Optional[str] = None,
    domain: Optional[str] = None,
    _: dict = Depends(require_auth),
    repo: StatsRepository = Depends(_get_repo),
) -> list[dict]:
    logs = await repo.get_recent_logs(limit=limit, action=action, domain=domain)
    return [
        {
            "timestamp": l.timestamp,
            "client_ip": l.client_ip,
            "domain": l.domain,
            "query_type": l.query_type,
            "action": l.action,
        }
        for l in logs
    ]
