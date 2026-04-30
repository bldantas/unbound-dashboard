from __future__ import annotations

from datetime import datetime, timezone
from typing import Optional

from app.domain.alert import Alert, AlertCreate, Severity
from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone


class AlertRepository:
    async def create(self, alert: AlertCreate) -> int:
        """Insere alerta e retorna o id gerado."""
        row = await db_fetchone(
            """
            INSERT INTO alerts (type, message, severity)
            VALUES (?, ?, ?)
            RETURNING id
            """,
            [alert.type, alert.message, alert.severity.value],
        )
        return row["id"]  # type: ignore[index]

    async def list_unread(self, limit: int = 50) -> list[Alert]:
        rows = await db_fetchall(
            "SELECT id, type, message, severity, is_read, created_at "
            "FROM alerts WHERE is_read = false ORDER BY created_at DESC LIMIT ?",
            [limit],
        )
        return [_row_to_alert(r) for r in rows]

    async def list_all(self, limit: int = 100, offset: int = 0) -> list[Alert]:
        rows = await db_fetchall(
            "SELECT id, type, message, severity, is_read, created_at "
            "FROM alerts ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [limit, offset],
        )
        return [_row_to_alert(r) for r in rows]

    async def mark_read(self, alert_id: int) -> None:
        await db_execute("UPDATE alerts SET is_read = true WHERE id = ?", [alert_id])

    async def mark_all_read(self) -> None:
        await db_execute("UPDATE alerts SET is_read = true WHERE is_read = false")

    async def delete(self, alert_id: int) -> None:
        await db_execute("DELETE FROM alerts WHERE id = ?", [alert_id])

    async def count_unread(self) -> int:
        row = await db_fetchone("SELECT COUNT(*) AS n FROM alerts WHERE is_read = false")
        return int(row["n"]) if row else 0  # type: ignore[index]


def _row_to_alert(row: dict) -> Alert:
    return Alert(
        id=row["id"],
        type=row["type"],
        message=row["message"],
        severity=Severity(row["severity"]),
        is_read=bool(row["is_read"]),
        created_at=row["created_at"],
    )
