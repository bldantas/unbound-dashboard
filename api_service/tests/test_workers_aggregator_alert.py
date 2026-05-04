"""Testes integrados de StatsAggregator e AlertChecker contra DuckDB temporário."""

from __future__ import annotations

import os
import time
from unittest.mock import patch

import duckdb
import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


@pytest.fixture()
def db_path(tmp_path):
    """DuckDB temporário com schema aplicado."""
    from app.db import run_migrations

    p = tmp_path / "test.duckdb"
    run_migrations(str(p))
    return str(p)


def _seed_query_logs(db: str, rows: list[tuple]) -> None:
    with duckdb.connect(db) as conn:
        conn.executemany(
            "INSERT INTO query_logs (id, timestamp, client_ip, domain, query_type, action) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            rows,
        )


# ---------------------------------------------------------------------------
# StatsAggregator
# ---------------------------------------------------------------------------


@pytest.mark.asyncio
async def test_aggregator_upserts_today_total_and_blocked(db_path) -> None:
    from app.core import config
    from app.workers.stats_aggregator import StatsAggregator

    now = int(time.time())
    _seed_query_logs(
        db_path,
        [
            (1, now - 100, "10.0.0.1", "ads.com", "A", "blocked"),
            (2, now - 200, "10.0.0.1", "ads.com", "A", "blocked"),
            (3, now - 300, "10.0.0.2", "safe.com", "A", "resolved"),
            (4, now - 86400 * 2, "10.0.0.3", "old.com", "A", "blocked"),  # 2 dias atrás
        ],
    )

    with patch.object(config.settings, "db_path", db_path):
        agg = StatsAggregator()
        await agg._aggregate_today()

    with duckdb.connect(db_path, read_only=True) as conn:
        row = conn.execute(
            "SELECT total_queries, blocked_count, cache_hits FROM daily_stats"
        ).fetchone()
        assert row[0] == 3  # 3 hoje
        assert row[1] == 2  # 2 blocked hoje
        assert row[2] == 0  # cache_hits intocado pelo worker


@pytest.mark.asyncio
async def test_aggregator_idempotent_upsert(db_path) -> None:
    """Roda 2x — não duplica linha, atualiza valores."""
    from app.core import config
    from app.workers.stats_aggregator import StatsAggregator

    now = int(time.time())
    _seed_query_logs(db_path, [(1, now - 100, "10.0.0.1", "x.com", "A", "blocked")])

    with patch.object(config.settings, "db_path", db_path):
        agg = StatsAggregator()
        await agg._aggregate_today()
        # Adiciona mais uma row e re-roda
        _seed_query_logs(db_path, [(2, now - 50, "10.0.0.1", "y.com", "A", "resolved")])
        await agg._aggregate_today()

    with duckdb.connect(db_path, read_only=True) as conn:
        rows = conn.execute("SELECT total_queries, blocked_count FROM daily_stats").fetchall()
        assert len(rows) == 1
        assert rows[0] == (2, 1)


# ---------------------------------------------------------------------------
# AlertChecker
# ---------------------------------------------------------------------------


@pytest.mark.asyncio
async def test_alert_checker_creates_no_queries_when_idle(db_path) -> None:
    """Sem queries recentes → cria alerta no_queries."""
    from app.core import config
    from app.workers.alert_checker import AlertChecker

    with patch.object(config.settings, "db_path", db_path):
        # nenhuma query → deve criar alerta
        checker = AlertChecker()
        await checker._check_no_queries()

    with duckdb.connect(db_path, read_only=True) as conn:
        rows = conn.execute(
            "SELECT type, severity, message FROM alerts WHERE type = 'no_queries'"
        ).fetchall()
        assert len(rows) == 1
        assert rows[0][0] == "no_queries"
        assert rows[0][1] == "critical"


@pytest.mark.asyncio
async def test_alert_checker_dedupes(db_path) -> None:
    """Roda 2x consecutivas sem queries → cria só 1 alerta."""
    from app.core import config
    from app.workers.alert_checker import AlertChecker

    with patch.object(config.settings, "db_path", db_path):
        checker = AlertChecker()
        await checker._check_no_queries()
        await checker._check_no_queries()

    with duckdb.connect(db_path, read_only=True) as conn:
        n = conn.execute("SELECT COUNT(*) FROM alerts WHERE type = 'no_queries'").fetchone()[0]
        assert n == 1


@pytest.mark.asyncio
async def test_alert_checker_auto_resolves_when_traffic_returns(db_path) -> None:
    """Cria alerta. Depois tráfego retorna → resolve automaticamente."""
    from app.core import config
    from app.workers.alert_checker import AlertChecker

    with patch.object(config.settings, "db_path", db_path):
        checker = AlertChecker()
        # 1) cria alerta (sem queries)
        await checker._check_no_queries()
        # 2) injeta query recente
        _seed_query_logs(
            db_path, [(99, int(time.time()) - 30, "10.0.0.1", "back.com", "A", "resolved")]
        )
        # 3) check de novo → deve auto-resolver
        await checker._check_no_queries()

    with duckdb.connect(db_path, read_only=True) as conn:
        row = conn.execute(
            "SELECT resolved_at, is_dismissed FROM alerts WHERE type='no_queries'"
        ).fetchone()
        assert row[0] is not None  # resolved_at preenchido
        assert row[1] is True  # is_dismissed = true
