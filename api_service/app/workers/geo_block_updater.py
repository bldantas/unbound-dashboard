"""
GeoBlockUpdater — atualiza diariamente os CIDRs dos países `blocked=true`.

Tick: 1x/24h. Pula execução se `geo_blocking_enabled != "1"` (evita martelar
o iwik.org se o user só está experimentando). Não chama apply() automatic —
mudanças nos CIDRs só entram em vigor no próximo apply manual (evita ciclos
de restart silenciosos).

Idempotente. Estado fica em settings (`geo_block_updater_last_*`) pra UI.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import settings_repo
from app.services import geo_blocking_service

log = structlog.get_logger(__name__)

UPDATE_INTERVAL = 24 * 3600  # 1x/dia
INITIAL_DELAY = 600  # 10min após boot — não brigar com outros workers


class GeoBlockUpdater:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("geo_block_updater.cycle_failed", error=str(exc))
                worker_errors.labels(worker="geo_block_updater").inc()
            await asyncio.sleep(UPDATE_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        enabled = (
            await settings_repo.get("geo_blocking_enabled", "0")
        ) == "1"
        if not enabled:
            log.debug("geo_block_updater.disabled")
            return {"skipped": "disabled"}

        result = await geo_blocking_service.refresh_all(only_blocked=True)
        ts_iso = datetime.now(UTC).isoformat(timespec="seconds")
        await settings_repo.bulk_upsert(
            [
                {"setting_key": "geo_block_updater_last_run", "setting_value": ts_iso},
                {"setting_key": "geo_block_updater_last_total", "setting_value": str(result.get("total", 0))},
                {"setting_key": "geo_block_updater_last_ok", "setting_value": str(result.get("successful", 0))},
            ]
        )
        log.info(
            "geo_block_updater.completed",
            total=result.get("total", 0),
            successful=result.get("successful", 0),
        )
        return result

    async def run_now(self) -> dict:
        return await self._run_once()
