"""
AuditPruner — apaga entries antigas do admin_audit por retenção configurável.

Tick: 1x/24h. Setting `audit_retention_days` (default 365, range 30..3650).
Trail crítico — default conservador (1 ano) e mínimo de 30 dias.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import settings_repo
from app.services import admin_audit_service

log = structlog.get_logger(__name__)

PRUNE_INTERVAL = 86400  # 1x/dia
DEFAULT_RETENTION_DAYS = 365


class AuditPruner:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        # Aguarda 15min após boot pra não competir com outros pruners
        await asyncio.sleep(900)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("audit_pruner.cycle_failed", error=str(exc))
                worker_errors.labels(worker="audit_pruner").inc()
            await asyncio.sleep(PRUNE_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        days = await settings_repo.get_int("audit_retention_days", DEFAULT_RETENTION_DAYS)
        days = max(30, min(3650, days))
        deleted = await admin_audit_service.prune_old(days)
        ts_iso = datetime.now(UTC).isoformat(timespec="seconds")
        await settings_repo.bulk_upsert([
            {"setting_key": "audit_pruner_last_run", "setting_value": ts_iso},
            {"setting_key": "audit_pruner_last_deleted", "setting_value": str(deleted)},
        ])
        log.info("audit_pruner.completed", deleted=deleted, retention_days=days)
        return {"deleted": deleted, "retention_days": days}

    async def run_now(self) -> dict:
        return await self._run_once()
