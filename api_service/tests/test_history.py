"""Testa /api/v1/history/summary."""

from __future__ import annotations

import time
from unittest.mock import patch

import duckdb
import pytest


@pytest.fixture()
def populated_db(tmp_path):
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    run_migrations(str(db))
    now = int(time.time())
    with duckdb.connect(str(db)) as conn:
        conn.execute("INSERT INTO blocklist_domains VALUES ('ads.com', 'ads', 'medium')")
        conn.executemany(
            "INSERT INTO query_logs (id, timestamp, client_ip, domain, query_type, action) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            [
                (1, now - 100, "10.0.0.1", "ads.com", "A", "blocked"),
                (2, now - 200, "10.0.0.1", "ads.com", "A", "blocked"),
                (3, now - 300, "10.0.0.1", "ads.com", "A", "blocked"),
                (4, now - 400, "10.0.0.2", "safe.com", "A", "resolved"),
                (5, now - 500, "10.0.0.3", "other.com", "A", "resolved"),
                (6, now - 86400 * 2, "10.0.0.4", "old.com", "A", "blocked"),  # 2d atrás
            ],
        )
    return str(db)


@pytest.fixture()
def client(populated_db):
    from fastapi.testclient import TestClient

    from app.core import config
    from app.core.security import hash_password

    with duckdb.connect(populated_db) as conn:
        conn.execute(
            "INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)",
            ["test_admin", hash_password("test_admin_pw"), "admin", True],
        )

    with patch.object(config.settings, "db_path", populated_db):
        from app.main import app

        c = TestClient(app)
        login = c.post(
            "/api/v1/auth/login",
            json={"username": "test_admin", "password": "test_admin_pw"},
        ).json()
        c.headers["Authorization"] = f"Bearer {login['access_token']}"
        yield c


def test_summary_top_domains_excludes_old(client) -> None:
    body = client.get("/api/v1/history/summary").json()
    domains = {d["domain"]: d["count"] for d in body["top_domains_24h"]}
    assert domains == {"ads.com": 3, "safe.com": 1, "other.com": 1}
    assert "old.com" not in domains  # 2d atrás, fora 24h


def test_summary_recent_queries_includes_category(client) -> None:
    body = client.get("/api/v1/history/summary?limit=10").json()
    recent = body["recent_queries"]
    assert len(recent) == 6  # todos
    ads = [r for r in recent if r["domain"] == "ads.com"]
    assert all(r["category"] == "ads" for r in ads)
    safe = [r for r in recent if r["domain"] == "safe.com"]
    assert all(r["category"] is None for r in safe)


def test_summary_requires_auth(populated_db) -> None:
    from fastapi.testclient import TestClient

    from app.core import config

    with patch.object(config.settings, "db_path", populated_db):
        from app.main import app

        c = TestClient(app)
        resp = c.get("/api/v1/history/summary")
        assert resp.status_code in (401, 403)


def test_summary_limit_parsing(client) -> None:
    assert client.get("/api/v1/history/summary?limit=20").status_code == 200
    assert client.get("/api/v1/history/summary?limit=todos").status_code == 200
    assert client.get("/api/v1/history/summary?limit=99999").status_code == 200  # fallback 10
