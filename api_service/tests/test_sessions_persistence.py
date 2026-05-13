"""
Testa o path DuckDB do tracking de sessões — sem dependência de Redis real.
Redis off é tratado como "best-effort failure"; DuckDB segue sendo o fallback
autoritativo.
"""

from __future__ import annotations

import time
from unittest.mock import AsyncMock, patch

import pytest


@pytest.fixture
def fresh_db(tmp_path, monkeypatch):
    db = tmp_path / "sessions_test.duckdb"
    monkeypatch.setenv("DB_PATH", str(db))
    # Recarrega settings após mudar env (cache do pydantic-settings)
    from app.core import config

    config.settings = config.Settings()  # noqa: SLF001
    # Patch o db_path direto no conn module
    from app.repositories.duckdb import connection
    connection.settings = config.settings  # type: ignore[attr-defined]

    from app.db import run_migrations
    run_migrations(str(db))
    return db


@pytest.fixture
def no_redis(monkeypatch):
    """Simula Redis indisponível — todo get_redis() levanta exceção."""
    async def _raise(*_a, **_kw):
        raise RuntimeError("redis off (simulado)")

    monkeypatch.setattr("app.services.sessions.get_redis", _raise)


@pytest.mark.asyncio
async def test_track_persists_to_duckdb_when_redis_off(fresh_db, no_redis):
    from app.services import sessions

    now = int(time.time())
    await sessions.track(
        user_id=42,
        token_hash="abc123",
        ip="10.0.0.1",
        user_agent="Mozilla/5.0",
        iat=now,
        exp=now + 3600,
    )

    items = await sessions.list_for_user(42)
    assert len(items) == 1
    assert items[0]["token_hash"] == "abc123"
    assert items[0]["user_id"] == 42
    assert items[0]["ip"] == "10.0.0.1"


@pytest.mark.asyncio
async def test_remove_marks_revoked_in_duckdb(fresh_db, no_redis):
    from app.services import sessions

    now = int(time.time())
    await sessions.track(99, "deadbeef", "1.2.3.4", "ua", now, now + 3600)
    assert len(await sessions.list_for_user(99)) == 1

    await sessions.remove(99, "deadbeef")
    # Após revoga, list_for_user não retorna (filtro `revoked_at IS NULL`)
    assert await sessions.list_for_user(99) == []


@pytest.mark.asyncio
async def test_list_for_user_filters_expired(fresh_db, no_redis):
    from app.services import sessions

    now = int(time.time())
    # Sessão expirada (exp no passado)
    await sessions.track(7, "expired1", "1.1.1.1", "ua", now - 7200, now - 60)
    # Sessão ativa
    await sessions.track(7, "active1", "2.2.2.2", "ua", now, now + 3600)

    items = await sessions.list_for_user(7)
    hashes = {i["token_hash"] for i in items}
    assert "active1" in hashes
    assert "expired1" not in hashes


@pytest.mark.asyncio
async def test_bootstrap_cleans_old_expired_rows(fresh_db, no_redis):
    """bootstrap deleta rows com exp + 30 dias < now."""
    from app.repositories.duckdb.connection import db_execute, db_fetchall
    from app.services import sessions

    now = int(time.time())
    # Insere row muito velha direto no DuckDB
    await db_execute(
        "INSERT INTO auth_sessions (token_hash, user_id, ip, user_agent, iat, exp, login_at, last_seen, revoked_at) "
        "VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)",
        ["ancient", 1, "x", "y", now - 90 * 86400, now - 60 * 86400, now - 90 * 86400, now - 60 * 86400],
    )
    rows_before = await db_fetchall("SELECT COUNT(*) AS c FROM auth_sessions", [])
    assert rows_before[0]["c"] >= 1

    # bootstrap_from_duckdb cleanup (Redis-off mas executa cleanup mesmo assim)
    with patch("app.services.sessions.get_redis", AsyncMock(side_effect=RuntimeError("off"))):
        await sessions.bootstrap_from_duckdb()

    rows_after = await db_fetchall("SELECT COUNT(*) AS c FROM auth_sessions WHERE token_hash = 'ancient'", [])
    assert rows_after[0]["c"] == 0


@pytest.mark.asyncio
async def test_track_updates_last_seen(fresh_db, no_redis):
    """Subsequente track() na mesma sessão atualiza last_seen mas preserva login_at."""
    from app.services import sessions

    t1 = int(time.time())
    await sessions.track(5, "abc", "1.1.1.1", "ua", t1, t1 + 3600)
    items1 = await sessions.list_for_user(5)
    assert items1[0]["login_at"] == t1

    # Avança alguns segundos (mas sem usar sleep — simula time passing)
    # Como o throttle é 30s no Redis path, Redis-off ignora throttle e sempre persiste.
    import asyncio
    await asyncio.sleep(0.05)
    await sessions.track(5, "abc", "1.1.1.1", "ua", t1, t1 + 3600)
    items2 = await sessions.list_for_user(5)
    # login_at preservado (UPSERT só toca ip/ua/last_seen)
    assert items2[0]["login_at"] == t1
    assert items2[0]["last_seen"] >= items1[0]["last_seen"]
