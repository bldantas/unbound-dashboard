"""
Workflow approval (2nd-approver) — opt-in via setting.

Quando `workflow_approval_enabled=1` E action ∈ `workflow_approval_actions`
(CSV), o caller não executa: chama `request(...)` que registra o pedido
em `approval_requests` e devolve `{status: pending, request_id}`. Um
admin diferente do requester revisa em `/approvals.php` e aprova/rejeita.

NÃO há "execute" automatizado nesta versão — operador aprova e re-aciona
a ação manualmente. Wire automático é TODO (precisa table de actions
permitidas e dispatch interno).
"""

from __future__ import annotations

import json
from datetime import datetime, timedelta, timezone
from typing import Any

import structlog

from app.repositories.duckdb import settings_repo
from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

VALID_STATUSES = ("pending", "approved", "rejected", "executed", "expired")
DEFAULT_TTL_HOURS = 24


def _to_iso(v: Any) -> str | None:
    if isinstance(v, datetime):
        return v.isoformat()
    return str(v) if v else None


def _row_to_dict(r: dict) -> dict[str, Any]:
    payload = r.get("payload")
    if isinstance(payload, str):
        try:
            payload = json.loads(payload)
        except Exception:  # noqa: BLE001
            pass
    result = r.get("executed_result")
    if isinstance(result, str):
        try:
            result = json.loads(result)
        except Exception:  # noqa: BLE001
            pass
    return {
        "id": int(r["id"]),
        "created_at": _to_iso(r.get("created_at")),
        "requester_id": r.get("requester_id"),
        "requester_username": r.get("requester_username") or "?",
        "requester_ip": r.get("requester_ip"),
        "action": r.get("action"),
        "description": r.get("description") or "",
        "payload": payload,
        "status": r.get("status"),
        "approver_id": r.get("approver_id"),
        "approver_username": r.get("approver_username"),
        "approved_at": _to_iso(r.get("approved_at")),
        "rejected_reason": r.get("rejected_reason"),
        "executed_at": _to_iso(r.get("executed_at")),
        "executed_result": result,
        "expires_at": _to_iso(r.get("expires_at")),
    }


# ---------- Config helpers ----------

async def is_enabled() -> bool:
    return await settings_repo.get_bool("workflow_approval_enabled", False)


async def required_for(action: str) -> bool:
    """True se workflow está habilitado E action está na lista."""
    if not await is_enabled():
        return False
    raw = await settings_repo.get("workflow_approval_actions", "")
    actions = {a.strip() for a in (raw or "").split(",") if a.strip()}
    return action in actions


async def get_config() -> dict:
    return {
        "enabled": await is_enabled(),
        "actions": (await settings_repo.get("workflow_approval_actions", "")) or "",
        "ttl_hours": await settings_repo.get_int("workflow_approval_ttl_hours", DEFAULT_TTL_HOURS),
    }


async def update_config(enabled: bool | None, actions: str | None, ttl_hours: int | None) -> dict:
    entries = []
    if enabled is not None:
        entries.append({"setting_key": "workflow_approval_enabled", "setting_value": "1" if enabled else "0"})
    if actions is not None:
        entries.append({"setting_key": "workflow_approval_actions", "setting_value": str(actions)})
    if ttl_hours is not None:
        ttl_hours = max(1, min(168, int(ttl_hours)))
        entries.append({"setting_key": "workflow_approval_ttl_hours", "setting_value": str(ttl_hours)})
    if entries:
        await settings_repo.bulk_upsert(entries)
    return await get_config()


# ---------- Request lifecycle ----------

async def request_approval(
    *,
    requester_id: int,
    requester_username: str | None,
    requester_ip: str | None,
    action: str,
    description: str,
    payload: dict | None = None,
) -> dict:
    ttl_hours = await settings_repo.get_int("workflow_approval_ttl_hours", DEFAULT_TTL_HOURS)
    expires_at = datetime.now(timezone.utc) + timedelta(hours=ttl_hours)
    await db_execute(
        """
        INSERT INTO approval_requests
            (requester_id, requester_username, requester_ip, action,
             description, payload, status, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
        """,
        [
            int(requester_id),
            requester_username[:64] if requester_username else None,
            requester_ip[:64] if requester_ip else None,
            action[:80],
            description[:500] if description else None,
            json.dumps(payload) if payload is not None else None,
            expires_at,
        ],
    )
    row = await db_fetchone(
        "SELECT * FROM approval_requests WHERE requester_id = ? AND action = ? ORDER BY id DESC LIMIT 1",
        [int(requester_id), action[:80]],
    )
    return _row_to_dict(row) if row else {}


async def list_pending() -> list[dict]:
    await _expire_old()
    rows = await db_fetchall(
        "SELECT * FROM approval_requests WHERE status = 'pending' ORDER BY created_at DESC",
        [],
    )
    return [_row_to_dict(r) for r in rows]


async def list_all(limit: int = 200) -> list[dict]:
    await _expire_old()
    rows = await db_fetchall(
        "SELECT * FROM approval_requests ORDER BY created_at DESC LIMIT ?",
        [int(limit)],
    )
    return [_row_to_dict(r) for r in rows]


async def get(request_id: int) -> dict | None:
    row = await db_fetchone(
        "SELECT * FROM approval_requests WHERE id = ?", [int(request_id)],
    )
    return _row_to_dict(row) if row else None


async def approve(request_id: int, approver_id: int, approver_username: str | None) -> dict:
    row = await db_fetchone(
        "SELECT * FROM approval_requests WHERE id = ? AND status = 'pending'",
        [int(request_id)],
    )
    if not row:
        return {"ok": False, "error": "request não pendente ou inexistente"}
    if int(row["requester_id"]) == int(approver_id):
        return {"ok": False, "error": "requester não pode aprovar o próprio request"}
    await db_execute(
        """
        UPDATE approval_requests
        SET status = 'approved', approver_id = ?, approver_username = ?, approved_at = NOW()
        WHERE id = ?
        """,
        [int(approver_id), approver_username[:64] if approver_username else None, int(request_id)],
    )
    return {"ok": True, "request_id": int(request_id)}


async def reject(request_id: int, approver_id: int, approver_username: str | None, reason: str = "") -> dict:
    row = await db_fetchone(
        "SELECT * FROM approval_requests WHERE id = ? AND status = 'pending'",
        [int(request_id)],
    )
    if not row:
        return {"ok": False, "error": "request não pendente ou inexistente"}
    if int(row["requester_id"]) == int(approver_id):
        return {"ok": False, "error": "requester não pode rejeitar o próprio request"}
    await db_execute(
        """
        UPDATE approval_requests
        SET status = 'rejected', approver_id = ?, approver_username = ?,
            approved_at = NOW(), rejected_reason = ?
        WHERE id = ?
        """,
        [
            int(approver_id),
            approver_username[:64] if approver_username else None,
            (reason or "")[:255],
            int(request_id),
        ],
    )
    return {"ok": True, "request_id": int(request_id)}


async def mark_executed(request_id: int, result: dict | None = None) -> bool:
    """Caller chama isso depois de re-executar a ação manualmente."""
    row = await db_fetchone(
        "SELECT id FROM approval_requests WHERE id = ? AND status = 'approved'",
        [int(request_id)],
    )
    if not row:
        return False
    await db_execute(
        """
        UPDATE approval_requests
        SET status = 'executed', executed_at = NOW(), executed_result = ?
        WHERE id = ?
        """,
        [json.dumps(result) if result is not None else None, int(request_id)],
    )
    return True


async def _expire_old() -> int:
    """Marca pending com expires_at no passado como 'expired'."""
    row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM approval_requests WHERE status = 'pending' AND expires_at < NOW()",
        [],
    )
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute(
            "UPDATE approval_requests SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()",
            [],
        )
    return n
