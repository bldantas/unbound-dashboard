"""Testa o runner de migrations: idempotência + detecção de alteração."""

from __future__ import annotations

import os

import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


EXPECTED_VERSIONS = list(range(1, 16))  # V1..V15 — adicionar aqui a cada migration nova

CORE_TABLES = {
    "users", "settings", "alerts", "query_logs", "daily_stats",
    "blocklist_domains", "auth_sessions", "update_audit", "api_tokens",
    "managed_hosts", "host_poll_history", "schema_migrations",
    # V9 blocklist_multisource, V11 client_policies, V12 hourly_stats,
    # V13 geo_blocking, V14 anomaly_whitelist, V15 admin_audit
    "blocklist_sources", "client_policies", "hourly_stats",
    "geo_blocks", "anomaly_whitelist", "admin_audit",
}


def test_apply_migrations_creates_schema(tmp_path) -> None:
    import duckdb

    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    applied = run_migrations(str(db))
    assert applied == EXPECTED_VERSIONS

    with duckdb.connect(str(db), read_only=True) as conn:
        tables = {
            row[0]
            for row in conn.execute(
                "SELECT table_name FROM information_schema.tables WHERE table_schema='main'"
            ).fetchall()
        }
        # Subset — algumas migrations criam várias tabelas auxiliares; checamos
        # que pelo menos os core esperados estão presentes.
        missing = CORE_TABLES - tables
        assert not missing, f"Tabelas esperadas faltando: {missing}"


def test_apply_migrations_is_idempotent(tmp_path) -> None:
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    first = run_migrations(str(db))
    second = run_migrations(str(db))
    third = run_migrations(str(db))
    assert first == EXPECTED_VERSIONS
    assert second == []
    assert third == []
