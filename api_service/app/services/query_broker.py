"""
Pub/Sub em memória pra streaming de queries em tempo real (WebSocket).

LogWatcher publica eventos aqui após parse de cada linha (de uma thread
executor, não do event loop). Subscribers (WebSocket handlers) recebem
por uma `queue.Queue` própria com cap pequeno — `queue.Queue` é
thread-safe (publish vem de thread, consumo via `await to_thread`).

Não persiste: se ninguém conectado, eventos são descartados. Compartilhar
via Redis seria over-engineering pra UX de "live feed".

Drop policy: se subscriber lento, dropa o mais antigo antes de empurrar.
"""

from __future__ import annotations

import queue
from typing import Any

# Cap por subscriber — pequeno pra não acumular se cliente travar
_QUEUE_MAX = 200

_subscribers: set[queue.Queue] = set()


def subscribe() -> queue.Queue:
    q: queue.Queue = queue.Queue(maxsize=_QUEUE_MAX)
    _subscribers.add(q)
    return q


def unsubscribe(q: queue.Queue) -> None:
    _subscribers.discard(q)


def publish(event: dict[str, Any]) -> None:
    """Fan-out best-effort. Subscribers lentos perdem eventos (drop-oldest).

    Pode ser chamado de qualquer thread — `queue.Queue` é thread-safe.
    """
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
