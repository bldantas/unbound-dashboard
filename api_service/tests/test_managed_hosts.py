"""Testes do services/managed_hosts.py — CRUD + poll (com httpx mockado)."""

from __future__ import annotations

from unittest.mock import AsyncMock, patch

import pytest


@pytest.fixture
def fresh_db(tmp_path, monkeypatch):
    db = tmp_path / "hosts_test.duckdb"
    monkeypatch.setenv("DB_PATH", str(db))
    from app.core import config

    config.settings = config.Settings()  # noqa: SLF001
    from app.repositories.duckdb import connection
    connection.settings = config.settings  # type: ignore[attr-defined]

    from app.db import run_migrations
    run_migrations(str(db))
    return db


@pytest.mark.asyncio
async def test_create_then_list(fresh_db):
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="agent-1",
        base_url="https://dns1.example.com",
        api_token="x" * 50,
        notes="primary recursor",
        added_by=1,
    )
    assert h_id > 0

    items = await managed_hosts.list_all()
    assert len(items) == 1
    h = items[0]
    assert h["label"] == "agent-1"
    assert h["base_url"] == "https://dns1.example.com"
    assert h["notes"] == "primary recursor"
    assert "api_token" not in h  # NÃO expõe pra UI


@pytest.mark.asyncio
async def test_create_duplicate_base_url(fresh_db):
    from app.services import managed_hosts

    await managed_hosts.create(
        label="a", base_url="https://x.com", api_token="t" * 30, notes=None, added_by=None
    )
    with pytest.raises(managed_hosts.DuplicateHost):
        await managed_hosts.create(
            label="b", base_url="https://x.com", api_token="t" * 30, notes=None, added_by=None
        )


@pytest.mark.asyncio
async def test_strip_trailing_slash(fresh_db):
    from app.services import managed_hosts

    await managed_hosts.create(
        label="a", base_url="https://x.com/", api_token="t" * 30, notes=None, added_by=None
    )
    items = await managed_hosts.list_all()
    assert items[0]["base_url"] == "https://x.com"  # sem trailing /


@pytest.mark.asyncio
async def test_update_label_and_notes(fresh_db):
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="old", base_url="https://x.com", api_token="t" * 30, notes="old", added_by=None
    )
    ok = await managed_hosts.update(h_id, label="new", notes="new notes")
    assert ok is True

    items = await managed_hosts.list_all()
    assert items[0]["label"] == "new"
    assert items[0]["notes"] == "new notes"


@pytest.mark.asyncio
async def test_update_preserve_token_when_empty(fresh_db):
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="a", base_url="https://x.com", api_token="ORIGINAL_TOKEN_VALUE", notes=None, added_by=None
    )
    # api_token="" deve preservar o original
    await managed_hosts.update(h_id, api_token="")
    h = await managed_hosts.get(h_id)
    assert h["api_token"] == "ORIGINAL_TOKEN_VALUE"

    # api_token novo sobrescreve
    await managed_hosts.update(h_id, api_token="NEW_TOKEN_VALUE")
    h = await managed_hosts.get(h_id)
    assert h["api_token"] == "NEW_TOKEN_VALUE"


@pytest.mark.asyncio
async def test_delete(fresh_db):
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="a", base_url="https://x.com", api_token="t" * 30, notes=None, added_by=None
    )
    ok = await managed_hosts.delete(h_id)
    assert ok is True
    items = await managed_hosts.list_all()
    assert items == []

    # Re-delete retorna False
    ok = await managed_hosts.delete(h_id)
    assert ok is False


@pytest.mark.asyncio
async def test_poll_host_ok(fresh_db, monkeypatch):
    """Mock httpx pra retornar 200 + JSON válido."""
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="a", base_url="https://x.com", api_token="t" * 30, notes=None, added_by=None
    )

    class MockResp:
        status_code = 200
        def json(self):
            return {"version": "2.21.1", "alerts_active": 0}
        text = ""

    class MockClient:
        def __init__(self, *a, **kw): pass
        async def __aenter__(self): return self
        async def __aexit__(self, *a): pass
        async def get(self, url, headers=None):
            assert "X-Api-Token" in headers
            assert headers["X-Api-Token"] == "t" * 30
            return MockResp()

    monkeypatch.setattr("app.services.managed_hosts.httpx.AsyncClient", MockClient)

    result = await managed_hosts.poll_host(h_id)
    assert result["status"] == "ok"
    assert result["payload"]["version"] == "2.21.1"


@pytest.mark.asyncio
async def test_poll_host_auth_failed(fresh_db, monkeypatch):
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="a", base_url="https://x.com", api_token="t" * 30, notes=None, added_by=None
    )

    class MockResp:
        status_code = 401
        text = "Unauthorized"

    class MockClient:
        def __init__(self, *a, **kw): pass
        async def __aenter__(self): return self
        async def __aexit__(self, *a): pass
        async def get(self, url, headers=None):
            return MockResp()

    monkeypatch.setattr("app.services.managed_hosts.httpx.AsyncClient", MockClient)
    result = await managed_hosts.poll_host(h_id)
    assert result["status"] == "auth_failed"


@pytest.mark.asyncio
async def test_poll_host_unreachable(fresh_db, monkeypatch):
    import httpx as httpx_mod
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="a", base_url="https://nonexistent.invalid", api_token="t" * 30, notes=None, added_by=None
    )

    class MockClient:
        def __init__(self, *a, **kw): pass
        async def __aenter__(self): return self
        async def __aexit__(self, *a): pass
        async def get(self, url, headers=None):
            raise httpx_mod.ConnectError("connection refused")

    monkeypatch.setattr("app.services.managed_hosts.httpx.AsyncClient", MockClient)
    result = await managed_hosts.poll_host(h_id)
    assert result["status"] == "unreachable"


@pytest.mark.asyncio
async def test_poll_host_not_found(fresh_db):
    from app.services import managed_hosts

    with pytest.raises(managed_hosts.HostNotFound):
        await managed_hosts.poll_host(9999)


@pytest.mark.asyncio
async def test_poll_writes_history(fresh_db, monkeypatch):
    """Cada poll grava 1 linha em host_poll_history (best-effort)."""
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="agent", base_url="https://h.example.com", api_token="t" * 30, notes=None, added_by=None
    )

    # Mock httpx pra retornar JSON ok
    class MockResp:
        status_code = 200

        def json(self):
            return {"version": "9.9.9", "uptime_seconds": 100}

    class MockClient:
        def __init__(self, *a, **kw): pass
        async def __aenter__(self): return self
        async def __aexit__(self, *a): pass
        async def get(self, url, headers=None):
            return MockResp()

    monkeypatch.setattr("app.services.managed_hosts.httpx.AsyncClient", MockClient)
    await managed_hosts.poll_host(h_id)

    history = await managed_hosts.list_history(h_id, limit=10)
    assert len(history) == 1
    assert history[0]["status"] == "ok"
    assert history[0]["payload"]["version"] == "9.9.9"


@pytest.mark.asyncio
async def test_history_trim_keeps_last_100(fresh_db):
    """
    Insert >100 linhas direto no banco e verifica que trim deixa só 100
    quando poll_host roda. Não passa por httpx — testa só a lógica de trim.
    """
    from app.repositories.duckdb.connection import db_execute
    from app.services import managed_hosts

    h_id = await managed_hosts.create(
        label="agent", base_url="https://h.example.com", api_token="t" * 30, notes=None, added_by=None
    )
    # Seed 105 linhas pré-existentes
    for i in range(105):
        await db_execute(
            "INSERT INTO host_poll_history (host_id, status, error, payload) VALUES (?, ?, ?, ?)",
            [h_id, "ok", None, None],
        )

    # Trigger trim via poll_host (vai dar unreachable mas o trim roda igual)
    import httpx as httpx_mod

    class MockClient:
        def __init__(self, *a, **kw): pass
        async def __aenter__(self): return self
        async def __aexit__(self, *a): pass
        async def get(self, url, headers=None):
            raise httpx_mod.ConnectError("refused")

    import pytest as _p
    monkeypatch_ctx = _p.MonkeyPatch()
    monkeypatch_ctx.setattr("app.services.managed_hosts.httpx.AsyncClient", MockClient)
    try:
        await managed_hosts.poll_host(h_id)
    finally:
        monkeypatch_ctx.undo()

    # 105 + 1 (poll novo) = 106, depois trim mantém 100
    history = await managed_hosts.list_history(h_id, limit=500)
    assert len(history) == managed_hosts.HISTORY_RETENTION == 100


def test_batch_routes_declared_before_parametric():
    """
    Regressão pra HTTP 422 no UI "Atualizar todos":
    FastAPI casa rotas na ordem de declaração. Se `/batch/upgrade`
    ficasse depois de `/{host_id}/upgrade`, o request iria pra
    parametrizada, tentaria parsear `host_id="batch"` como int e
    falharia com 422.
    """
    from app.routers import hosts

    paths = [r.path for r in hosts.router.routes]
    for batch_path, parametric_path in [
        ("/api/v1/hosts/batch/poll", "/api/v1/hosts/{host_id}/poll"),
        ("/api/v1/hosts/batch/restart/{service}", "/api/v1/hosts/{host_id}/restart/{service}"),
        ("/api/v1/hosts/batch/upgrade", "/api/v1/hosts/{host_id}/upgrade"),
    ]:
        assert batch_path in paths, f"rota {batch_path} sumiu"
        assert parametric_path in paths, f"rota {parametric_path} sumiu"
        assert paths.index(batch_path) < paths.index(parametric_path), (
            f"{batch_path} precisa vir ANTES de {parametric_path} "
            "(senão FastAPI casa 'batch' como host_id e dá 422)"
        )
