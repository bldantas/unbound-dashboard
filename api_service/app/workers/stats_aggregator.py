"""
StatsAggregator — agrega query_logs em daily_stats e hourly_stats (DuckDB).

Substitui PARCIALMENTE `scripts/aggregate_stats.php` da v1. O PHP original
faz duas coisas:
  1. Lê `unbound-control stats_noreset` e escreve `data/latest_stats.json`
     (métricas em tempo real do daemon — uptime, cache, memória).
  2. Atualiza daily_stats.total_queries / blocked_count agregando query_logs.

Este worker faz APENAS (2). O (1) — métricas do daemon Unbound — continua em
PHP por enquanto, porque não depende dos dados em DuckDB. Será portado depois.

Adicionalmente (v2.39.0+), recomputa `hourly_stats` para a hora atual e a
hora anterior — granularidade fina para dashboards de observabilidade e
gráficos de "queries por hora".

Tick: 60s. Idempotente (UPSERT). Só atualiza colunas que sabemos calcular
(total_queries, blocked_count); cache_hits/cache_misses continuam sendo
populados pelo PHP no mesmo registro daily_stats.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb.connection import db_execute, db_fetchone

log = structlog.get_logger(__name__)

AGGREGATE_INTERVAL = 60  # segundos


class StatsAggregator:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        while self._running:
            try:
                await self._aggregate_today()
                await self._aggregate_recent_hours()
            except Exception as exc:  # noqa: BLE001
                # Loga e segue — supervisor restart é desnecessário pra erro pontual de query
                log.error("stats_aggregator.cycle_failed", error=str(exc))
                worker_errors.labels(worker="stats_aggregator").inc()
            await asyncio.sleep(AGGREGATE_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _aggregate_today(self) -> None:
        today = datetime.now(UTC).date()
        day_start = int(datetime(today.year, today.month, today.day, tzinfo=UTC).timestamp())
        day_end = day_start + 86400

        row = await db_fetchone(
            """
            SELECT
                COUNT(*)                                          AS total,
                COUNT(*) FILTER (WHERE action = 'blocked')        AS blocked
            FROM query_logs
            WHERE timestamp >= ? AND timestamp < ?
            """,
            [day_start, day_end],
        )
        total = int(row["total"] or 0) if row else 0
        blocked = int(row["blocked"] or 0) if row else 0

        # UPSERT: cria a linha se não existir; senão atualiza só os 2 campos.
        # cache_hits/cache_misses NÃO são tocados — PHP aggregate_stats.php
        # continua responsável por eles durante a transição.
        await db_execute(
            """
            INSERT INTO daily_stats (stat_date, total_queries, blocked_count)
            VALUES (?, ?, ?)
            ON CONFLICT (stat_date) DO UPDATE SET
                total_queries = EXCLUDED.total_queries,
                blocked_count = EXCLUDED.blocked_count
            """,
            [today, total, blocked],
        )
        log.info(
            "stats_aggregator.upserted",
            date=today.isoformat(),
            total=total,
            blocked=blocked,
        )

    async def _aggregate_recent_hours(self) -> None:
        """
        Recomputa hourly_stats da hora atual + hora anterior.
        Hora anterior fecha logo após virar a hora; a atual atualiza no tick.
        """
        now = int(datetime.now(UTC).timestamp())
        current_hour = (now // 3600) * 3600
        previous_hour = current_hour - 3600

        for hour_start in (previous_hour, current_hour):
            hour_end = hour_start + 3600
            row = await db_fetchone(
                """
                SELECT
                    COUNT(*)                                          AS total,
                    COUNT(*) FILTER (WHERE action = 'blocked')        AS blocked
                FROM query_logs
                WHERE timestamp >= ? AND timestamp < ?
                """,
                [hour_start, hour_end],
            )
            total = int(row["total"] or 0) if row else 0
            blocked = int(row["blocked"] or 0) if row else 0

            await db_execute(
                """
                INSERT INTO hourly_stats (hour_start, total_queries, blocked_count)
                VALUES (?, ?, ?)
                ON CONFLICT (hour_start) DO UPDATE SET
                    total_queries = EXCLUDED.total_queries,
                    blocked_count = EXCLUDED.blocked_count
                """,
                [hour_start, total, blocked],
            )
