"""
BaselineLearner — re-treina o baseline diariamente a partir de hourly_stats.

Pra cada bucket (hour_of_day × day_of_week), calcula média e stddev de:
- total_queries
- blocked_count

das últimas N semanas (setting `anomaly_baseline_window_weeks`, default 4).
Pula buckets com menos de `anomaly_baseline_min_samples` amostras
(default 3) pra evitar baselines instáveis em dados esparsos.

Tick: 1x/24h. Sem custo durante coleta — só agrega o que `hourly_stats`
já tem (que é populado pelo `StatsAggregator`).
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import settings_repo
from app.repositories.duckdb.connection import db_execute, db_fetchall

log = structlog.get_logger(__name__)

LEARN_INTERVAL = 86400
INITIAL_DELAY_SECONDS = 1500  # 25min após boot


class BaselineLearner:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY_SECONDS)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("baseline_learner.cycle_failed", error=str(exc))
                worker_errors.labels(worker="baseline_learner").inc()
            await asyncio.sleep(LEARN_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        weeks = await settings_repo.get_int("anomaly_baseline_window_weeks", 4)
        weeks = max(1, min(52, weeks))
        min_samples = await settings_repo.get_int("anomaly_baseline_min_samples", 3)
        min_samples = max(2, min_samples)

        cutoff = weeks * 7 * 86400

        # Reset table — recalculamos do zero a cada ciclo (cheap em 168 buckets)
        await db_execute("DELETE FROM anomaly_baseline", [])

        # Agrega por bucket. DuckDB DAYOFWEEK: 0=Sun..6=Sat.
        rows = await db_fetchall(
            """
            SELECT
                EXTRACT(HOUR FROM to_timestamp(hour_start)) AS hod,
                EXTRACT(DOW FROM to_timestamp(hour_start)) AS dow,
                COUNT(*) AS n,
                AVG(total_queries) AS avg_q,
                STDDEV_POP(total_queries) AS sd_q,
                AVG(blocked_count) AS avg_b,
                STDDEV_POP(blocked_count) AS sd_b
            FROM hourly_stats
            WHERE hour_start >= epoch(now()) - ?
            GROUP BY hod, dow
            HAVING COUNT(*) >= ?
            """,
            [cutoff, min_samples],
        )

        if not rows:
            log.info("baseline_learner.no_samples", weeks=weeks)
            return {"learned": 0}

        for r in rows:
            await db_execute(
                """
                INSERT INTO anomaly_baseline
                    (hour_of_day, day_of_week, sample_count,
                     avg_queries, stddev_queries, avg_blocked, stddev_blocked)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                """,
                [
                    int(r["hod"]), int(r["dow"]), int(r["n"]),
                    float(r["avg_q"] or 0), float(r["sd_q"] or 0),
                    float(r["avg_b"] or 0), float(r["sd_b"] or 0),
                ],
            )

        ts_iso = datetime.now(UTC).isoformat(timespec="seconds")
        await settings_repo.bulk_upsert([
            {"setting_key": "anomaly_baseline_last_run", "setting_value": ts_iso},
            {"setting_key": "anomaly_baseline_buckets_learned", "setting_value": str(len(rows))},
        ])
        log.info("baseline_learner.completed", buckets=len(rows), weeks=weeks)
        return {"learned": len(rows), "weeks": weeks}

    async def run_now(self) -> dict:
        return await self._run_once()
