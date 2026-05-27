"""
Per-user notification preferences (V26).

Cada user (não-API-token) tem 1 linha em `user_notification_prefs`.
Defaults aplicados se a linha não existe ainda — não força INSERT no
primeiro `get()`, só no primeiro `update()`.
"""

from __future__ import annotations

import json
from datetime import datetime
from typing import Any

import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

_VALID_SEVERITIES = ("critical", "warning", "info")


def _defaults() -> dict[str, Any]:
    return {
        "severity_min": "warning",
        "categories": [],
        "digest_enabled": False,
        "digest_hour": 8,
        "last_digest_sent_at": None,
    }


def _row_to_dict(r: dict | None) -> dict[str, Any]:
    if r is None:
        return _defaults()
    raw_cats = r.get("categories") or "[]"
    try:
        cats = json.loads(raw_cats) if isinstance(raw_cats, str) else list(raw_cats)
    except (json.JSONDecodeError, TypeError):
        cats = []
    return {
        "severity_min": r.get("severity_min") or "warning",
        "categories": cats if isinstance(cats, list) else [],
        "digest_enabled": bool(r.get("digest_enabled")),
        "digest_hour": int(r.get("digest_hour") or 8),
        "last_digest_sent_at": (
            r["last_digest_sent_at"].isoformat()
            if isinstance(r.get("last_digest_sent_at"), datetime) else None
        ),
    }


async def get(user_id: int) -> dict[str, Any]:
    row = await db_fetchone(
        "SELECT * FROM user_notification_prefs WHERE user_id = ?", [int(user_id)]
    )
    return _row_to_dict(row)


async def update(user_id: int, body: dict) -> dict[str, Any]:
    """Upsert das prefs do user. Valida severity e digest_hour."""
    sev = body.get("severity_min", "warning")
    if sev not in _VALID_SEVERITIES:
        raise ValueError(f"severity_min inválido: {sev}")

    cats = body.get("categories", [])
    if isinstance(cats, str):
        try:
            cats = json.loads(cats)
        except json.JSONDecodeError:
            raise ValueError("categories: JSON inválido")
    if not isinstance(cats, list):
        raise ValueError("categories deve ser array")
    cats = [str(c)[:50] for c in cats][:20]

    digest_enabled = bool(body.get("digest_enabled", False))

    try:
        digest_hour = int(body.get("digest_hour", 8))
    except (TypeError, ValueError):
        raise ValueError("digest_hour deve ser inteiro 0..23")
    if not 0 <= digest_hour <= 23:
        raise ValueError("digest_hour fora do range 0..23")

    existing = await db_fetchone(
        "SELECT id FROM user_notification_prefs WHERE user_id = ?", [int(user_id)]
    )
    cats_json = json.dumps(cats, ensure_ascii=False)
    if existing:
        await db_execute(
            """
            UPDATE user_notification_prefs SET
                severity_min = ?, categories = ?, digest_enabled = ?,
                digest_hour = ?, updated_at = NOW()
            WHERE user_id = ?
            """,
            [sev, cats_json, digest_enabled, digest_hour, int(user_id)],
        )
    else:
        await db_execute(
            """
            INSERT INTO user_notification_prefs
                (user_id, severity_min, categories, digest_enabled, digest_hour)
            VALUES (?, ?, ?, ?, ?)
            """,
            [int(user_id), sev, cats_json, digest_enabled, digest_hour],
        )
    return await get(user_id)


async def list_due_for_digest(current_hour: int) -> list[dict]:
    """Users com digest_enabled cuja hora bate e que não foram avisados hoje.

    Retorna joined com email do users — caller decide quem realmente notifica.
    """
    rows = await db_fetchall(
        """
        SELECT p.user_id, p.severity_min, p.categories, p.digest_hour,
               p.last_digest_sent_at, u.email, u.username, u.is_active
        FROM user_notification_prefs p
        JOIN users u ON u.id = p.user_id
        WHERE p.digest_enabled = true
          AND p.digest_hour = ?
          AND u.is_active = true
          AND COALESCE(u.email, '') <> ''
          AND (p.last_digest_sent_at IS NULL
               OR DATE_TRUNC('day', p.last_digest_sent_at) < DATE_TRUNC('day', NOW()))
        """,
        [int(current_hour)],
    )
    out = []
    for r in rows:
        try:
            cats = json.loads(r.get("categories") or "[]")
        except (json.JSONDecodeError, TypeError):
            cats = []
        out.append({
            "user_id": int(r["user_id"]),
            "email": r["email"],
            "username": r["username"],
            "severity_min": r.get("severity_min") or "warning",
            "categories": cats if isinstance(cats, list) else [],
        })
    return out


async def mark_digest_sent(user_id: int) -> None:
    await db_execute(
        "UPDATE user_notification_prefs SET last_digest_sent_at = NOW() WHERE user_id = ?",
        [int(user_id)],
    )
