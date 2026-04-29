"""
Cache Redis com TTL — camada cache-first para métricas.
Evita queries repetidas ao DuckDB para dados que não mudam com frequência.
"""

from __future__ import annotations

import json
from typing import Any, Optional

from app.repositories.redis.connection import get_redis

# TTLs em segundos
TTL_LIVE_STATS = 30
TTL_HISTORY = 300       # 5 minutos
TTL_UNBOUND_STATS = 15
TTL_HEALTH = 10
TTL_BLOCKLIST = 3600    # 1 hora


class StatsCache:
    _prefix = "unbound_dash:"

    def _key(self, name: str) -> str:
        return f"{self._prefix}{name}"

    async def get(self, key: str) -> Optional[Any]:
        redis = await get_redis()
        raw = await redis.get(self._key(key))
        if raw is None:
            return None
        return json.loads(raw)

    async def set(self, key: str, value: Any, ttl: int) -> None:
        redis = await get_redis()
        await redis.setex(self._key(key), ttl, json.dumps(value, default=str))

    async def delete(self, key: str) -> None:
        redis = await get_redis()
        await redis.delete(self._key(key))

    async def delete_pattern(self, pattern: str) -> None:
        redis = await get_redis()
        keys = await redis.keys(self._key(pattern))
        if keys:
            await redis.delete(*keys)

    # Helpers semânticos para as chaves mais usadas

    async def get_live_stats(self) -> Optional[dict]:
        return await self.get("live_stats")

    async def set_live_stats(self, data: dict) -> None:
        await self.set("live_stats", data, TTL_LIVE_STATS)

    async def get_history(self) -> Optional[list]:
        return await self.get("history")

    async def set_history(self, data: list) -> None:
        await self.set("history", data, TTL_HISTORY)

    async def get_unbound_stats(self) -> Optional[dict]:
        return await self.get("unbound_stats")

    async def set_unbound_stats(self, data: dict) -> None:
        await self.set("unbound_stats", data, TTL_UNBOUND_STATS)

    async def get_health(self) -> Optional[dict]:
        return await self.get("health")

    async def set_health(self, data: dict) -> None:
        await self.set("health", data, TTL_HEALTH)

    async def invalidate_all(self) -> None:
        await self.delete_pattern("*")
