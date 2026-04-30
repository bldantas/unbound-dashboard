"""Testes de integração: AlertRepository e SettingsRepository."""
from __future__ import annotations

import os
import pytest
import duckdb
from pathlib import Path

from app.core.config import settings as app_settings
from app.db import run_migrations
from app.domain.alert import AlertCreate, Severity


# -- Fixture: banco DuckDB isolado com schema aplicado --

@pytest.fixture(autouse=True)
def _patch_db_path(tmp_path: Path, monkeypatch):
    """Redireciona todas as operações DuckDB para o banco temporário do teste."""
    db_path = str(tmp_path / "test.duckdb")
    monkeypatch.setattr(app_settings, "db_path", db_path)
    run_migrations(db_path)


# ------------------------------------------------------------------ #
# AlertRepository                                                     #
# ------------------------------------------------------------------ #

@pytest.mark.asyncio
async def test_alert_create_and_list() -> None:
    from app.repositories.duckdb.alert_repo import AlertRepository

    repo = AlertRepository()
    alert_id = await repo.create(
        AlertCreate(type="test_alert", message="Teste", severity=Severity.WARNING)
    )
    assert isinstance(alert_id, int)

    alerts = await repo.list_all()
    assert len(alerts) == 1
    assert alerts[0].type == "test_alert"
    assert alerts[0].severity == Severity.WARNING
    assert alerts[0].is_read is False


@pytest.mark.asyncio
async def test_alert_mark_read() -> None:
    from app.repositories.duckdb.alert_repo import AlertRepository

    repo = AlertRepository()
    aid = await repo.create(AlertCreate(type="x", message="m", severity=Severity.INFO))

    assert await repo.count_unread() == 1
    await repo.mark_read(aid)
    assert await repo.count_unread() == 0


@pytest.mark.asyncio
async def test_alert_mark_all_read() -> None:
    from app.repositories.duckdb.alert_repo import AlertRepository

    repo = AlertRepository()
    for i in range(3):
        await repo.create(AlertCreate(type=f"t{i}", message="m", severity=Severity.INFO))

    assert await repo.count_unread() == 3
    await repo.mark_all_read()
    assert await repo.count_unread() == 0


# ------------------------------------------------------------------ #
# SettingsRepository                                                  #
# ------------------------------------------------------------------ #

@pytest.mark.asyncio
async def test_settings_set_and_get() -> None:
    from app.repositories.duckdb.settings_repo import SettingsRepository

    repo = SettingsRepository()
    await repo.set("theme", "dark")
    value = await repo.get("theme")
    assert value == "dark"


@pytest.mark.asyncio
async def test_settings_overwrite() -> None:
    from app.repositories.duckdb.settings_repo import SettingsRepository

    repo = SettingsRepository()
    await repo.set("key", "v1")
    await repo.set("key", "v2")
    assert await repo.get("key") == "v2"


@pytest.mark.asyncio
async def test_settings_get_missing_returns_none() -> None:
    from app.repositories.duckdb.settings_repo import SettingsRepository

    repo = SettingsRepository()
    result = await repo.get("nonexistent")
    assert result is None


@pytest.mark.asyncio
async def test_settings_all() -> None:
    from app.repositories.duckdb.settings_repo import SettingsRepository

    repo = SettingsRepository()
    await repo.bulk_set({"a": "1", "b": "2", "c": "3"})
    all_settings = await repo.all()
    assert all_settings == {"a": "1", "b": "2", "c": "3"}
