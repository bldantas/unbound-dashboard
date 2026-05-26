"""
Admin audit log — trilha geral de ações administrativas.

Diferente do `audit_service` (V5, só updates/restores), esta camada
registra qualquer ação relevante: login, edits de config, CRUD de hosts,
exports de dados, etc. Tudo passa por `log()` — falha silenciosa pra
nunca bloquear o caller.

Lista pra UI com filtros (categoria, actor, janela temporal), export CSV
e LGPD report (queries por IP cliente em janela X).
"""

from __future__ import annotations

import csv
import io
import json
import time
from datetime import datetime
from typing import Any

import structlog

from app.repositories.duckdb import settings_repo
from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)


async def log(
    *,
    actor_id: int | None,
    actor_username: str | None,
    actor_ip: str | None,
    action: str,
    category: str,
    target_type: str | None = None,
    target_id: str | None = None,
    details: dict[str, Any] | None = None,
) -> None:
    """Append-only. Falha silenciosa (warning no log estruturado)."""
    try:
        await db_execute(
            """
            INSERT INTO admin_audit
                (actor_id, actor_username, actor_ip, action, category,
                 target_type, target_id, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            """,
            [
                actor_id,
                actor_username[:64] if actor_username else None,
                actor_ip[:64] if actor_ip else None,
                action[:80],
                category[:32],
                target_type[:40] if target_type else None,
                target_id[:80] if target_id else None,
                json.dumps(details) if details is not None else None,
            ],
        )
    except Exception as exc:  # noqa: BLE001
        log.warning("admin_audit.log_failed", action=action, error=str(exc))


def _row_to_dict(r: dict) -> dict[str, Any]:
    details = r.get("details")
    if isinstance(details, str):
        try:
            details = json.loads(details)
        except Exception:  # noqa: BLE001
            pass
    ts = r.get("created_at")
    return {
        "id": int(r["id"]),
        "created_at": ts.isoformat() if isinstance(ts, datetime) else str(ts) if ts else None,
        "actor_id": r.get("actor_id"),
        "actor_username": r.get("actor_username") or "?",
        "actor_ip": r.get("actor_ip"),
        "action": r.get("action"),
        "category": r.get("category"),
        "target_type": r.get("target_type"),
        "target_id": r.get("target_id"),
        "details": details,
    }


async def list_filtered(
    *,
    category: str | None = None,
    actor_id: int | None = None,
    action_prefix: str | None = None,
    from_ts: int | None = None,
    to_ts: int | None = None,
    limit: int = 100,
    offset: int = 0,
) -> dict:
    where = ["1=1"]
    params: list = []
    if category:
        where.append("category = ?")
        params.append(category)
    if actor_id is not None:
        where.append("actor_id = ?")
        params.append(int(actor_id))
    if action_prefix:
        where.append("action LIKE ?")
        params.append(f"{action_prefix}%")
    if from_ts is not None:
        where.append("created_at >= to_timestamp(?)")
        params.append(int(from_ts))
    if to_ts is not None:
        where.append("created_at <= to_timestamp(?)")
        params.append(int(to_ts))
    where_sql = " AND ".join(where)

    total_row = await db_fetchone(
        f"SELECT COUNT(*) AS n FROM admin_audit WHERE {where_sql}", params
    )
    total = int(total_row["n"]) if total_row else 0

    rows = await db_fetchall(
        f"""
        SELECT id, created_at, actor_id, actor_username, actor_ip,
               action, category, target_type, target_id, details
        FROM admin_audit
        WHERE {where_sql}
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
        """,
        params + [int(limit), int(offset)],
    )
    return {
        "items": [_row_to_dict(r) for r in rows],
        "total": total,
        "limit": limit,
        "offset": offset,
    }


async def export_csv(**filters: Any) -> str:
    """Mesmos filtros de list_filtered, mas sem paginação (cap 10k linhas)."""
    filters.pop("limit", None)
    filters.pop("offset", None)
    out = await list_filtered(limit=10000, offset=0, **filters)
    buf = io.StringIO()
    w = csv.writer(buf)
    w.writerow([
        "id", "created_at", "actor_id", "actor_username", "actor_ip",
        "action", "category", "target_type", "target_id", "details",
    ])
    for it in out["items"]:
        details = it.get("details")
        if isinstance(details, (dict, list)):
            details = json.dumps(details, ensure_ascii=False)
        w.writerow([
            it["id"], it["created_at"], it["actor_id"], it["actor_username"],
            it["actor_ip"], it["action"], it["category"],
            it["target_type"] or "", it["target_id"] or "", details or "",
        ])
    return buf.getvalue()


async def prune_old(days: int) -> int:
    """DELETE de entries com created_at < NOW() - N dias."""
    row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM admin_audit WHERE created_at < NOW() - (INTERVAL '1 day' * ?)",
        [int(days)],
    )
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute(
            "DELETE FROM admin_audit WHERE created_at < NOW() - (INTERVAL '1 day' * ?)",
            [int(days)],
        )
    return n


# ---------- LGPD report ----------

async def lgpd_report(client_ip: str, hours: int = 24, limit: int = 5000) -> dict:
    """Retorna queries DNS feitas por um IP cliente nas últimas N horas.

    Acesso a dados pessoais — quem chama este endpoint é registrado no
    admin_audit pela camada de router (action='compliance.lgpd_report').
    """
    cutoff = int(time.time()) - (hours * 3600)
    rows = await db_fetchall(
        """
        SELECT timestamp, client_ip, query_type, domain, action
        FROM query_logs
        WHERE client_ip = ? AND timestamp >= ?
        ORDER BY timestamp DESC
        LIMIT ?
        """,
        [client_ip, cutoff, int(limit)],
    )
    items = []
    for r in rows:
        items.append({
            "timestamp": int(r.get("timestamp") or 0),
            "client_ip": r.get("client_ip"),
            "query_type": r.get("query_type"),
            "domain": r.get("domain"),
            "action": r.get("action"),
        })
    return {
        "client_ip": client_ip,
        "hours": hours,
        "cutoff": cutoff,
        "total": len(items),
        "truncated": len(items) >= int(limit),
        "items": items,
    }


def lgpd_report_csv(report: dict) -> str:
    buf = io.StringIO()
    w = csv.writer(buf)
    w.writerow(["timestamp_iso", "client_ip", "query_type", "domain", "action"])
    for it in report.get("items", []):
        ts = it.get("timestamp") or 0
        iso = datetime.fromtimestamp(ts).isoformat() if ts else ""
        w.writerow([
            iso, it.get("client_ip", ""), it.get("query_type", ""),
            it.get("domain", ""), it.get("action", ""),
        ])
    return buf.getvalue()
