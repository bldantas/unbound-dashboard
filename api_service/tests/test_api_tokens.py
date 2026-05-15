"""Testes do services/api_tokens.py — geração, verificação, revogação."""

from __future__ import annotations

import pytest


@pytest.fixture
def fresh_db(tmp_path, monkeypatch):
    db = tmp_path / "tokens_test.duckdb"
    monkeypatch.setenv("DB_PATH", str(db))
    from app.core import config

    config.settings = config.Settings()  # noqa: SLF001
    from app.repositories.duckdb import connection
    connection.settings = config.settings  # type: ignore[attr-defined]

    from app.db import run_migrations
    run_migrations(str(db))
    return db


def test_generate_raw_token_unique():
    from app.services.api_tokens import generate_raw_token

    a = generate_raw_token()
    b = generate_raw_token()
    assert a != b
    # token_urlsafe(32) → 43 chars base64url
    assert len(a) >= 40
    assert len(b) >= 40


@pytest.mark.asyncio
async def test_create_then_verify_then_revoke(fresh_db):
    from app.services import api_tokens

    token_id, raw = await api_tokens.create("test-token", created_by=1)
    assert token_id > 0
    assert len(raw) >= 40

    # Verify
    meta = await api_tokens.verify(raw, source_ip="1.2.3.4")
    assert meta is not None
    assert meta["id"] == token_id
    assert meta["label"] == "test-token"

    # List
    items = await api_tokens.list_active()
    assert len(items) == 1
    assert items[0]["label"] == "test-token"
    assert items[0]["is_active"] is True

    # Revoke
    ok = await api_tokens.revoke(token_id)
    assert ok is True

    # Verify falha após revoga
    meta = await api_tokens.verify(raw)
    assert meta is None

    # Re-revoke retorna False
    ok = await api_tokens.revoke(token_id)
    assert ok is False


@pytest.mark.asyncio
async def test_verify_invalid_token(fresh_db):
    from app.services import api_tokens

    # Token muito curto rejeita sem hit no banco
    assert await api_tokens.verify("") is None
    assert await api_tokens.verify("short") is None
    # Token de tamanho ok mas inexistente
    assert await api_tokens.verify("a" * 50) is None


@pytest.mark.asyncio
async def test_list_includes_revoked_when_flag(fresh_db):
    from app.services import api_tokens

    id1, _ = await api_tokens.create("active", created_by=1)
    id2, _ = await api_tokens.create("to-revoke", created_by=1)
    await api_tokens.revoke(id2)

    # Default: só ativos
    active = await api_tokens.list_active(include_revoked=False)
    assert len(active) == 1
    assert active[0]["id"] == id1

    # Com flag: ambos
    all_t = await api_tokens.list_active(include_revoked=True)
    assert len(all_t) == 2
    assert {t["id"] for t in all_t} == {id1, id2}


@pytest.mark.asyncio
async def test_verify_updates_last_used(fresh_db):
    from app.services import api_tokens

    _, raw = await api_tokens.create("ping", created_by=None)

    # Antes da verificação, last_used = NULL
    items = await api_tokens.list_active()
    assert items[0]["last_used_at"] is None

    # Verifica
    await api_tokens.verify(raw, source_ip="9.9.9.9")

    # Depois, last_used preenchido
    items = await api_tokens.list_active()
    assert items[0]["last_used_at"] is not None
    assert items[0]["last_used_ip"] == "9.9.9.9"
