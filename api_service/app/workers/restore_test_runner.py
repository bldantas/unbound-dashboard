"""
RestoreTestRunner — automatiza restore-test S3 (v2.68) numa cadência fixa.

Tick: 1x/semana (configurável via setting `backup_s3_restore_test_interval_hours`,
default 168). Pula se backup S3 não está configurado.

Resultado fica em settings (`backup_s3_last_restore_test_*`), mesmas chaves
que o endpoint manual usa — UI exibe normalmente.
"""

from __future__ import annotations

import asyncio
from datetime import UTC, datetime

import structlog

from app.core.metrics import worker_errors
from app.repositories.duckdb import settings_repo
from app.services import backup_offsite_service as svc

log = structlog.get_logger(__name__)

DEFAULT_INTERVAL_HOURS = 168  # 1x/semana
INITIAL_DELAY_SECONDS = 1800  # 30min após boot — não roda junto com outros


class RestoreTestRunner:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY_SECONDS)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("restore_test_runner.cycle_failed", error=str(exc))
                worker_errors.labels(worker="restore_test_runner").inc()
            interval_h = await settings_repo.get_int(
                "backup_s3_restore_test_interval_hours", DEFAULT_INTERVAL_HOURS
            )
            interval_h = max(1, min(720, interval_h))
            slept = 0
            target = interval_h * 3600
            while self._running and slept < target:
                await asyncio.sleep(min(60, target - slept))
                slept += 60

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> dict:
        enabled = await settings_repo.get_bool("backup_s3_restore_test_enabled", False)
        if not enabled:
            log.debug("restore_test_runner.disabled")
            return {"skipped": "disabled"}

        cfg = await svc.load_config()
        if not cfg.get("backup_s3_bucket"):
            return {"skipped": "no bucket"}

        loop = asyncio.get_running_loop()
        result = await loop.run_in_executor(None, svc.restore_test, cfg, None)

        ts_iso = datetime.now(UTC).isoformat(timespec="seconds")
        await settings_repo.bulk_upsert([
            {"setting_key": "backup_s3_last_restore_test_at", "setting_value": ts_iso},
            {"setting_key": "backup_s3_last_restore_test_ok",
             "setting_value": "1" if result.get("success") else "0"},
            {"setting_key": "backup_s3_last_restore_test_error",
             "setting_value": str(result.get("error") or "")},
            {"setting_key": "backup_s3_last_restore_test_key",
             "setting_value": str(result.get("key") or "")},
        ])
        log.info(
            "restore_test_runner.completed",
            ok=result.get("success"),
            key=result.get("key"),
            error=result.get("error"),
        )
        return result
