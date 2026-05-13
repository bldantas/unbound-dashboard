"""Testa o runner de migrations: idempotência + detecção de alteração."""

from __future__ import annotations

import os

import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


def test_apply_migrations_creates_schema(tmp_path) -> None:
    import duckdb

    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    applied = run_migrations(str(db))
    assert applied == [1, 2, 3, 4]

    with duckdb.connect(str(db), read_only=True) as conn:
        tables = {
            row[0]
            for row in conn.execute(
                "SELECT table_name FROM information_schema.tables WHERE table_schema='main'"
            ).fetchall()
        }
        # Tabelas do plano + schema_migrations + auth_sessions (V3)
        assert tables == {
            "users",
            "settings",
            "alerts",
            "query_logs",
            "daily_stats",
            "blocklist_domains",
            "auth_sessions",
            "schema_migrations",
        }


def test_apply_migrations_is_idempotent(tmp_path) -> None:
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    first = run_migrations(str(db))
    second = run_migrations(str(db))
    third = run_migrations(str(db))
    assert first == [1, 2, 3, 4]
    assert second == []
    assert third == []
