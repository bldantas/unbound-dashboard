"""Regressões de segurança do fluxo de auth: challenge 2FA, API token com
escopo e rota interna de reset de senha."""

from __future__ import annotations

import os
from unittest.mock import patch

import duckdb
import pytest
from fastapi import HTTPException
from starlette.requests import Request


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


@pytest.fixture()
def populated_db(tmp_path):
    """DuckDB com schema + 1 admin (id=1)."""
    from app.core.security import hash_password
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    run_migrations(str(db))

    with duckdb.connect(str(db)) as conn:
        conn.execute(
            "INSERT INTO users (username, password_hash, role, is_active, email) "
            "VALUES (?, ?, 'admin', true, 'admin@example.com')",
            ["admin_user", hash_password("admin_pw_strong")],
        )
    return str(db)


@pytest.fixture()
def client(populated_db):
    from fastapi.testclient import TestClient

    from app.core import config

    with patch.object(config.settings, "db_path", populated_db):
        from app.main import app

        yield TestClient(app)


def _challenge_token() -> str:
    import secrets

    from app.core.security import create_access_token

    return create_access_token({"sub": "1", "totp_pending": True, "jti": secrets.token_hex(16)})


# ---------------------------------------------------------------------------
# Challenge 2FA não vale como JWT completo
# ---------------------------------------------------------------------------


def test_challenge_token_rejected_by_protected_route(client) -> None:
    resp = client.get(
        "/api/v1/auth/me", headers={"Authorization": f"Bearer {_challenge_token()}"}
    )
    assert resp.status_code == 401


def test_challenge_token_cannot_be_refreshed(client) -> None:
    resp = client.post(
        "/api/v1/auth/refresh", headers={"Authorization": f"Bearer {_challenge_token()}"}
    )
    assert resp.status_code == 401


def test_full_token_still_refreshes(client) -> None:
    from app.core.security import create_access_token

    token = create_access_token({"sub": "1", "role": "admin"})
    resp = client.post("/api/v1/auth/refresh", headers={"Authorization": f"Bearer {token}"})
    assert resp.status_code == 200, resp.text


# ---------------------------------------------------------------------------
# API token com escopo não passa em rotas só-admin
# ---------------------------------------------------------------------------


def _api_token_payload(capabilities: list[str]) -> dict:
    return {
        "sub": "api-token",
        "role": "admin",
        "auth_kind": "api_token",
        "api_token_id": 1,
        "api_token_label": "t",
        "api_token_capabilities": capabilities,
    }


@pytest.mark.asyncio
async def test_scoped_api_token_denied_by_require_admin() -> None:
    from app.core.deps import require_admin

    with pytest.raises(HTTPException) as exc:
        await require_admin(_api_token_payload(["dashboard.read"]))
    assert exc.value.status_code == 403


@pytest.mark.asyncio
async def test_scoped_api_token_denied_by_require_global_admin() -> None:
    from app.core.deps import require_global_admin

    with pytest.raises(HTTPException) as exc:
        await require_global_admin(_api_token_payload(["dashboard.read"]))
    assert exc.value.status_code == 403


@pytest.mark.asyncio
async def test_unscoped_api_token_still_global_admin() -> None:
    from app.core.deps import require_admin, require_global_admin

    payload = _api_token_payload([])
    assert await require_admin(payload) is payload
    assert await require_global_admin(payload) is payload


# ---------------------------------------------------------------------------
# Reset de senha: token só pra chamada local direta (PHP)
# ---------------------------------------------------------------------------


def _request(client_host: str, headers: dict[str, str] | None = None) -> Request:
    raw = [(k.lower().encode(), v.encode()) for k, v in (headers or {}).items()]
    return Request({"type": "http", "client": (client_host, 12345), "headers": raw})


def test_direct_local_request_detection() -> None:
    from app.routers.auth import _is_direct_local_request

    assert _is_direct_local_request(_request("127.0.0.1"))
    assert _is_direct_local_request(_request("::1"))
    assert not _is_direct_local_request(_request("203.0.113.9"))
    # Veio pelo Apache (mod_proxy sempre adiciona X-Forwarded-For)
    assert not _is_direct_local_request(
        _request("127.0.0.1", {"X-Forwarded-For": "203.0.113.9"})
    )


def test_password_reset_request_refused_for_remote_caller(client) -> None:
    resp = client.post(
        "/api/v1/auth/password-reset/request", json={"email": "admin@example.com"}
    )
    assert resp.status_code == 403
    assert "token" not in resp.json()


# ---------------------------------------------------------------------------
# Rate limit por IP real e limite de códigos 2FA por challenge
# ---------------------------------------------------------------------------


def test_client_ip_key_uses_last_forwarded_hop() -> None:
    from app.core.rate_limit import client_ip_key

    # Atrás do Apache: o primeiro item do XFF é do cliente (forjável); o último é do Apache
    assert client_ip_key(_request("127.0.0.1", {"X-Forwarded-For": "6.6.6.6, 203.0.113.9"})) == "ip:203.0.113.9"
    # uvicorn já resolveu o IP real (proxy_headers): usa direto
    assert client_ip_key(_request("203.0.113.9", {"X-Forwarded-For": "6.6.6.6"})) == "ip:203.0.113.9"


async def test_totp_challenge_dies_after_too_many_wrong_codes(populated_db) -> None:
    import pyotp

    from app.core import config
    from app.repositories.duckdb import connection
    from app.services import auth_service

    secret = pyotp.random_base32()
    with duckdb.connect(populated_db) as c:
        c.execute("UPDATE users SET totp_enabled = true, totp_secret = ? WHERE id = 1", [secret])

    with patch.object(config.settings, "db_path", populated_db), \
            patch.object(connection.settings, "db_path", populated_db):
        challenge = _challenge_token()
        for _ in range(5):
            with pytest.raises(auth_service.InvalidTOTPCode):
                await auth_service.login_2fa_verify(challenge, "000000")
        # Mesmo com o código certo, o challenge esgotado não vale mais
        with pytest.raises(auth_service.InvalidChallengeToken):
            await auth_service.login_2fa_verify(challenge, pyotp.TOTP(secret).now())
        # Novo challenge (novo login) funciona
        out = await auth_service.login_2fa_verify(_challenge_token(), pyotp.TOTP(secret).now())
        assert out["access_token"]


# ---------------------------------------------------------------------------
# E-mail de reset enviado pela API (SMTP do painel)
# ---------------------------------------------------------------------------


async def test_reset_email_uses_public_url_not_request_host(monkeypatch) -> None:
    from app.repositories.duckdb import settings_repo
    from app.routers import auth as auth_router
    from app.services import email_notifier

    sent = {}

    async def _cfg():
        return {"enabled": True, "host": "smtp", "from_addr": "x@y.z"}

    def _send(cfg, to, subject, body, html_body=None):
        sent["body"] = body
        return True, "ok"

    async def _get(key, default=None):
        return "https://painel.exemplo/unbound-dashboard" if key == "dashboard_public_url" else default

    monkeypatch.setattr(email_notifier, "_load_smtp_config", _cfg)
    monkeypatch.setattr(email_notifier, "_send_via_smtp", _send)
    monkeypatch.setattr(settings_repo, "get", _get)

    ok = await auth_router._send_reset_email("a@b.c", "tok123", "https://evil.example/reset.php")
    assert ok is True
    assert "https://painel.exemplo/unbound-dashboard/reset.php?token=tok123" in sent["body"]
    assert "evil.example" not in sent["body"]


async def test_reset_email_falls_back_when_smtp_disabled(monkeypatch) -> None:
    from app.routers import auth as auth_router
    from app.services import email_notifier

    async def _cfg():
        return {"enabled": False, "host": "", "from_addr": ""}

    monkeypatch.setattr(email_notifier, "_load_smtp_config", _cfg)
    assert await auth_router._send_reset_email("a@b.c", "tok", "https://h/reset.php") is False
