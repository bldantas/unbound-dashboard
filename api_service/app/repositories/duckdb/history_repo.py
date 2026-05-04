"""Queries de histórico — alimentam GET /api/v1/history/summary (substitui PDO em history.php)."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_fetchall


async def top_domains_24h(limit: int = 10) -> list[dict]:
    """Top N domínios consultados nas últimas 24h (qualquer action)."""
    return await db_fetchall(
        """
        SELECT domain, COUNT(*) AS count
        FROM query_logs
        WHERE timestamp >= (epoch(now()) - 86400)::INTEGER
        GROUP BY domain
        ORDER BY count DESC
        LIMIT ?
        """,
        [limit],
    )


async def recent_queries(limit: int) -> list[dict]:
    """Últimas N consultas (todas as actions), com category do blocklist via LEFT JOIN."""
    return await db_fetchall(
        """
        SELECT q.timestamp, q.client_ip, q.domain, q.query_type, q.action,
               b.category
        FROM (
            SELECT timestamp, client_ip, domain, query_type, action
            FROM query_logs
            ORDER BY timestamp DESC
            LIMIT ?
        ) q
        LEFT JOIN blocklist_domains b ON q.domain = b.domain
        """,
        [limit],
    )
