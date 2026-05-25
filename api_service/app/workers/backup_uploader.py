"""
BackupUploader — worker que dispara `backup_offsite_service.upload_backup`
periodicamente quando `backup_s3_enabled='1'` e o intervalo do schedule
passou desde o último upload.

Por ser síncrono (boto3 não é nativo async), o upload roda via
`run_in_executor` — uma instância por vez, não bloqueia o loop.
"""

from __future__ import annotations

import asyncio
from datetime import datetime, timezone

import structlog

from app.repositories.duckdb import settings_repo
from app.services import backup_offsite_service as svc

log = structlog.get_logger(__name__)

CHECK_INTERVAL = 3600  # 1h
INITIAL_DELAY_SECONDS = 120


def _parse_iso(s: str | None) -> datetime | None:
    if not s:
        return None
    try:
        return datetime.fromisoformat(s)
    except ValueError:
        return None


class BackupUploader:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY_SECONDS)

        while self._running:
            try:
                await self._maybe_upload()
            except Exception as exc:  # noqa: BLE001
                log.warning("backup_uploader.unexpected_error", error=str(exc))

            slept = 0
            while self._running and slept < CHECK_INTERVAL:
                await asyncio.sleep(10)
                slept += 10

    async def stop(self) -> None:
        self._running = False

    async def _maybe_upload(self) -> None:
        enabled = await settings_repo.get_bool("backup_s3_enabled", False)
        if not enabled:
            return

        cfg = await svc.load_config()
        if not cfg.get("backup_s3_bucket"):
            return

        # Verifica schedule
        schedule_h = float(cfg.get("backup_s3_schedule_hours", "24") or "24")
        last_str = await settings_repo.get("backup_s3_last_upload_at")
        last = _parse_iso(last_str)
        if last is not None:
            elapsed_h = (datetime.now(timezone.utc) - last).total_seconds() / 3600
            if elapsed_h < schedule_h:
                return

        log.info("backup_uploader.starting", bucket=cfg["backup_s3_bucket"])
        loop = asyncio.get_running_loop()
        result = await loop.run_in_executor(None, svc.upload_backup, cfg)

        if result.get("success"):
            await svc.save_status(
                status="ok",
                error=None,
                size=result.get("size_bytes"),
                key=result.get("key"),
            )
            log.info(
                "backup_uploader.completed",
                key=result.get("key"),
                size=result.get("size_bytes"),
                retention_deleted=result.get("retention_deleted"),
            )
        else:
            await svc.save_status(status="error", error=str(result.get("error") or ""))
            log.warning("backup_uploader.failed", error=result.get("error"))
