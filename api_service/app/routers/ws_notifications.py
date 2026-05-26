"""
WebSocket /api/v1/ws/notifications — push em tempo real dos eventos de alert.

Espelha o `ws_queries.py`. Subscribe no `alerts_broker`, recebe eventos
{event: created|resolved|dismissed|dismissed_all, ...} e empurra como JSON
pelo socket. Bell faz fallback pra polling 1x/min caso a conexão caia.

Auth: query param `?token=<jwt-or-api-token>` (mesmo padrão de ws_queries).
"""

from __future__ import annotations

import asyncio
import json
import queue

import structlog
from fastapi import APIRouter, Query, WebSocket, WebSocketDisconnect, status

from app.core.security import JWTError, decode_token
from app.services import alerts_broker
from app.services import api_tokens as api_tokens_service

log = structlog.get_logger(__name__)

router = APIRouter(prefix="/api/v1/ws", tags=["websocket"])


async def _validate(token: str) -> dict | None:
    if not token:
        return None
    try:
        payload = decode_token(token)
        if payload.get("role", "") in ("admin", "readonly_admin", "operator", "viewer"):
            return payload
    except JWTError:
        pass
    try:
        info = await api_tokens_service.verify(token)
        if info:
            return {"sub": "api_token", "role": "admin", "token_id": info.get("id")}
    except Exception:  # noqa: BLE001
        pass
    return None


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
        loop = asyncio.get_running_loop()
        while True:
            try:
                event = await asyncio.wait_for(
                    loop.run_in_executor(None, q.get, True, 30.0),
                    timeout=35.0,
                )
            except (queue.Empty, asyncio.TimeoutError):
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
