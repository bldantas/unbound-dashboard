"""Regressões de confiabilidade: lockout de login concorrente, digest diário
e backup consistente do DuckDB."""

from __future__ import annotations

import asyncio
import os
import tarfile
from datetime import datetime, timedelta

import duckdb
import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


@pytest.fixture
def fresh_db(tmp_path, monkeypatch):
    db = tmp_path / "rel_test.duckdb"
    monkeypatch.setenv("DB_PATH", str(db))
    from app.core import config

    config.settings = config.Settings()  # noqa: SLF001
    from app.repositories.duckdb import connection

    connection.settings = config.settings  # type: ignore[attr-defined]
    from app.db import run_migrations

    run_migrations(str(db))
    return str(db)


async def test_concurrent_wrong_passwords_trigger_lockout(fresh_db) -> None:
    from app.core.security import hash_password
    from app.services import auth_service

    with duckdb.connect(fresh_db) as c:
        c.execute(
            "INSERT INTO users (username, password_hash, role, is_active) VALUES ('u', ?, 'admin', true)",
            [hash_password("senha_certa_123")],
        )

    async def _try():
        try:
            await auth_service.login("u", "errada")
        except Exception:  # noqa: BLE001
            pass

    await asyncio.gather(*[_try() for _ in range(8)])
    with duckdb.connect(fresh_db) as c:
        failed, locked = c.execute(
            "SELECT failed_logins, locked_until FROM users WHERE username = 'u'"
        ).fetchone()
    assert failed == 8
    assert locked is not None
    with pytest.raises(auth_service.AccountLocked):
        await auth_service.login("u", "senha_certa_123")


async def test_digest_collects_recent_alerts(monkeypatch) -> None:
    """started_at do DuckDB é naive (hora local): não pode quebrar a comparação."""
    from app.workers import digest_sender

    now_local = datetime.now()
    rows = {
        "items": [
            {
                "id": 1,
                "type": "t",
                "severity": "warning",
                "message": "recente",
                "started_at": now_local,
            },
            {
                "id": 2,
                "type": "t",
                "severity": "warning",
                "message": "velho",
                "started_at": now_local - timedelta(days=3),
            },
        ]
    }

    async def _list_filtered(**kw):
        return rows

    captured = {}

    async def _due(hour):
        return [
            {
                "user_id": 1,
                "email": "a@b.c",
                "username": "a",
                "org_id": None,
                "severity_min": "info",
                "categories": [],
            }
        ]

    async def _cfg():
        return {"enabled": True, "host": "smtp", "from_addr": "x@y.z"}

    def _send(cfg, to, subject, body, html_body=None):
        captured["body"] = body
        return True, "ok"

    async def _noop(*a, **k):
        return None

    monkeypatch.setattr(digest_sender.alert_repo, "list_filtered", _list_filtered)
    monkeypatch.setattr(digest_sender.email_notifier, "_load_smtp_config", _cfg)
    monkeypatch.setattr(digest_sender.email_notifier, "_send_via_smtp", _send)
    monkeypatch.setattr(digest_sender.notification_prefs_service, "list_due_for_digest", _due)
    monkeypatch.setattr(digest_sender.notification_prefs_service, "mark_digest_sent", _noop)

    worker = digest_sender.DigestSender()
    out = await worker._run_once()  # noqa: SLF001
    assert "recente" in captured.get("body", "")
    assert "velho" not in captured["body"]
    assert out["sent"] == 1


async def test_backup_archive_contains_consistent_duckdb_snapshot(
    fresh_db, monkeypatch, tmp_path
) -> None:
    from app.repositories.duckdb.connection import db_execute
    from app.services import backup_offsite_service

    monkeypatch.setattr(backup_offsite_service.app_settings, "db_path", fresh_db)
    monkeypatch.setattr(backup_offsite_service, "INCLUDED_PATHS", [])
    await db_execute(
        "INSERT INTO settings (setting_key, setting_value) VALUES ('marcador', 'depois-do-checkpoint')"
    )

    archive, size = await asyncio.get_running_loop().run_in_executor(
        None,
        backup_offsite_service._create_archive,  # noqa: SLF001
    )
    assert size > 0
    out = tmp_path / "x"
    with tarfile.open(archive) as tar:
        tar.extractall(out, filter="data")
    restored = next(out.glob("duckdb/*.duckdb"))
    with duckdb.connect(str(restored), read_only=True) as c:
        val = c.execute(
            "SELECT setting_value FROM settings WHERE setting_key = 'marcador'"
        ).fetchone()
    assert val == ("depois-do-checkpoint",)
    os.unlink(archive)
