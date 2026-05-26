"""
Pub/Sub em memória pra eventos de alertas/notificações (WebSocket).

Espelha o `query_broker`: alerts_service publica aqui sempre que cria/
resolve/dismiss um alert, e o handler WS empurra pros bells conectados.

Sem persistência — eventos são descartados se ninguém conectado. O feed
HTTP (`/notifications/feed`, `/list`) continua sendo a fonte canônica;
o broker só dá UX em tempo real.
"""

from __future__ import annotations

import queue
from typing import Any

_QUEUE_MAX = 200

_subscribers: set[queue.Queue] = set()


def subscribe() -> queue.Queue:
    q: queue.Queue = queue.Queue(maxsize=_QUEUE_MAX)
    _subscribers.add(q)
    return q


def unsubscribe(q: queue.Queue) -> None:
    _subscribers.discard(q)


def publish(event: dict[str, Any]) -> None:
    """Fan-out best-effort. Subscribers lentos perdem eventos (drop-oldest)."""
    for q in list(_subscribers):
        try:
            q.put_nowait(event)
        except queue.Full:
            try:
                q.get_nowait()
                q.put_nowait(event)
            except (queue.Empty, queue.Full):
                pass


def subscriber_count() -> int:
    return len(_subscribers)
