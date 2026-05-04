"""Testes do fluxo de auth: login, JWT, lockout, RBAC, endpoint protegido."""

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
    """DuckDB com schema + 1 admin + 1 viewer + 1 inativo."""
    from app.core.security import hash_password
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    run_migrations(str(db))

    with duckdb.connect(str(db)) as conn:
        for username, password, role, active in [
            ("admin_user", "admin_pw_strong", "admin", True),
            ("viewer_user", "viewer_pw_strong", "viewer", True),
            ("inactive_user", "doesnt_matter", "admin", False),
        ]:
            conn.execute(
                "INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)",
                [username, hash_password(password), role, active],
            )
    return str(db)


@pytest.fixture()
def client(populated_db):
    from fastapi.testclient import TestClient

    from app.core import config

    with patch.object(config.settings, "db_path", populated_db):
        from app.main import app

        yield TestClient(app)


# ---------------------------------------------------------------------------
# Login
# ---------------------------------------------------------------------------


def test_login_success_returns_jwt(client) -> None:
    resp = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    )
    assert resp.status_code == 200, resp.text
    body = resp.json()
    assert body["token_type"] == "bearer"
    assert body["role"] == "admin"
    assert len(body["access_token"]) > 50  # JWT real


def test_login_wrong_password_returns_401(client) -> None:
    resp = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "wrong"},
    )
    assert resp.status_code == 401


def test_login_nonexistent_user_returns_401(client) -> None:
    """Mesma resposta pra usuário inexistente — não vaza enumeração."""
    resp = client.post(
        "/api/v1/auth/login",
        json={"username": "nonexistent", "password": "anything"},
    )
    assert resp.status_code == 401


def test_login_inactive_user_returns_401(client) -> None:
    resp = client.post(
        "/api/v1/auth/login",
        json={"username": "inactive_user", "password": "doesnt_matter"},
    )
    assert resp.status_code == 401


def test_login_5_failures_locks_account(client) -> None:
    for _ in range(5):
        client.post(
            "/api/v1/auth/login",
            json={"username": "viewer_user", "password": "wrong"},
        )
    # 6ª tentativa, mesmo COM senha correta, deve dar 429 (locked)
    resp = client.post(
        "/api/v1/auth/login",
        json={"username": "viewer_user", "password": "viewer_pw_strong"},
    )
    assert resp.status_code == 429


# ---------------------------------------------------------------------------
# /me
# ---------------------------------------------------------------------------


def test_me_returns_user_data(client) -> None:
    login = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    ).json()
    token = login["access_token"]
    resp = client.get("/api/v1/auth/me", headers={"Authorization": f"Bearer {token}"})
    assert resp.status_code == 200
    body = resp.json()
    assert body["username"] == "admin_user"
    assert body["role"] == "admin"
    assert body["is_active"] is True


def test_me_without_token_returns_403_or_401(client) -> None:
    resp = client.get("/api/v1/auth/me")
    assert resp.status_code in (401, 403)  # HTTPBearer returns 403 if no header


def test_me_with_invalid_token_returns_401(client) -> None:
    resp = client.get("/api/v1/auth/me", headers={"Authorization": "Bearer not-a-jwt"})
    assert resp.status_code == 401


def test_me_with_expired_token_returns_401(client) -> None:
    from datetime import timedelta

    from app.core.security import create_access_token

    expired = create_access_token(
        {"sub": "1", "role": "admin"}, expires_delta=timedelta(seconds=-1)
    )
    time.sleep(0.1)
    resp = client.get("/api/v1/auth/me", headers={"Authorization": f"Bearer {expired}"})
    assert resp.status_code == 401


# ---------------------------------------------------------------------------
# RBAC — endpoint protegido (threats)
# ---------------------------------------------------------------------------


def test_threats_data_requires_token(client) -> None:
    """Sem JWT → 403 (HTTPBearer auto_error)."""
    resp = client.get("/api/v1/threats/data")
    assert resp.status_code in (401, 403)


def test_threats_data_admin_can_access(client) -> None:
    login = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    ).json()
    resp = client.get(
        "/api/v1/threats/data",
        headers={"Authorization": f"Bearer {login['access_token']}"},
    )
    assert resp.status_code == 200
    assert resp.json()["status"] == "success"


def test_threats_data_viewer_gets_403(client) -> None:
    login = client.post(
        "/api/v1/auth/login",
        json={"username": "viewer_user", "password": "viewer_pw_strong"},
    ).json()
    resp = client.get(
        "/api/v1/threats/data",
        headers={"Authorization": f"Bearer {login['access_token']}"},
    )
    assert resp.status_code == 403


# ---------------------------------------------------------------------------
# Logout (stateless, mas exige token válido)
# ---------------------------------------------------------------------------


def test_logout_returns_204(client) -> None:
    login = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    ).json()
    resp = client.post(
        "/api/v1/auth/logout",
        headers={"Authorization": f"Bearer {login['access_token']}"},
    )
    assert resp.status_code == 204


# ---------------------------------------------------------------------------
# PUT /me/password — sync com PHP Auth::updatePassword
# ---------------------------------------------------------------------------


def test_change_password_success(client) -> None:
    login = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    ).json()
    token = login["access_token"]

    # Troca pra senha nova
    resp = client.put(
        "/api/v1/auth/me/password",
        json={"old_password": "admin_pw_strong", "new_password": "new_pw_strong"},
        headers={"Authorization": f"Bearer {token}"},
    )
    assert resp.status_code == 204

    # Senha antiga não funciona mais
    fail = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    )
    assert fail.status_code == 401

    # Senha nova funciona
    ok = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "new_pw_strong"},
    )
    assert ok.status_code == 200


def test_change_password_wrong_old_returns_400(client) -> None:
    login = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    ).json()
    resp = client.put(
        "/api/v1/auth/me/password",
        json={"old_password": "wrong_old", "new_password": "any_new_pw"},
        headers={"Authorization": f"Bearer {login['access_token']}"},
    )
    assert resp.status_code == 400


def test_change_password_too_short_returns_400(client) -> None:
    login = client.post(
        "/api/v1/auth/login",
        json={"username": "admin_user", "password": "admin_pw_strong"},
    ).json()
    resp = client.put(
        "/api/v1/auth/me/password",
        json={"old_password": "admin_pw_strong", "new_password": "abc"},  # <6
        headers={"Authorization": f"Bearer {login['access_token']}"},
    )
    assert resp.status_code == 400


def test_change_password_without_token_returns_401(client) -> None:
    resp = client.put(
        "/api/v1/auth/me/password",
        json={"old_password": "x", "new_password": "yyyyyy"},
    )
    assert resp.status_code in (401, 403)
