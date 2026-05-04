"""Testa /api/v1/threats/data — espelho do PHP v1 com DuckDB seedado."""

from __future__ import annotations

import os
import time
from unittest.mock import patch

import duckdb
import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


@pytest.fixture()
def populated_db(tmp_path):
    """DuckDB com schema, daily_stats, query_logs e blocklist_domains seedados."""
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    run_migrations(str(db))

    now = int(time.time())
    with duckdb.connect(str(db)) as conn:
        # daily_stats: 2 dias de histórico (totals do PHP somam isso)
        conn.execute(
            "INSERT INTO daily_stats (stat_date, total_queries, blocked_count, cache_hits) "
            "VALUES (CURRENT_DATE, 1000, 50, 0), (CURRENT_DATE - INTERVAL 1 DAY, 500, 25, 0)"
        )
        # blocklist_domains: 3 com category, 1 sem (testa LEFT JOIN)
        conn.execute(
            "INSERT INTO blocklist_domains VALUES "
            "('ads.evil.com', 'malware', 'high'), "
            "('tracker.example.com', 'tracking', 'medium'), "
            "('phish.evil.com', 'phishing', 'critical')"
        )
        # query_logs: blocks + um resolved (não deve aparecer nos top blocked)
        conn.executemany(
            "INSERT INTO query_logs (id, timestamp, client_ip, domain, query_type, action) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            [
                (1, now - 100, "10.0.0.1", "ads.evil.com", "A", "blocked"),
                (2, now - 200, "10.0.0.1", "ads.evil.com", "A", "blocked"),
                (3, now - 300, "10.0.0.2", "ads.evil.com", "A", "blocked"),
                (4, now - 400, "10.0.0.2", "tracker.example.com", "A", "blocked"),
                (
                    5,
                    now - 500,
                    "10.0.0.3",
                    "unknown.bad.com",
                    "A",
                    "blocked",
                ),  # NÃO está na blocklist
                (6, now - 600, "10.0.0.1", "good.example.com", "A", "resolved"),
            ],
        )
    return str(db)


@pytest.fixture()
def client(populated_db):
    """TestClient + cria um admin pra usar nos testes (threats exige require_admin)."""
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
        # Faz login e injeta token em todas requests
        login = c.post(
            "/api/v1/auth/login",
            json={"username": "test_admin", "password": "test_admin_pw"},
        ).json()
        c.headers["Authorization"] = f"Bearer {login['access_token']}"
        yield c


def test_threats_data_response_shape(client) -> None:
    resp = client.get("/api/v1/threats/data")
    assert resp.status_code == 200
    body = resp.json()
    assert body["status"] == "success"
    assert set(body["data"].keys()) == {"totals", "top", "recent"}
    assert set(body["data"]["totals"].keys()) == {"blacklist", "threats", "queries", "ratio"}
    assert set(body["data"]["top"].keys()) == {"domains", "clients"}


def test_totals_aggregate_daily_stats(client) -> None:
    body = client.get("/api/v1/threats/data").json()
    totals = body["data"]["totals"]
    assert totals["queries"] == 1500  # 1000 + 500
    assert totals["threats"] == 75  # 50 + 25
    assert totals["blacklist"] == 3  # 3 entries
    assert totals["ratio"] == 5.0  # 75/1500 * 100


def test_top_blocked_domains_only_includes_blacklisted(client) -> None:
    """INNER JOIN — domínios bloqueados que NÃO estão na blocklist são excluídos."""
    body = client.get("/api/v1/threats/data").json()
    domains = {d["label"]: d["count"] for d in body["data"]["top"]["domains"]}
    assert "ads.evil.com" in domains
    assert domains["ads.evil.com"] == 3
    assert "tracker.example.com" in domains
    assert "unknown.bad.com" not in domains  # não está na blocklist


def test_top_clients_includes_all_blocked(client) -> None:
    body = client.get("/api/v1/threats/data").json()
    clients = {c["label"]: c["count"] for c in body["data"]["top"]["clients"]}
    assert clients["10.0.0.1"] == 2
    assert clients["10.0.0.2"] == 2
    assert clients["10.0.0.3"] == 1


def test_recent_uses_left_join_with_default_category(client) -> None:
    """LEFT JOIN — domínio fora da blocklist aparece em recent com category='Geral'."""
    body = client.get("/api/v1/threats/data?limit=100").json()
    recent = body["data"]["recent"]
    assert len(recent) == 5  # 5 blocked
    # ads.evil.com está na blocklist com category=malware
    ads_entries = [r for r in recent if r["domain"] == "ads.evil.com"]
    assert all(e["category"] == "malware" for e in ads_entries)
    # unknown.bad.com NÃO está → category='Geral'
    unknown_entries = [r for r in recent if r["domain"] == "unknown.bad.com"]
    assert all(e["category"] == "Geral" for e in unknown_entries)


@pytest.mark.parametrize(
    "raw,expected",
    [
        ("10", 10),
        ("20", 20),
        ("50", 50),
        ("100", 100),
        ("todos", 1000),
        ("0", 10),  # fora da whitelist → default
        ("99999", 10),
        ("abc", 10),  # não-numérico → default
        ("", 10),
    ],
)
def test_limit_parsing(raw, expected) -> None:
    from app.routers.threats import _parse_limit

    assert _parse_limit(raw) == expected


def test_recent_time_format(client) -> None:
    """time deve ser HH:MM:SS, date DD/MM/YY."""
    import re

    body = client.get("/api/v1/threats/data").json()
    for r in body["data"]["recent"]:
        assert re.fullmatch(r"\d{2}:\d{2}:\d{2}", r["time"])
        assert re.fullmatch(r"\d{2}/\d{2}/\d{2}", r["date"])
