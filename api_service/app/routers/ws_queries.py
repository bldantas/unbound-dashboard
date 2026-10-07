"""
WebSocket /api/v1/ws/queries — stream em tempo real de queries parseadas.

LogWatcher publica cada evento parseado em `query_broker`. Este endpoint
subscribe, recebe em queue thread-safe e empurra como JSON pelo socket.

Auth: query param `?token=<jwt>` (WS não tem como mandar header Authorization
de browser facilmente). Valida JWT + capability `dashboard.read`.

Drop policy: subscriber lento perde eventos antigos (queue cap 200, drop-oldest).
Cliente reconnect simples basta — nenhum estado precisa persistir.
"""

from __future__ import annotations

import json
import queue

import structlog
from fastapi import APIRouter, Query, WebSocket, WebSocketDisconnect, status

from app.core.deps import validate_ws_token
from app.core.ws import queue_get
from app.services import query_broker

log = structlog.get_logger(__name__)

router = APIRouter(prefix="/api/v1/ws", tags=["websocket"])


async def _validate(token: str) -> dict | None:
    """JWT ou API token via `?token=` (ver deps.validate_ws_token)."""
    return await validate_ws_token(token)


@router.websocket("/queries")
async def ws_queries(websocket: WebSocket, token: str = Query("")):
    payload = await _validate(token)
    if not payload:
        log.warning("ws_queries.auth_failed", token_len=len(token))
        await websocket.close(code=status.WS_1008_POLICY_VIOLATION, reason="auth")
        return

    await websocket.accept()
    q = query_broker.subscribe()
    log.info("ws_queries.connected", subs=query_broker.subscriber_count())

    try:
        # Envia frame de boas-vindas pra cliente saber que conectou
        await websocket.send_text(
            json.dumps({"type": "hello", "subscribers": query_broker.subscriber_count()})
        )

        while True:
            try:
                event = await queue_get(q, 30.0)
            except queue.Empty:
                # Heartbeat pra cliente não pensar que está zumbi
                try:
                    await websocket.send_text(json.dumps({"type": "ping"}))
                except Exception:
                    break
                continue
            except Exception:
                break

            try:
                await websocket.send_text(json.dumps({"type": "query", **event}))
            except Exception:
                break
    except WebSocketDisconnect:
        pass
    finally:
        query_broker.unsubscribe(q)
        log.info("ws_queries.disconnected", subs=query_broker.subscriber_count())
