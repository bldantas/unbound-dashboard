"""
Audit log de updates/restores aplicados via UI.

Cada operação registra:
  - INSERT no início (status=running)
  - UPDATE ao monitor concluir (status final, finished_at)

Lista pra UI: ordem cronológica reversa, com tempo de duração calculado.
"""

from __future__ import annotations

import time
from typing import Any

import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall

log = structlog.get_logger(__name__)


async def record_start(
    *,
    job_id: str,
    kind: str,  # 'update' | 'restore'
    user_id: int | None,
    username: str | None,
    ip: str | None,
    from_version: str,
    to_version: str,
    backup_timestamp: str | None = None,
    acknowledge_breaking: bool = False,
) -> None:
    """Grava entry inicial. Falha silenciosa — não bloqueia o caller."""
    try:
        await db_execute(
            """
            INSERT INTO update_audit
                (job_id, kind, user_id, username, ip, from_version, to_version,
                 backup_timestamp, acknowledge_breaking, status, started_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'running', ?)
            """,
            [
                job_id, kind, user_id, username, ip[:64] if ip else None,
                from_version, to_version, backup_timestamp,
                bool(acknowledge_breaking), int(time.time()),
            ],
        )
    except Exception as exc:  # noqa: BLE001
        log.warning("audit.record_start_failed", job_id=job_id, error=str(exc))


async def record_finish(job_id: str, status: str) -> None:
    """Atualiza entry com status final + finished_at. Idempotente."""
    try:
        await db_execute(
            "UPDATE update_audit SET status = ?, finished_at = ? WHERE job_id = ?",
            [status, int(time.time()), job_id],
        )
    except Exception as exc:  # noqa: BLE001
        log.warning("audit.record_finish_failed", job_id=job_id, error=str(exc))


async def list_recent(limit: int = 50) -> list[dict[str, Any]]:
    """Últimas N entries, mais recente primeiro."""
    rows = await db_fetchall(
        """
        SELECT id, job_id, kind, user_id, username, ip,
               from_version, to_version, backup_timestamp,
               acknowledge_breaking, status, started_at, finished_at
        FROM update_audit
        ORDER BY started_at DESC
        LIMIT ?
        """,
        [limit],
    )
    out = []
    for r in rows:
        started = int(r.get("started_at") or 0)
        finished = r.get("finished_at")
        finished = int(finished) if finished is not None else None
        duration = (finished - started) if finished else None
        out.append({
            "id": int(r["id"]),
            "job_id": r["job_id"],
            "kind": r["kind"],
            "user_id": r.get("user_id"),
            "username": r.get("username") or "?",
            "ip": r.get("ip"),
            "from_version": r.get("from_version"),
            "to_version": r.get("to_version"),
            "backup_timestamp": r.get("backup_timestamp"),
            "acknowledge_breaking": bool(r.get("acknowledge_breaking", False)),
            "status": r["status"],
            "started_at": started,
            "finished_at": finished,
            "duration_seconds": duration,
        })
    return out
