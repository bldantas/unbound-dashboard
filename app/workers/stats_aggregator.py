"""
StatsAggregator — agrega query_logs em daily_stats diariamente.

Executa uma vez por hora: calcula os totais do dia atual a partir dos
logs brutos e faz upsert em daily_stats. Assim o endpoint de stats
históricos não precisa fazer GROUP BY sobre toda a tabela OLAP.
"""

from __future__ import annotations

import asyncio
import time
from datetime import date, datetime, timezone

import structlog

from app.domain.stats import DailyStat
from app.repositories.duckdb.connection import db_fetchone
from app.repositories.duckdb.stats_repo import StatsRepository

log = structlog.get_logger(__name__)

AGGREGATE_INTERVAL = 3600  # segundos (1 hora)


class StatsAggregator:
    def __init__(self, repo: StatsRepository | None = None) -> None:
        self._repo = repo or StatsRepository()
        self._running = False

    async def start(self) -> None:
        self._running = True
        while self._running:
            await self._aggregate_today()
            await asyncio.sleep(AGGREGATE_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _aggregate_today(self) -> None:
        today = date.today()
        day_start = int(
            datetime(today.year, today.month, today.day, tzinfo=timezone.utc).timestamp()
        )

        try:
            row = await db_fetchone(
                """
                SELECT
                    COUNT(*)                                        AS total,
                    COUNT(*) FILTER (WHERE action = 'blocked')     AS blocked,
                    COUNT(*) FILTER (WHERE action = 'resolved')    AS resolved,
                    COUNT(*) FILTER (WHERE action = 'cached')      AS cache_hits
                FROM query_logs WHERE timestamp >= ?
                """,
                [day_start],
            )
            if row is None:
                return

            stat = DailyStat(
                date=today,
                total=int(row["total"]),
                blocked=int(row["blocked"]),
                resolved=int(row["resolved"]),
                cache_hits=int(row["cache_hits"]),
            )
            await self._repo.upsert_daily_stat(stat)
            log.info(
                "stats_aggregator.done",
                date=str(today),
                total=stat.total,
                blocked=stat.blocked,
            )
        except Exception as exc:  # noqa: BLE001
            log.error("stats_aggregator.error", error=str(exc))
