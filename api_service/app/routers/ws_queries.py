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

import asyncio
import json
import queue

import structlog
from fastapi import APIRouter, Query, WebSocket, WebSocketDisconnect, status

from app.core.security import JWTError, decode_token
from app.services import api_tokens as api_tokens_service
from app.services import query_broker

log = structlog.get_logger(__name__)

router = APIRouter(prefix="/api/v1/ws", tags=["websocket"])


async def _validate(token: str) -> dict | None:
    """
    Valida JWT ou API token. Retorna payload ou None.

    WS não tem header Authorization fácil de mandar do browser, então
    aceitamos `?token=<jwt-or-api-token>` no query string. Tentamos JWT
    primeiro (login humano); se falhar, fallback pra api_tokens
    (máquinas, X-Api-Token equivalente via query).
    """
    if not token:
        return None

    try:
        payload = decode_token(token)
        role = payload.get("role", "")
        if role in ("admin", "readonly_admin", "operator", "viewer"):
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
        await websocket.send_text(json.dumps({"type": "hello", "subscribers": query_broker.subscriber_count()}))

        loop = asyncio.get_running_loop()
        # Drena queue continuamente — usa `to_thread(q.get, timeout)` pra não
        # bloquear o event loop quando vazia.
        while True:
            try:
                event = await asyncio.wait_for(
                    loop.run_in_executor(None, q.get, True, 30.0),
                    timeout=35.0,
                )
            except (queue.Empty, asyncio.TimeoutError):
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
