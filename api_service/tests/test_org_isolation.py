"""Isolamento multi-tenant: admin de uma org não alcança recursos de outra
nem altera recursos globais (infra compartilhada)."""

from __future__ import annotations

import os
from unittest.mock import patch

import duckdb
import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


@pytest.fixture()
def world(tmp_path):
    """Orgs A/B, admin global + admins de A e B, hosts/policies global/A/B."""
    from app.core.security import hash_password
    from app.db import run_migrations

    db = tmp_path / "test.duckdb"
    run_migrations(str(db))
    ids: dict[str, int] = {}
    with duckdb.connect(str(db)) as c:
        c.execute("INSERT INTO organizations (id, name, slug) VALUES (1, 'Org A', 'a'), (2, 'Org B', 'b')")
        for name, org in (("global", None), ("admin_a", 1), ("admin_b", 2)):
            c.execute(
                "INSERT INTO users (username, password_hash, role, is_active, org_id) "
                "VALUES (?, ?, 'admin', true, ?)",
                [name, hash_password("pw_strong_123"), org],
            )
            ids[name] = c.execute("SELECT id FROM users WHERE username = ?", [name]).fetchone()[0]
        for name, org in (("h_global", None), ("h_a", 1), ("h_b", 2)):
            c.execute(
                "INSERT INTO managed_hosts (label, base_url, api_token, org_id) VALUES (?, ?, 'tok', ?)",
                [name, f"https://{name}.example", org],
            )
            ids[name] = c.execute("SELECT id FROM managed_hosts WHERE label = ?", [name]).fetchone()[0]
        for slug, org in (("p-global", None), ("p-a", 1), ("p-b", 2)):
            c.execute("INSERT INTO client_policies (slug, name, org_id) VALUES (?, ?, ?)", [slug, slug, org])
            ids[slug] = c.execute("SELECT id FROM client_policies WHERE slug = ?", [slug]).fetchone()[0]
        c.execute(
            "INSERT INTO client_policy_ranges (policy_id, cidr) VALUES (?, '10.2.0.0/24')", [ids["p-b"]]
        )
        ids["range_b"] = c.execute("SELECT id FROM client_policy_ranges").fetchone()[0]
        c.execute(
            "INSERT INTO settings (setting_key, setting_value) VALUES "
            "('smtp_password', 's3cr3t'), ('blacklist_source', 'stevenblack'), ('_oidc_state_x', 'y')"
        )
    return str(db), ids


@pytest.fixture()
def api(world):
    from fastapi.testclient import TestClient

    from app.core import config
    from app.core.security import create_access_token

    db, ids = world
    with patch.object(config.settings, "db_path", db):
        from app.main import app

        client = TestClient(app)

        def as_(user: str) -> dict:
            token = create_access_token({"sub": str(ids[user]), "role": "admin"})
            return {"Authorization": f"Bearer {token}"}

        yield client, ids, as_, db


def _count(db: str, sql: str, params: list) -> int:
    with duckdb.connect(db) as c:
        return c.execute(sql, params).fetchone()[0]


# ---------------------------------------------------------------------------
# Hosts
# ---------------------------------------------------------------------------


def test_org_admin_cannot_touch_other_org_host(api) -> None:
    client, ids, as_, db = api
    r = client.delete(f"/api/v1/hosts/{ids['h_b']}", headers=as_("admin_a"))
    assert r.status_code == 404
    assert _count(db, "SELECT COUNT(*) FROM managed_hosts WHERE id = ?", [ids["h_b"]]) == 1
    assert client.get(f"/api/v1/hosts/{ids['h_b']}/history", headers=as_("admin_a")).status_code == 404


def test_org_admin_reads_but_cannot_change_global_host(api) -> None:
    client, ids, as_, _ = api
    assert client.get(f"/api/v1/hosts/{ids['h_global']}/history", headers=as_("admin_a")).status_code == 200
    r = client.delete(f"/api/v1/hosts/{ids['h_global']}", headers=as_("admin_a"))
    assert r.status_code == 403


def test_org_admin_manages_own_host_and_global_admin_everything(api) -> None:
    client, ids, as_, _ = api
    assert client.delete(f"/api/v1/hosts/{ids['h_a']}", headers=as_("admin_a")).status_code == 204
    assert client.delete(f"/api/v1/hosts/{ids['h_b']}", headers=as_("global")).status_code == 204


def test_batch_and_push_are_global_admin_only(api) -> None:
    client, ids, as_, _ = api
    hdr = as_("admin_a")
    assert client.post("/api/v1/hosts/batch/poll", headers=hdr).status_code == 403
    assert client.post("/api/v1/hosts/batch/upgrade", headers=hdr, json={"version": "latest"}).status_code == 403
    assert client.post("/api/v1/hosts/batch/push-config", headers=hdr, json={}).status_code == 403
    assert client.post(f"/api/v1/hosts/{ids['h_a']}/push-config", headers=hdr, json={}).status_code == 403


# ---------------------------------------------------------------------------
# Settings (exports)
# ---------------------------------------------------------------------------


def test_settings_export_masks_credentials_for_non_global(api) -> None:
    client, _, as_, _ = api
    rows = {r["setting_key"]: r["setting_value"] for r in client.get(
        "/api/v1/exports/settings", headers=as_("admin_a")).json()}
    assert rows["smtp_password"] == "********"
    assert rows["blacklist_source"] == "stevenblack"
    assert "_oidc_state_x" not in rows

    rows = {r["setting_key"]: r["setting_value"] for r in client.get(
        "/api/v1/exports/settings", headers=as_("global")).json()}
    assert rows["smtp_password"] == "s3cr3t"
    assert "_oidc_state_x" not in rows


def test_settings_bulk_is_global_admin_only_and_filtered(api) -> None:
    client, _, as_, db = api
    body = [{"setting_key": "smtp_password", "setting_value": "hijack"}]
    assert client.post("/api/v1/exports/settings/bulk", headers=as_("admin_a"), json=body).status_code == 403

    bad = [{"setting_key": "_oidc_state_z", "setting_value": "x"}]
    assert client.post("/api/v1/exports/settings/bulk", headers=as_("global"), json=bad).status_code == 400

    masked = [{"setting_key": "smtp_password", "setting_value": "********"}]
    assert client.post("/api/v1/exports/settings/bulk", headers=as_("global"), json=masked).status_code == 200
    assert _count(db, "SELECT COUNT(*) FROM settings WHERE setting_key = 'smtp_password' "
                      "AND setting_value = 's3cr3t'", []) == 1


# ---------------------------------------------------------------------------
# Policies e apply-config
# ---------------------------------------------------------------------------


def test_org_admin_cannot_change_global_policy(api) -> None:
    client, _, as_, db = api
    assert client.delete("/api/v1/policies/p-global", headers=as_("admin_a")).status_code == 403
    assert _count(db, "SELECT COUNT(*) FROM client_policies WHERE slug = 'p-global'", []) == 1
    assert client.get("/api/v1/policies/p-global", headers=as_("admin_a")).status_code == 200


def test_range_removal_is_scoped_to_its_policy(api) -> None:
    client, ids, as_, db = api
    r = client.delete(f"/api/v1/policies/p-a/ranges/{ids['range_b']}", headers=as_("admin_a"))
    assert r.json()["removed"] is False
    assert _count(db, "SELECT COUNT(*) FROM client_policy_ranges WHERE id = ?", [ids["range_b"]]) == 1


def test_apply_config_is_global_admin_only(api) -> None:
    client, _, as_, _ = api
    body = {"policies": [{"slug": "p-b", "name": "x", "ranges": [], "blocks": ["banco.example"]}]}
    assert client.post("/api/v1/host/apply-config", headers=as_("admin_a"), json=body).status_code == 403


# ---------------------------------------------------------------------------
# Ações de auth sobre outro usuário
# ---------------------------------------------------------------------------


def test_org_admin_cannot_target_users_outside_own_org(api) -> None:
    client, ids, as_, _ = api
    hdr = as_("admin_a")
    assert client.post(f"/api/v1/auth/revoke/{ids['global']}", headers=hdr).status_code == 403
    assert client.post(f"/api/v1/auth/2fa/admin-reset/{ids['admin_b']}", headers=hdr).status_code == 403


# ---------------------------------------------------------------------------
# WebSocket auth
# ---------------------------------------------------------------------------


async def test_ws_token_rules(monkeypatch) -> None:
    from app.core import deps
    from app.core.security import create_access_token
    from app.services import api_tokens

    assert await deps.validate_ws_token(create_access_token({"sub": "1", "totp_pending": True})) is None
    ok = create_access_token({"sub": "1", "role": "viewer"})
    assert await deps.validate_ws_token(ok) is not None

    async def _revoked(user_id, iat):
        return True

    monkeypatch.setattr(deps, "is_user_revoked", _revoked)
    assert await deps.validate_ws_token(ok) is None

    async def _scoped(raw, source_ip=None):
        return {"id": 7, "label": "t", "capabilities": ["alerts.read"]}

    monkeypatch.setattr(api_tokens, "verify", _scoped)
    assert await deps.validate_ws_token("udt_qualquer") is None


# ---------------------------------------------------------------------------
# Configuração global (webhooks, rate limits, approvals, geo-blocking)
# ---------------------------------------------------------------------------


def test_global_config_routes_deny_org_admin(api) -> None:
    client, _, as_, _ = api
    hdr = as_("admin_a")
    assert client.get("/api/v1/webhooks/config", headers=hdr).status_code == 403
    assert client.get("/api/v1/rate-limits/config", headers=hdr).status_code == 403
    assert client.get("/api/v1/approvals/config", headers=hdr).status_code == 403
    assert client.delete("/api/v1/geo-blocking/countries/BR", headers=hdr).status_code == 403
    assert client.get("/api/v1/webhooks/config", headers=as_("global")).status_code == 200


async def test_global_capability_respects_api_token_scope() -> None:
    from fastapi import HTTPException

    from app.core.deps import require_global_capability

    dep = require_global_capability("config.write")
    token = {"sub": "api-token", "role": "admin", "auth_kind": "api_token"}
    assert await dep({**token, "api_token_capabilities": []}) is not None
    assert await dep({**token, "api_token_capabilities": ["config.write"]}) is not None
    with pytest.raises(HTTPException) as exc:
        await dep({**token, "api_token_capabilities": ["dashboard.read"]})
    assert exc.value.status_code == 403
