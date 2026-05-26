"""Pub/sub do alerts_broker (usado pelo WS /api/v1/ws/notifications)."""

from __future__ import annotations

import queue

import pytest


def _reset_subscribers(mod) -> None:
    """Limpa o set global entre testes — broker é module-level state."""
    mod._subscribers.clear()


def test_subscribe_returns_queue_and_counts():
    from app.services import alerts_broker

    _reset_subscribers(alerts_broker)
    q = alerts_broker.subscribe()
    assert isinstance(q, queue.Queue)
    assert alerts_broker.subscriber_count() == 1
    alerts_broker.unsubscribe(q)
    assert alerts_broker.subscriber_count() == 0


def test_publish_fans_out_to_all_subscribers():
    from app.services import alerts_broker

    _reset_subscribers(alerts_broker)
    q1 = alerts_broker.subscribe()
    q2 = alerts_broker.subscribe()

    alerts_broker.publish({"event": "created", "type": "x"})

    assert q1.get_nowait() == {"event": "created", "type": "x"}
    assert q2.get_nowait() == {"event": "created", "type": "x"}

    alerts_broker.unsubscribe(q1)
    alerts_broker.unsubscribe(q2)


def test_publish_drop_oldest_when_queue_full():
    """Subscriber lento: queue cheia, drop-oldest."""
    from app.services import alerts_broker

    _reset_subscribers(alerts_broker)
    q = alerts_broker.subscribe()

    # Enche com mais que _QUEUE_MAX (200)
    for i in range(250):
        alerts_broker.publish({"event": "created", "n": i})

    items = []
    while True:
        try:
            items.append(q.get_nowait())
        except queue.Empty:
            break

    # Mantém só os últimos 200 (cap)
    assert len(items) == 200
    # Os primeiros foram dropados — o item 0 sumiu
    ns = [item["n"] for item in items]
    assert 0 not in ns
    assert 249 in ns

    alerts_broker.unsubscribe(q)


def test_unsubscribe_is_idempotent():
    from app.services import alerts_broker

    _reset_subscribers(alerts_broker)
    q = alerts_broker.subscribe()
    alerts_broker.unsubscribe(q)
    alerts_broker.unsubscribe(q)  # não deve crashar
    assert alerts_broker.subscriber_count() == 0


def test_publish_with_zero_subscribers_is_silent():
    from app.services import alerts_broker

    _reset_subscribers(alerts_broker)
    # Sem subscribers — não deve dar erro
    alerts_broker.publish({"event": "created"})
    assert alerts_broker.subscriber_count() == 0
