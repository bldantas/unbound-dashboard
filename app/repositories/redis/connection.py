"""Conexão Redis — pub/sub para WebSocket live-log e cache de métricas."""

from __future__ import annotations

import redis.asyncio as aioredis

from app.core.config import settings

_client: aioredis.Redis | None = None


async def get_redis() -> aioredis.Redis:
    global _client
    if _client is None:
        _client = aioredis.from_url(
            str(settings.redis_url),
            decode_responses=True,
        )
    return _client


async def close_redis() -> None:
    global _client
    if _client is not None:
        await _client.aclose()
        _client = None
