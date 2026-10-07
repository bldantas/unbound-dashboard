"""Workflow de aprovação: validade, aprovador humano e execução única."""

from __future__ import annotations

import asyncio

import pytest


@pytest.fixture
def fresh_db(tmp_path, monkeypatch):
    db = tmp_path / "approvals_test.duckdb"
    monkeypatch.setenv("DB_PATH", str(db))
    from app.core import config

    config.settings = config.Settings()  # noqa: SLF001
    from app.repositories.duckdb import connection

    connection.settings = config.settings  # type: ignore[attr-defined]
    from app.db import run_migrations

    run_migrations(str(db))
    return db


async def _new_request(action: str = "test.action") -> int:
    from app.services import approval_service

    out = await approval_service.request_approval(
        requester_id=1, requester_username="req", requester_ip=None,
        action=action, description="teste", payload={"x": 1},
    )
    return int(out["id"])


async def test_expired_request_cannot_be_approved(fresh_db) -> None:
    from app.repositories.duckdb.connection import db_execute
    from app.services import approval_service

    rid = await _new_request()
    await db_execute(
        "UPDATE approval_requests SET expires_at = NOW() - INTERVAL 1 HOUR WHERE id = ?", [rid]
    )
    out = await approval_service.approve(rid, 2, "aprovador")
    assert out["ok"] is False


async def test_api_token_cannot_approve(fresh_db) -> None:
    from app.services import approval_service

    rid = await _new_request()
    out = await approval_service.approve(rid, None, None)
    assert out["ok"] is False
    assert (await approval_service.approve(rid, 2, "aprovador"))["ok"] is True


async def test_concurrent_execute_runs_handler_once(fresh_db) -> None:
    from app.services import approval_service

    calls = []

    async def _handler(payload):
        calls.append(payload)
        await asyncio.sleep(0.05)
        return {"ok": True}

    approval_service.register_action_handler("test.once", _handler)
    rid = await _new_request("test.once")
    assert (await approval_service.approve(rid, 2, "aprovador"))["ok"] is True

    results = await asyncio.gather(
        approval_service.execute_request(rid), approval_service.execute_request(rid)
    )
    assert len(calls) == 1
    assert sorted(r["ok"] for r in results) == [False, True]
