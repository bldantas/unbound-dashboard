"""
ExternalHealthPruner — apaga probes antigos em external_health_probes.

Tick: 1x/24h. Setting `external_health_retention_days` (default 90, 7..3650).
Estado persistido (`external_health_pruner_last_*`) pra UI exibir.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import settings_repo
from app.services import external_health_service

log = structlog.get_logger(__name__)

PRUNE_INTERVAL = 86400
DEFAULT_RETENTION_DAYS = 90
INITIAL_DELAY_SECONDS = 1200  # 20min após boot


class ExternalHealthPruner:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY_SECONDS)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("external_health_pruner.cycle_failed", error=str(exc))
                worker_errors.labels(worker="external_health_pruner").inc()
            await asyncio.sleep(PRUNE_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        days = await settings_repo.get_int(
            "external_health_retention_days", DEFAULT_RETENTION_DAYS
        )
        days = max(7, min(3650, days))
        deleted = await external_health_service.prune_old(days)
        ts_iso = datetime.now(UTC).isoformat(timespec="seconds")
        await settings_repo.bulk_upsert([
            {"setting_key": "external_health_pruner_last_run", "setting_value": ts_iso},
            {"setting_key": "external_health_pruner_last_deleted", "setting_value": str(deleted)},
        ])
        log.info("external_health_pruner.completed", deleted=deleted, retention_days=days)
        return {"deleted": deleted, "retention_days": days}

    async def run_now(self) -> dict:
        return await self._run_once()
