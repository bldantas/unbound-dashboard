"""Testes de integração — UserRepository com DuckDB temporário."""
from __future__ import annotations

from datetime import datetime, timedelta, timezone
from pathlib import Path

import bcrypt as _bcrypt
import duckdb as _duckdb
import pytest

from app.db import run_migrations
from app.domain.user import Role
from app.repositories.duckdb import connection as duck_conn
from app.repositories.duckdb.user_repo import UserRepository


def _hash(plain: str) -> str:
    return _bcrypt.hashpw(plain.encode(), _bcrypt.gensalt()).decode()


@pytest.fixture(autouse=True)
def patch_db_path(tmp_path: Path, monkeypatch):
    """Redireciona conexões DuckDB para arquivo temporário isolado por teste."""
    db_path = str(tmp_path / "test.duckdb")
    run_migrations(db_path)
    monkeypatch.setattr(duck_conn, "_write_conn", lambda: _duckdb.connect(db_path))
    monkeypatch.setattr(duck_conn, "_read_conn", lambda: _duckdb.connect(db_path))
    monkeypatch.setattr(duck_conn, "_ensure_data_dir", lambda: db_path)


@pytest.mark.asyncio
async def test_create_and_find_user():
    repo = UserRepository()
    await repo.create("alice", _hash("pass"), Role.ADMIN, "alice@example.com")

    user = await repo.find_by_username("alice")
    assert user is not None
    assert user.username == "alice"
    assert user.role == Role.ADMIN
    assert user.email == "alice@example.com"
    assert user.failed_logins == 0
    assert user.is_active is True


@pytest.mark.asyncio
async def test_find_nonexistent_user_returns_none():
    repo = UserRepository()
    result = await repo.find_by_username("ghost")
    assert result is None


@pytest.mark.asyncio
async def test_update_and_reset_failed_logins():
    repo = UserRepository()
    await repo.create("bob", _hash("pass"))

    user = await repo.find_by_username("bob")
    assert user is not None

    # Naive UTC — compatível com DuckDB TIMESTAMP (sem timezone)
    lock_until = datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(minutes=15)
    await repo.update_failed_logins(user.id, 5, lock_until)

    updated = await repo.find_by_username("bob")
    assert updated is not None
    assert updated.failed_logins == 5
    assert updated.is_locked() is True

    await repo.reset_failed_logins(user.id)
    reset = await repo.find_by_username("bob")
    assert reset is not None
    assert reset.failed_logins == 0
    assert reset.locked_until is None
