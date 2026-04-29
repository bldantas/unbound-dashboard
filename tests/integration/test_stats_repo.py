"""Testes de integração: StatsRepository."""
from __future__ import annotations

import time
from datetime import date
from pathlib import Path

import pytest

from app.core.config import settings as app_settings
from app.db import run_migrations
from app.domain.stats import DailyStat, QueryLog


@pytest.fixture(autouse=True)
def _patch_db_path(tmp_path: Path, monkeypatch):
    db_path = str(tmp_path / "test.duckdb")
    monkeypatch.setattr(app_settings, "db_path", db_path)
    run_migrations(db_path)


def _log(action: str, domain: str = "example.com") -> QueryLog:
    return QueryLog(
        timestamp=int(time.time()),
        client_ip="10.0.0.1",
        domain=domain,
        query_type="A",
        action=action,
    )


@pytest.mark.asyncio
async def test_bulk_insert_and_live_stats() -> None:
    from app.repositories.duckdb.stats_repo import StatsRepository

    repo = StatsRepository()
    logs = [
        _log("resolved"),
        _log("resolved"),
        _log("blocked"),
        _log("cached"),
    ]
    await repo.bulk_insert_logs(logs)

    stats = await repo.get_live_stats(window_hours=1)
    assert stats.total == 4
    assert stats.blocked == 1
    assert stats.resolved == 2
    assert stats.cache_hits == 1
    assert stats.block_rate == 25.0


@pytest.mark.asyncio
async def test_upsert_daily_stat() -> None:
    from app.repositories.duckdb.stats_repo import StatsRepository

    repo = StatsRepository()
    today = date.today()
    stat = DailyStat(date=today, total=100, blocked=10, resolved=80, cache_hits=10)
    await repo.upsert_daily_stat(stat)

    history = await repo.get_daily_stats(days=7)
    assert len(history) == 1
    assert history[0].total == 100

    # Upsert deve atualizar
    stat2 = DailyStat(date=today, total=200, blocked=20, resolved=160, cache_hits=20)
    await repo.upsert_daily_stat(stat2)
    history2 = await repo.get_daily_stats(days=7)
    assert len(history2) == 1
    assert history2[0].total == 200


@pytest.mark.asyncio
async def test_get_recent_logs_filter() -> None:
    from app.repositories.duckdb.stats_repo import StatsRepository

    repo = StatsRepository()
    logs = [
        _log("blocked", "ads.evil.com"),
        _log("resolved", "google.com"),
        _log("resolved", "github.com"),
    ]
    await repo.bulk_insert_logs(logs)

    blocked_only = await repo.get_recent_logs(action="blocked")
    assert len(blocked_only) == 1
    assert blocked_only[0].domain == "ads.evil.com"

    google = await repo.get_recent_logs(domain="google")
    assert len(google) == 1
    assert google[0].domain == "google.com"
