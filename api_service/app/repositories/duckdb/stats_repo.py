"""Queries analíticas de estatísticas DNS sobre query_logs (DuckDB OLAP)."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_fetchall, db_fetchone


async def totals_window(window_seconds: int) -> dict[str, int]:
    """COUNT total/blocked/resolved nas últimas window_seconds."""
    row = await db_fetchone(
        """
        SELECT
            COUNT(*)                                          AS total,
            COUNT(*) FILTER (WHERE action = 'blocked')        AS blocked,
            COUNT(*) FILTER (WHERE action = 'resolved')       AS resolved
        FROM query_logs
        WHERE timestamp >= (epoch(now()) - ?)::INTEGER
        """,
        [window_seconds],
    )
    if not row:
        return {"total": 0, "blocked": 0, "resolved": 0}
    return {k: int(v or 0) for k, v in row.items()}


async def top_blocked_domains(window_seconds: int, limit: int = 10) -> list[dict]:
    return await db_fetchall(
        """
        SELECT domain, COUNT(*) AS hits
        FROM query_logs
        WHERE action = 'blocked'
          AND timestamp >= (epoch(now()) - ?)::INTEGER
        GROUP BY domain
        ORDER BY hits DESC
        LIMIT ?
        """,
        [window_seconds, limit],
    )


async def top_clients(window_seconds: int, limit: int = 10) -> list[dict]:
    return await db_fetchall(
        """
        SELECT client_ip, COUNT(*) AS hits
        FROM query_logs
        WHERE timestamp >= (epoch(now()) - ?)::INTEGER
        GROUP BY client_ip
        ORDER BY hits DESC
        LIMIT ?
        """,
        [window_seconds, limit],
    )
