"""Testa o módulo de RBAC granular."""

from __future__ import annotations

from app.core.rbac import VALID_ROLES, can


def test_admin_can_everything():
    for cap in ["config.write", "users.manage", "webhooks.manage", "smtp.manage",
                "alerts.resolve", "blocklist.write", "alerts.read", "blocklist.read",
                "users.read", "config.read_sensitive", "dashboard.read"]:
        assert can("admin", cap) is True, f"admin should have {cap}"


def test_readonly_admin_cannot_modify():
    assert can("readonly_admin", "config.write") is False
    assert can("readonly_admin", "users.manage") is False
    assert can("readonly_admin", "webhooks.manage") is False
    assert can("readonly_admin", "smtp.manage") is False
    assert can("readonly_admin", "alerts.resolve") is False
    assert can("readonly_admin", "blocklist.write") is False


def test_readonly_admin_can_read_everything():
    assert can("readonly_admin", "users.read") is True
    assert can("readonly_admin", "config.read_sensitive") is True
    assert can("readonly_admin", "alerts.read") is True
    assert can("readonly_admin", "blocklist.read") is True
    assert can("readonly_admin", "dashboard.read") is True


def test_operator_noc_capabilities():
    assert can("operator", "alerts.resolve") is True
    assert can("operator", "blocklist.write") is True
    assert can("operator", "alerts.read") is True
    assert can("operator", "blocklist.read") is True
    assert can("operator", "dashboard.read") is True


def test_operator_cannot_see_sensitive():
    assert can("operator", "users.read") is False
    assert can("operator", "users.manage") is False
    assert can("operator", "config.read_sensitive") is False
    assert can("operator", "webhooks.manage") is False
    assert can("operator", "smtp.manage") is False
    assert can("operator", "config.write") is False


def test_viewer_only_dashboard():
    assert can("viewer", "dashboard.read") is True
    assert can("viewer", "alerts.read") is False
    assert can("viewer", "blocklist.read") is False
    assert can("viewer", "users.read") is False


def test_unknown_role_denied():
    assert can("hacker", "dashboard.read") is False
    assert can(None, "dashboard.read") is False
    assert can("", "dashboard.read") is False


def test_unknown_capability_denied():
    assert can("admin", "does.not.exist") is False


def test_valid_roles_set():
    assert VALID_ROLES == frozenset({"admin", "readonly_admin", "operator", "viewer"})
