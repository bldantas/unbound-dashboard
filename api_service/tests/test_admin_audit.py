"""admin_audit_service: log/list_filtered/export_csv/prune_old + lgpd_report."""

from __future__ import annotations

import csv
import io
import os
import time

import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


@pytest.fixture()
def fresh_db(tmp_path, monkeypatch):
    """DB temp com migrations aplicadas; configura o singleton settings."""
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    run_migrations(str(db))
    monkeypatch.setenv("DB_PATH", str(db))
    # Força reload do settings module-level
    from app.core import config as cfg
    cfg.settings.db_path = str(db)
    yield str(db)


async def test_log_inserts_row(fresh_db):
    from app.services import admin_audit_service

    await admin_audit_service.log(
        actor_id=1, actor_username="alice", actor_ip="10.0.0.1",
        action="login.success", category="auth",
    )
    out = await admin_audit_service.list_filtered(limit=10)
    assert out["total"] == 1
    item = out["items"][0]
    assert item["actor_username"] == "alice"
    assert item["actor_ip"] == "10.0.0.1"
    assert item["action"] == "login.success"
    assert item["category"] == "auth"


async def test_list_filtered_by_category(fresh_db):
    from app.services import admin_audit_service

    await admin_audit_service.log(
        actor_id=1, actor_username="a", actor_ip=None,
        action="login.success", category="auth",
    )
    await admin_audit_service.log(
        actor_id=2, actor_username="b", actor_ip=None,
        action="dns_security.apply", category="config",
    )
    await admin_audit_service.log(
        actor_id=2, actor_username="b", actor_ip=None,
        action="audit.export_csv", category="data_export",
    )

    auth_only = await admin_audit_service.list_filtered(category="auth")
    assert auth_only["total"] == 1
    assert auth_only["items"][0]["category"] == "auth"

    config_only = await admin_audit_service.list_filtered(category="config")
    assert config_only["total"] == 1


async def test_list_filtered_by_action_prefix(fresh_db):
    from app.services import admin_audit_service

    await admin_audit_service.log(
        actor_id=1, actor_username="a", actor_ip=None,
        action="login.success", category="auth",
    )
    await admin_audit_service.log(
        actor_id=1, actor_username="a", actor_ip=None,
        action="login.fail", category="auth",
    )
    await admin_audit_service.log(
        actor_id=1, actor_username="a", actor_ip=None,
        action="logout", category="auth",
    )

    out = await admin_audit_service.list_filtered(action_prefix="login.")
    assert out["total"] == 2


async def test_details_json_roundtrip(fresh_db):
    from app.services import admin_audit_service

    await admin_audit_service.log(
        actor_id=1, actor_username="a", actor_ip=None,
        action="config.update", category="config",
        details={"key": "value", "n": 42, "nested": {"a": [1, 2]}},
    )
    out = await admin_audit_service.list_filtered(limit=1)
    item = out["items"][0]
    assert item["details"] == {"key": "value", "n": 42, "nested": {"a": [1, 2]}}


async def test_export_csv_has_header_and_rows(fresh_db):
    from app.services import admin_audit_service

    await admin_audit_service.log(
        actor_id=1, actor_username="alice", actor_ip="10.0.0.1",
        action="x.action", category="config",
    )
    csv_str = await admin_audit_service.export_csv()
    rows = list(csv.reader(io.StringIO(csv_str)))
    assert rows[0] == [
        "id", "created_at", "actor_id", "actor_username", "actor_ip",
        "action", "category", "target_type", "target_id", "details",
    ]
    assert len(rows) == 2  # header + 1 row
    assert rows[1][3] == "alice"


async def test_prune_old_removes_old_entries(fresh_db):
    """Insere com created_at antigo manualmente, depois prune."""
    from app.repositories.duckdb.connection import db_execute
    from app.services import admin_audit_service

    # Insert com timestamp velho (40 dias atrás)
    await db_execute(
        """
        INSERT INTO admin_audit (created_at, actor_username, action, category)
        VALUES (NOW() - INTERVAL '40 days', 'old_user', 'old.action', 'auth')
        """,
        [],
    )
    # E uma recente
    await admin_audit_service.log(
        actor_id=1, actor_username="new", actor_ip=None,
        action="new.action", category="auth",
    )

    deleted = await admin_audit_service.prune_old(days=30)
    assert deleted == 1

    out = await admin_audit_service.list_filtered(limit=10)
    assert out["total"] == 1
    assert out["items"][0]["actor_username"] == "new"


async def test_lgpd_report_returns_queries_for_ip(fresh_db):
    """Seedar query_logs com 2 IPs, garantir filtro funciona."""
    from app.repositories.duckdb.connection import db_execute
    from app.services import admin_audit_service

    now = int(time.time())
    await db_execute(
        "INSERT INTO query_logs (timestamp, client_ip, domain, query_type, action) "
        "VALUES (?, '10.0.0.1', 'example.com', 'A', 'resolved')",
        [now - 100],
    )
    await db_execute(
        "INSERT INTO query_logs (timestamp, client_ip, domain, query_type, action) "
        "VALUES (?, '10.0.0.1', 'evil.com', 'A', 'blocked')",
        [now - 200],
    )
    await db_execute(
        "INSERT INTO query_logs (timestamp, client_ip, domain, query_type, action) "
        "VALUES (?, '10.0.0.2', 'other.com', 'A', 'resolved')",
        [now - 50],
    )

    report = await admin_audit_service.lgpd_report("10.0.0.1", hours=1)
    assert report["client_ip"] == "10.0.0.1"
    assert report["total"] == 2
    domains = {it["domain"] for it in report["items"]}
    assert domains == {"example.com", "evil.com"}


def test_lgpd_csv_format():
    from app.services import admin_audit_service

    report = {
        "client_ip": "10.0.0.1", "hours": 1, "cutoff": 0, "total": 1, "truncated": False,
        "items": [{
            "timestamp": 1700000000, "client_ip": "10.0.0.1",
            "query_type": "A", "domain": "example.com", "action": "resolved",
        }],
    }
    csv_str = admin_audit_service.lgpd_report_csv(report)
    rows = list(csv.reader(io.StringIO(csv_str)))
    assert rows[0] == ["timestamp_iso", "client_ip", "query_type", "domain", "action"]
    assert rows[1][1] == "10.0.0.1"
    assert rows[1][3] == "example.com"
