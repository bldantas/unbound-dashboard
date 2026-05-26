"""
Endpoints /api/v1/observability — saúde do daemon e dos workers.

Compõe dados existentes (não duplica): /unbound/stats já entrega snapshot
do daemon; aqui adicionamos a série temporal de 1h gerada pelo
UnboundCollector (`src/data/time_series.json`) e um status agregado de
todos os workers (last_run/erro/etc).
"""

from __future__ import annotations

import asyncio
import json
from datetime import UTC, datetime
from pathlib import Path
from typing import Annotated

from fastapi import APIRouter, Depends, Request

from app.core.deps import require_capability

router = APIRouter(prefix="/api/v1/observability", tags=["observability"])

TIME_SERIES_PATH = Path("/var/www/html/unbound-dashboard/src/data/time_series.json")


@router.get("/time-series")
async def get_time_series(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    """
    Série temporal de até 60 samples (1h, 1/min) escrita pelo UnboundCollector.
    Inclui latência média/mediana, QPS, hits/miss, secure/bogus.
    """
    if not TIME_SERIES_PATH.exists():
        return {"samples": []}
    try:
        data = json.loads(TIME_SERIES_PATH.read_text(encoding="utf-8"))
        samples = data.get("samples", [])
        return {"count": len(samples), "samples": samples}
    except (OSError, json.JSONDecodeError) as exc:
        return {"samples": [], "error": str(exc)}


@router.get("/workers")
async def get_workers_status(
    request: Request,
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    """
    Status agregado dos workers. Combina:
      - tasks vivas (do app.state via lifespan)
      - last_run conhecido (settings ou tabelas próprias)
      - próximas execuções estimadas (best-effort, baseado no tick)
    """
    from app.repositories.duckdb import settings_repo
    from app.repositories.duckdb.connection import db_fetchall, db_fetchone

    # 1. Snapshot das tasks asyncio (do lifespan)
    task_status: dict[str, str] = {}
    for task in asyncio.all_tasks():
        name = task.get_name()
        if task.done():
            task_status[name] = "done"
        else:
            task_status[name] = "running"

    # 2. Coleta last_run + metadata best-effort por worker
    async def _stat(key: str) -> str | None:
        return await settings_repo.get(key)

    last_backup = await _stat("backup_s3_last_upload_at")
    last_prune_run = await _stat("query_log_pruner_last_run")
    last_prune_deleted = await _stat("query_log_pruner_last_deleted")

    # blocklist syncs — pega o mais recente entre todas as sources ativas
    bl_row = await db_fetchone(
        "SELECT MAX(last_sync) AS ls FROM blocklist_sources WHERE index_enabled = true OR block_enabled = true"
    )
    last_bl_sync = bl_row["ls"].isoformat() if bl_row and bl_row.get("ls") else None

    # managed_hosts — mais recente polled
    mh_row = await db_fetchone(
        "SELECT MAX(last_polled_at) AS lp FROM managed_hosts"
    )
    last_host_poll = mh_row["lp"].isoformat() if mh_row and mh_row.get("lp") else None

    # unbound collector — usa o arquivo latest_stats.json
    latest_stats_path = Path("/var/www/html/unbound-dashboard/data/latest_stats.json")
    last_collector = None
    if latest_stats_path.exists():
        try:
            data = json.loads(latest_stats_path.read_text(encoding="utf-8"))
            ts = data.get("timestamp")
            if ts:
                last_collector = datetime.fromtimestamp(int(ts), tz=UTC).isoformat()
        except (OSError, json.JSONDecodeError, ValueError):
            pass

    # anomalies — mais recente
    anomaly_row = await db_fetchone(
        "SELECT MAX(started_at) AS la FROM alerts WHERE type LIKE 'anomaly_%'"
    )
    last_anomaly = anomaly_row["la"].isoformat() if anomaly_row and anomaly_row.get("la") else None

    # alerts table — total ativos
    alerts_row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM alerts WHERE resolved_at IS NULL"
    )
    active_alerts = int(alerts_row["n"] or 0) if alerts_row else 0

    workers = [
        {
            "name": "log_watcher",
            "tick_seconds": 1,
            "status": task_status.get("log_watcher", "unknown"),
            "description": "Tail do log do Unbound → query_logs (DuckDB)",
            "last_run": None,  # contínuo
        },
        {
            "name": "stats_aggregator",
            "tick_seconds": 60,
            "status": task_status.get("stats_aggregator", "unknown"),
            "description": "Recomputa daily_stats + hourly_stats",
            "last_run": None,  # roda 1x/min, sem trace persistente
        },
        {
            "name": "alert_checker",
            "tick_seconds": 60,
            "status": task_status.get("alert_checker", "unknown"),
            "description": "Avalia thresholds e abre/fecha alertas",
            "extra": {"active_alerts": active_alerts},
        },
        {
            "name": "unbound_collector",
            "tick_seconds": 60,
            "status": task_status.get("unbound_collector", "unknown"),
            "description": "Coleta unbound-control → latest_stats.json + time_series",
            "last_run": last_collector,
        },
        {
            "name": "update_checker",
            "tick_seconds": 3600,
            "status": task_status.get("update_checker", "unknown"),
            "description": "Checa releases novos no GitHub",
        },
        {
            "name": "host_poller",
            "tick_seconds": 60,
            "status": task_status.get("host_poller", "unknown"),
            "description": "Polleia managed_hosts (multi-host)",
            "last_run": last_host_poll,
        },
        {
            "name": "blocklist_syncer",
            "tick_seconds": 3600,
            "status": task_status.get("blocklist_syncer", "unknown"),
            "description": "Baixa fontes ativas de blocklist multi-source",
            "last_run": last_bl_sync,
        },
        {
            "name": "anomaly_detector",
            "tick_seconds": 300,
            "status": task_status.get("anomaly_detector", "unknown"),
            "description": "DGA / NXDOMAIN spike / cliente novo (heurístico)",
            "last_run": last_anomaly,
        },
        {
            "name": "backup_uploader",
            "tick_seconds": 3600,
            "status": task_status.get("backup_uploader", "unknown"),
            "description": "Upload DuckDB+configs pra S3-compatible",
            "last_run": last_backup,
        },
        {
            "name": "query_log_pruner",
            "tick_seconds": 3600,
            "status": task_status.get("query_log_pruner", "unknown"),
            "description": "Deleta query_logs > retention_days",
            "last_run": last_prune_run,
            "extra": {"last_deleted": int(last_prune_deleted or 0)},
        },
    ]

    return {
        "now": datetime.now(UTC).isoformat(),
        "workers": workers,
        "summary": {
            "total": len(workers),
            "running": sum(1 for w in workers if w["status"] == "running"),
        },
    }
