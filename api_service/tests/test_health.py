"""Smoke test do healthz — garante que o app FastAPI sobe e o endpoint responde."""

from __future__ import annotations

import os

import pytest
from fastapi.testclient import TestClient


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")
    os.environ.setdefault("DB_PATH", "/tmp/test.duckdb")


@pytest.fixture()
def client() -> TestClient:
    from app.main import app

    return TestClient(app)


def test_healthz_returns_200(client: TestClient) -> None:
    response = client.get("/api/v1/healthz")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert "version" in body
