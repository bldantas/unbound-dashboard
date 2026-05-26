"""
QueryLogPruner — deleta linhas antigas de query_logs por retention configurável.

Tick: 1x/h. Settings:
  - `query_log_retention_enabled` (default "1")
  - `query_log_retention_days` (default "90", min 7)

DuckDB não tem partitioning nativo eficiente pra deletes; um simples
DELETE WHERE timestamp < cutoff é suficiente — zonemap por timestamp já
poda os blocks.

Idempotente. Estado pós-poda fica em settings (`query_log_pruner_last_*`)
pra a UI poder mostrar última execução / linhas removidas.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import settings_repo
from app.repositories.duckdb.connection import db_execute, db_fetchone

log = structlog.get_logger(__name__)

PRUNE_INTERVAL = 3600  # 1h
DEFAULT_RETENTION_DAYS = 90
MIN_RETENTION_DAYS = 7


class QueryLogPruner:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        # Aguarda 5min após boot pra não correr junto com outros workers
        await asyncio.sleep(300)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("query_log_pruner.cycle_failed", error=str(exc))
                worker_errors.labels(worker="query_log_pruner").inc()
            await asyncio.sleep(PRUNE_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        enabled = await settings_repo.get_bool("query_log_retention_enabled", True)
        if not enabled:
            log.debug("query_log_pruner.disabled")
            return {"deleted": 0, "skipped": "disabled"}

        days = await settings_repo.get_int("query_log_retention_days", DEFAULT_RETENTION_DAYS)
        if days < MIN_RETENTION_DAYS:
            days = MIN_RETENTION_DAYS

        cutoff = int(datetime.now(UTC).timestamp()) - (days * 86400)
        before_row = await db_fetchone(
            "SELECT COUNT(*) AS n FROM query_logs WHERE timestamp < ?", [cutoff]
        )
        to_delete = int(before_row["n"] or 0) if before_row else 0

        if to_delete > 0:
            await db_execute("DELETE FROM query_logs WHERE timestamp < ?", [cutoff])

        # Persiste status na settings table — UI exibe "última execução"
        ts_iso = datetime.now(UTC).isoformat(timespec="seconds")
        await settings_repo.bulk_upsert(
            [
                {"setting_key": "query_log_pruner_last_run", "setting_value": ts_iso},
                {"setting_key": "query_log_pruner_last_deleted", "setting_value": str(to_delete)},
                {"setting_key": "query_log_pruner_last_cutoff", "setting_value": str(cutoff)},
            ]
        )

        log.info(
            "query_log_pruner.completed",
            deleted=to_delete,
            retention_days=days,
            cutoff=cutoff,
        )
        return {"deleted": to_delete, "retention_days": days, "cutoff": cutoff}

    async def run_now(self) -> dict:
        """Dispara prune imediato — chamado pelo endpoint POST /retention/prune-now."""
        return await self._run_once()
