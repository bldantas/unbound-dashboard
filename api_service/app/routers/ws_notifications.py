"""
WebSocket /api/v1/ws/notifications — push em tempo real dos eventos de alert.

Espelha o `ws_queries.py`. Subscribe no `alerts_broker`, recebe eventos
{event: created|resolved|dismissed|dismissed_all, ...} e empurra como JSON
pelo socket. Bell faz fallback pra polling 1x/min caso a conexão caia.

Auth: query param `?token=<jwt-or-api-token>` (mesmo padrão de ws_queries).
"""

from __future__ import annotations

import json
import queue

import structlog
from fastapi import APIRouter, Query, WebSocket, WebSocketDisconnect, status

from app.core.deps import validate_ws_token
from app.core.ws import queue_get
from app.services import alerts_broker

log = structlog.get_logger(__name__)

router = APIRouter(prefix="/api/v1/ws", tags=["websocket"])


async def _validate(token: str) -> dict | None:
    """JWT ou API token via `?token=` (ver deps.validate_ws_token)."""
    return await validate_ws_token(token)


@router.websocket("/notifications")
async def ws_notifications(websocket: WebSocket, token: str = Query("")):
    payload = await _validate(token)
    if not payload:
        log.warning("ws_notifications.auth_failed", token_len=len(token))
        await websocket.close(code=status.WS_1008_POLICY_VIOLATION, reason="auth")
        return

    await websocket.accept()
    q = alerts_broker.subscribe()
    log.info("ws_notifications.connected", subs=alerts_broker.subscriber_count())

    try:
        await websocket.send_text(json.dumps({"type": "hello", "subscribers": alerts_broker.subscriber_count()}))
        while True:
            try:
                event = await queue_get(q, 30.0)
            except queue.Empty:
                try:
                    await websocket.send_text(json.dumps({"type": "ping"}))
                except Exception:
                    break
                continue
            except Exception:
                break
            try:
                await websocket.send_text(json.dumps({"type": "alert", **event}))
            except Exception:
                break
    except WebSocketDisconnect:
        pass
    finally:
        alerts_broker.unsubscribe(q)
        log.info("ws_notifications.disconnected", subs=alerts_broker.subscriber_count())
