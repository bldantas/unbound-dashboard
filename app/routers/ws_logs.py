"""WebSocket /ws/live-log — fan-out de logs em tempo real via Redis pub/sub."""

from __future__ import annotations

import asyncio

from fastapi import APIRouter, WebSocket, WebSocketDisconnect

from app.repositories.redis.connection import get_redis

router = APIRouter(tags=["websocket"])


@router.websocket("/ws/live-log")
async def live_log(websocket: WebSocket) -> None:
    """
    Subscreve no canal Redis 'live:logs' e encaminha cada mensagem
    para o cliente WebSocket. O LogWatcher publica nesse canal.
    """
    await websocket.accept()
    redis = await get_redis()
    pubsub = redis.pubsub()
    await pubsub.subscribe("live:logs")

    try:
        async for message in pubsub.listen():
            if message["type"] == "message":
                data = message["data"]
                await websocket.send_text(data if isinstance(data, str) else data.decode())
    except WebSocketDisconnect:
        pass
    except asyncio.CancelledError:
        pass
    finally:
        try:
            await pubsub.unsubscribe("live:logs")
        except Exception:
            pass
