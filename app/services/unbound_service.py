"""
UnboundService — lógica de negócio para controle e monitoramento do Unbound.
Cache-first: tenta Redis antes de chamar unbound-control.
"""

from __future__ import annotations

from typing import Any

import structlog

from app.infrastructure.unbound import UnboundAdapter
from app.repositories.redis.stats_cache import StatsCache

log = structlog.get_logger(__name__)


class UnboundService:
    def __init__(
        self,
        adapter: UnboundAdapter | None = None,
        cache: StatsCache | None = None,
    ) -> None:
        self._adapter = adapter or UnboundAdapter()
        self._cache = cache or StatsCache()

    async def get_stats(self, force_refresh: bool = False) -> dict[str, Any]:
        """Retorna stats do unbound-control com cache Redis."""
        if not force_refresh:
            cached = await self._cache.get_unbound_stats()
            if cached is not None:
                return cached

        stats = await self._adapter.stats()
        await self._cache.set_unbound_stats(stats)
        return stats

    async def get_status(self) -> dict[str, str]:
        return await self._adapter.status()

    async def get_version(self) -> str:
        return await self._adapter.version()

    async def reload_config(self) -> None:
        await self._adapter.reload()
        # Invalida o cache após reload
        await self._cache.delete("unbound_stats")

    async def flush_domain(self, domain: str) -> None:
        await self._adapter.flush(domain)
        log.info("unbound.flush_domain", domain=domain)

    async def flush_all(self) -> None:
        await self._adapter.flush_all()
        log.info("unbound.flush_all")

    async def list_local_zones(self) -> list[str]:
        return await self._adapter.list_local_zones()

    async def add_local_zone(self, zone: str, zone_type: str = "redirect") -> None:
        await self._adapter.add_local_zone(zone, zone_type)

    async def remove_local_zone(self, zone: str) -> None:
        await self._adapter.remove_local_zone(zone)
