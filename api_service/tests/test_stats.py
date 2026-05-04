"""Testa o endpoint /api/v1/stats/summary com DuckDB temporário populado."""

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
    """Cria DuckDB temporário com schema + alguns query_logs de teste."""
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    run_migrations(str(db))

    now = int(time.time())
    rows = [
        # Recent (dentro de 24h)
        (1, now - 100, "10.0.0.1", "ads.example.com", "A", "blocked"),
        (2, now - 200, "10.0.0.1", "ads.example.com", "A", "blocked"),
        (3, now - 300, "10.0.0.2", "safe.example.com", "A", "resolved"),
        (4, now - 400, "10.0.0.2", "other.example.com", "A", "resolved"),
        # Antigo (fora da janela 24h)
        (5, now - 86400 * 7, "10.0.0.3", "old.example.com", "A", "blocked"),
    ]
    with duckdb.connect(str(db)) as conn:
        conn.executemany(
            "INSERT INTO query_logs (id, timestamp, client_ip, domain, query_type, action) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            rows,
        )
    return str(db)


@pytest.fixture()
def client(populated_db):
    """TestClient com settings.db_path apontando para o DB de teste."""
    from fastapi.testclient import TestClient

    from app.core import config

    with patch.object(config.settings, "db_path", populated_db):
        from app.main import app

        yield TestClient(app)


def test_summary_default_window_returns_24h_only(client) -> None:
    response = client.get("/api/v1/stats/summary")
    assert response.status_code == 200
    body = response.json()

    assert body["window_hours"] == 24
    # Da fixture: 4 rows recentes (2 blocked + 2 resolved); 1 row antiga (excluída)
    assert body["totals"]["total"] == 4
    assert body["totals"]["blocked"] == 2
    assert body["totals"]["resolved"] == 2
    assert body["totals"]["block_rate"] == 0.5

    # Top blocked: ads.example.com com 2 hits
    assert body["top_blocked_domains"][0]["domain"] == "ads.example.com"
    assert body["top_blocked_domains"][0]["hits"] == 2

    # Top clients: 10.0.0.1 e 10.0.0.2 com 2 cada
    top_ips = {c["client_ip"] for c in body["top_clients"]}
    assert {"10.0.0.1", "10.0.0.2"}.issubset(top_ips)


def test_summary_custom_window_includes_old_data(client) -> None:
    # 30 dias inclui o row antigo (7 dias atrás)
    response = client.get("/api/v1/stats/summary?window_hours=720")
    assert response.status_code == 200
    body = response.json()
    assert body["window_hours"] == 720
    assert body["totals"]["total"] == 5
    assert body["totals"]["blocked"] == 3


def test_summary_invalid_window_rejected(client) -> None:
    # window_hours=0 deve ser rejeitado (ge=1)
    assert client.get("/api/v1/stats/summary?window_hours=0").status_code == 422
    # window_hours=10000 deve ser rejeitado (le=720)
    assert client.get("/api/v1/stats/summary?window_hours=10000").status_code == 422
