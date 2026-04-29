from __future__ import annotations

import time
from datetime import date, datetime
from typing import Optional

from app.domain.stats import DailyStat, LiveStats, QueryLog
from app.repositories.duckdb.connection import db_execute, db_executemany, db_fetchall, db_fetchone


class StatsRepository:
    async def bulk_insert_logs(self, logs: list[QueryLog]) -> None:
        """Insere múltiplos query_logs de uma vez (flush do write buffer)."""
        if not logs:
            return
        rows = [
            [l.timestamp, l.client_ip, l.domain, l.query_type, l.action]
            for l in logs
        ]
        await db_executemany(
            "INSERT INTO query_logs (timestamp, client_ip, domain, query_type, action) "
            "VALUES (?, ?, ?, ?, ?)",
            rows,
        )

    async def upsert_daily_stat(self, stat: DailyStat) -> None:
        """Cria ou atualiza a linha de stat diário."""
        await db_execute(
            """
            INSERT INTO daily_stats (date, total, blocked, resolved, cache_hits)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (date) DO UPDATE SET
                total      = excluded.total,
                blocked    = excluded.blocked,
                resolved   = excluded.resolved,
                cache_hits = excluded.cache_hits
            """,
            [stat.date, stat.total, stat.blocked, stat.resolved, stat.cache_hits],
        )

    async def get_daily_stats(self, days: int = 30) -> list[DailyStat]:
        rows = await db_fetchall(
            """
            SELECT date, total, blocked, resolved, cache_hits
            FROM daily_stats
            ORDER BY date DESC
            LIMIT ?
            """,
            [days],
        )
        return [_row_to_daily(r) for r in rows]

    async def get_live_stats(self, window_hours: int = 24) -> LiveStats:
        since_epoch = int(time.time() - window_hours * 3600)

        # Totais
        totals = await db_fetchone(
            """
            SELECT
                COUNT(*)                                        AS total,
                COUNT(*) FILTER (WHERE action = 'blocked')     AS blocked,
                COUNT(*) FILTER (WHERE action = 'resolved')    AS resolved,
                COUNT(*) FILTER (WHERE action = 'cached')      AS cache_hits
            FROM query_logs WHERE timestamp >= ?
            """,
            [since_epoch],
        )

        # Top domínios (excluindo bloqueados)
        top_domains = await db_fetchall(
            """
            SELECT domain, COUNT(*) AS hits
            FROM query_logs
            WHERE timestamp >= ? AND action != 'blocked'
            GROUP BY domain ORDER BY hits DESC LIMIT 10
            """,
            [since_epoch],
        )

        # Top clientes
        top_clients = await db_fetchall(
            """
            SELECT client_ip, COUNT(*) AS hits
            FROM query_logs WHERE timestamp >= ?
            GROUP BY client_ip ORDER BY hits DESC LIMIT 10
            """,
            [since_epoch],
        )

        t = totals or {}
        return LiveStats(
            window_hours=window_hours,
            total=int(t.get("total", 0)),
            blocked=int(t.get("blocked", 0)),
            resolved=int(t.get("resolved", 0)),
            cache_hits=int(t.get("cache_hits", 0)),
            top_domains=top_domains,
            top_clients=top_clients,
        )

    async def get_recent_logs(
        self,
        limit: int = 100,
        action: Optional[str] = None,
        domain: Optional[str] = None,
    ) -> list[QueryLog]:
        conditions = ["1=1"]
        params: list = []
        if action:
            conditions.append("action = ?")
            params.append(action)
        if domain:
            conditions.append("domain ILIKE ?")
            params.append(f"%{domain}%")
        params.append(limit)

        where = " AND ".join(conditions)
        rows = await db_fetchall(
            f"SELECT timestamp, client_ip, domain, query_type, action "
            f"FROM query_logs WHERE {where} ORDER BY timestamp DESC LIMIT ?",
            params,
        )
        return [_row_to_log(r) for r in rows]

    async def bulk_insert_blocklist_hits(self, rows: list[list]) -> None:
        """rows: [[timestamp, domain, list_name], ...]"""
        if not rows:
            return
        await db_executemany(
            "INSERT INTO blocklist_hits (timestamp, domain, list_name) VALUES (?, ?, ?)",
            rows,
        )


def _row_to_daily(row: dict) -> DailyStat:
    d = row["date"]
    if isinstance(d, datetime):
        d = d.date()
    return DailyStat(
        date=d,
        total=int(row["total"]),
        blocked=int(row["blocked"]),
        resolved=int(row["resolved"]),
        cache_hits=int(row["cache_hits"]),
    )


def _row_to_log(row: dict) -> QueryLog:
    return QueryLog(
        timestamp=int(row["timestamp"]),
        client_ip=row["client_ip"],
        domain=row["domain"],
        query_type=row["query_type"],
        action=row["action"],
    )
