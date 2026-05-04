"""Repository de alerts."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone


async def list_history(limit: int = 100) -> list[dict]:
    """
    Lista alerts ordenados por started_at DESC + duration em segundos.
    Espelho de AlertManager::getHistory() (PHP).
    """
    return await db_fetchall(
        """
        SELECT id, type, severity, message, started_at, resolved_at, is_dismissed,
               EXTRACT(EPOCH FROM (COALESCE(resolved_at, NOW()) - started_at))::BIGINT
                   AS duration_secs
        FROM alerts
        ORDER BY started_at DESC
        LIMIT ?
        """,
        [limit],
    )


async def count_active() -> int:
    row = await db_fetchone("SELECT COUNT(*) AS n FROM alerts WHERE resolved_at IS NULL")
    return int(row["n"]) if row else 0


async def resolve_by_id(alert_id: int) -> bool:
    """Marca alerta específico como resolvido (se ainda ativo). Retorna se mudou."""
    row = await db_fetchone(
        "SELECT id FROM alerts WHERE id = ? AND resolved_at IS NULL",
        [alert_id],
    )
    if not row:
        return False
    await db_execute(
        "UPDATE alerts SET resolved_at = NOW() WHERE id = ? AND resolved_at IS NULL",
        [alert_id],
    )
    return True


async def clear_resolved() -> int:
    """DELETE alertas já resolvidos. Retorna quantos foram apagados."""
    row = await db_fetchone("SELECT COUNT(*) AS n FROM alerts WHERE resolved_at IS NOT NULL")
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute("DELETE FROM alerts WHERE resolved_at IS NOT NULL", [])
    return n
