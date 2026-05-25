"""
FastAPI app da modernização v1 do Unbound Dashboard.

Servido por Uvicorn em 127.0.0.1:8001. Apache faz reverse proxy de /api/v1/*
para este processo. Endpoints PHP legados em /var/www/html/unbound-dashboard/api/*.php
continuam servidos pelo Apache durante a transição (ver docs/PLANO_MODERNIZACAO_V1.md).
"""

from __future__ import annotations

import asyncio
from contextlib import asynccontextmanager

import structlog
from fastapi import FastAPI
from slowapi import _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded

from app.core.config import settings
from app.core.metrics import setup_metrics
from app.core.rate_limit import limiter
from app.db import run_migrations
from app.routers import (
    alerts,
    analytics,
    api_tokens,
    audit,
    auth,
    backup_offsite,
    blocklist,
    exports,
    health,
    history,
    host,
    hosts,
    policies,
    stats,
    threats,
    updates,
    users,
    webhooks,
)
from app.routers import unbound as unbound_router
from app.workers import (
    AlertChecker,
    AnomalyDetector,
    BackupUploader,
    BlocklistSyncer,
    HostPoller,
    LogWatcher,
    StatsAggregator,
    UnboundCollector,
    UpdateChecker,
)

log = structlog.get_logger()

_background_tasks: list[asyncio.Task] = []
_SUPERVISOR_BACKOFF_INITIAL = 1.0
_SUPERVISOR_BACKOFF_MAX = 60.0


async def _supervised(name: str, worker) -> None:
    """
    Roda worker.start() reiniciando com backoff exponencial em caso de crash.

    Encerra graciosamente se start() retornar normalmente (ex: após
    worker.stop() externo). CancelledError é re-lançado para shutdown limpo
    via task.cancel().
    """
    backoff = _SUPERVISOR_BACKOFF_INITIAL
    while True:
        try:
            await worker.start()
            log.info("worker.exited_gracefully", name=name)
            return
        except asyncio.CancelledError:
            log.info("worker.cancelled", name=name)
            raise
        except Exception as exc:  # noqa: BLE001
            log.error("worker.crashed", name=name, error=str(exc), backoff_s=backoff)
            await asyncio.sleep(backoff)
            backoff = min(backoff * 2, _SUPERVISOR_BACKOFF_MAX)


@asynccontextmanager
async def lifespan(app: FastAPI):
    log.info(
        "unbound-dashboard-api iniciando",
        version=settings.api_version,
        db_path=settings.db_path,
    )

    # Migrations DuckDB idempotentes
    run_migrations()

    # Rehidrata Redis com sessões persistidas no DuckDB (sobreviver restart Redis)
    try:
        from app.services.sessions import bootstrap_from_duckdb
        await bootstrap_from_duckdb()
    except Exception as exc:  # noqa: BLE001
        log.warning("sessions.bootstrap_failed", error=str(exc))

    # Workers em background — supervisionados (restart on crash, backoff exponencial)
    log_watcher = LogWatcher(log_path=settings.unbound_log)
    stats_aggregator = StatsAggregator()
    alert_checker = AlertChecker()
    unbound_collector = UnboundCollector()
    update_checker = UpdateChecker()
    host_poller = HostPoller()
    blocklist_syncer_worker = BlocklistSyncer()
    anomaly_detector = AnomalyDetector()
    backup_uploader = BackupUploader()
    _background_tasks.extend(
        [
            asyncio.create_task(_supervised("log_watcher", log_watcher), name="log_watcher"),
            asyncio.create_task(
                _supervised("stats_aggregator", stats_aggregator), name="stats_aggregator"
            ),
            asyncio.create_task(_supervised("alert_checker", alert_checker), name="alert_checker"),
            asyncio.create_task(
                _supervised("unbound_collector", unbound_collector), name="unbound_collector"
            ),
            asyncio.create_task(
                _supervised("update_checker", update_checker), name="update_checker"
            ),
            asyncio.create_task(
                _supervised("host_poller", host_poller), name="host_poller"
            ),
            asyncio.create_task(
                _supervised("blocklist_syncer", blocklist_syncer_worker), name="blocklist_syncer"
            ),
            asyncio.create_task(
                _supervised("anomaly_detector", anomaly_detector), name="anomaly_detector"
            ),
            asyncio.create_task(
                _supervised("backup_uploader", backup_uploader), name="backup_uploader"
            ),
        ]
    )
    log.info("workers iniciados, API pronta")

    yield

    # Shutdown: sinaliza stop, cancela tasks, drena
    log.info("unbound-dashboard-api encerrando")
    await log_watcher.stop()
    await stats_aggregator.stop()
    await alert_checker.stop()
    await unbound_collector.stop()
    await update_checker.stop()
    await host_poller.stop()
    await blocklist_syncer_worker.stop()
    await anomaly_detector.stop()
    await backup_uploader.stop()
    for task in _background_tasks:
        task.cancel()
    await asyncio.gather(*_background_tasks, return_exceptions=True)
    _background_tasks.clear()

    # Fecha conexão Redis singleton (denylist JWT, etc)
    from app.infrastructure.redis_client import close_redis
    await close_redis()


app = FastAPI(
    title="Unbound Dashboard API (v1 modernization)",
    description="Backend Python da v1. Apache proxia /api/v1/* para cá.",
    version=settings.api_version,
    docs_url="/api/v1/docs",
    redoc_url="/api/v1/redoc",
    openapi_url="/api/v1/openapi.json",
    lifespan=lifespan,
)

# Rate limiting (slowapi) — shared limiter instance + handler 429
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

# Prometheus metrics em /metrics (sem auth — scraper precisa acesso livre)
setup_metrics(app)

app.include_router(alerts.router)
app.include_router(analytics.router)
app.include_router(api_tokens.router)
app.include_router(audit.router)
app.include_router(auth.router)
app.include_router(backup_offsite.router)
app.include_router(blocklist.router)
app.include_router(exports.router)
app.include_router(health.router)
app.include_router(history.router)
app.include_router(host.router)
app.include_router(hosts.router)
app.include_router(policies.router)
app.include_router(stats.router)
app.include_router(threats.router)
app.include_router(unbound_router.router)
app.include_router(updates.router)
app.include_router(users.router)
app.include_router(webhooks.router)
