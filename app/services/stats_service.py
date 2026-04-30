"""
StatsService — métricas com cache-first.
Agrega dados do DuckDB + unbound-control + psutil em um snapshot único.
"""

from __future__ import annotations

from typing import Any

from app.infrastructure import system as sys_info
from app.infrastructure.unbound import UnboundAdapter
from app.repositories.duckdb.stats_repo import StatsRepository
from app.repositories.redis.stats_cache import StatsCache


class StatsService:
    def __init__(
        self,
        stats_repo: StatsRepository | None = None,
        unbound: UnboundAdapter | None = None,
        cache: StatsCache | None = None,
    ) -> None:
        self._repo = stats_repo or StatsRepository()
        self._unbound = unbound or UnboundAdapter()
        self._cache = cache or StatsCache()

    async def get_live(self, hours: int = 24, force: bool = False) -> dict[str, Any]:
        """Stats ao vivo com cache Redis."""
        if not force:
            cached = await self._cache.get_live_stats()
            if cached is not None:
                return cached

        live = await self._repo.get_live_stats(window_hours=hours)
        result = {
            "window_hours": live.window_hours,
            "total": live.total,
            "blocked": live.blocked,
            "resolved": live.resolved,
            "cache_hits": live.cache_hits,
            "block_rate": live.block_rate,
            "top_domains": live.top_domains,
            "top_clients": live.top_clients,
        }
        await self._cache.set_live_stats(result)
        return result

    async def get_history(self, days: int = 30, force: bool = False) -> list[dict]:
        if not force:
            cached = await self._cache.get_history()
            if cached is not None:
                return cached

        stats = await self._repo.get_daily_stats(days=days)
        result = [
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
        await self._cache.set_history(result)
        return result

    async def get_health_snapshot(self, force: bool = False) -> dict[str, Any]:
        """Snapshot completo: sistema + Unbound + DuckDB stats."""
        if not force:
            cached = await self._cache.get_health()
            if cached is not None:
                return cached

        system, unbound_status, unbound_stats = await __import__("asyncio").gather(
            sys_info.full_snapshot(),
            self._unbound.status(),
            self._unbound.stats(),
            return_exceptions=True,
        )

        result = {
            "system": system if not isinstance(system, Exception) else {},
            "unbound": {
                "status": unbound_status if not isinstance(unbound_status, Exception) else {},
                "stats": unbound_stats if not isinstance(unbound_stats, Exception) else {},
            },
        }
        await self._cache.set_health(result)
        return result
