"""
NotificationPruner — deleta alerts antigos por retention configurável.

Apaga só alerts que já estão `resolved` OU `dismissed` E cujo `started_at`
é mais velho que N dias. Alerts ativos não-dismissed nunca são apagados.

Tick: 1x/24h. Setting `notifications_retention_days` (default 30, range 1..365).
Persiste estado (`notification_pruner_last_run`, `..._last_deleted`) pra UI.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import alert_repo, settings_repo

log = structlog.get_logger(__name__)

PRUNE_INTERVAL = 86400  # 1x/dia
DEFAULT_RETENTION_DAYS = 30


class NotificationPruner:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        # Aguarda 10min após boot pra não correr junto com outros workers
        await asyncio.sleep(600)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("notification_pruner.cycle_failed", error=str(exc))
                worker_errors.labels(worker="notification_pruner").inc()
            await asyncio.sleep(PRUNE_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        days = await settings_repo.get_int("notifications_retention_days", DEFAULT_RETENTION_DAYS)
        days = max(1, min(365, days))

        deleted = await alert_repo.prune_old(days)

        ts_iso = datetime.now(UTC).isoformat(timespec="seconds")
        await settings_repo.bulk_upsert([
            {"setting_key": "notification_pruner_last_run", "setting_value": ts_iso},
            {"setting_key": "notification_pruner_last_deleted", "setting_value": str(deleted)},
        ])
        log.info("notification_pruner.completed", deleted=deleted, retention_days=days)
        return {"deleted": deleted, "retention_days": days}

    async def run_now(self) -> dict:
        return await self._run_once()
