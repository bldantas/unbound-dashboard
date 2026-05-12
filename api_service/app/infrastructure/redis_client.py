"""
Cliente Redis assíncrono compartilhado.

Singleton-ish: uma instância de redis.asyncio.Redis com connection pool,
inicializada lazy no primeiro `get_redis()`. Encerrada no shutdown do
lifespan.

Se o Redis estiver indisponível, as funções helper (`is_user_revoked`,
etc) **devem falhar fechado por padrão** ou ser tolerantes — depende
do caller. Aqui só expomos o client; políticas ficam em
`services/jwt_denylist.py`.
"""

from __future__ import annotations

import asyncio

import redis.asyncio as aioredis
import structlog

from app.core.config import settings

log = structlog.get_logger(__name__)

_redis: aioredis.Redis | None = None
_lock = asyncio.Lock()


async def get_redis() -> aioredis.Redis:
    """Retorna a conexão Redis singleton, inicializando se necessário."""
    global _redis
    if _redis is not None:
        return _redis
    async with _lock:
        if _redis is None:
            _redis = aioredis.from_url(
                str(settings.redis_url),
                encoding="utf-8",
                decode_responses=True,
                socket_connect_timeout=2,
                socket_timeout=2,
            )
            try:
                await _redis.ping()
                log.info("redis.connected", url=str(settings.redis_url))
            except Exception as exc:  # noqa: BLE001
                log.warning("redis.connect_failed", error=str(exc))
                # Não levanta — caller decide fail-open/closed.
    return _redis


async def close_redis() -> None:
    """Fecha a conexão no shutdown. Idempotente."""
    global _redis
    if _redis is not None:
        try:
            await _redis.aclose()
        except Exception:  # noqa: BLE001
            pass
        _redis = None
