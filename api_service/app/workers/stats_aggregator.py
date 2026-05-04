"""
StatsAggregator — agrega query_logs do dia em daily_stats (DuckDB).

Substitui PARCIALMENTE `scripts/aggregate_stats.php` da v1. O PHP original
faz duas coisas:
  1. Lê `unbound-control stats_noreset` e escreve `data/latest_stats.json`
     (métricas em tempo real do daemon — uptime, cache, memória).
  2. Atualiza daily_stats.total_queries / blocked_count agregando query_logs.

Este worker faz APENAS (2). O (1) — métricas do daemon Unbound — continua em
PHP por enquanto, porque não depende dos dados em DuckDB. Será portado depois.

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
