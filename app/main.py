from contextlib import asynccontextmanager

import asyncio
import structlog
from fastapi import FastAPI
from slowapi import _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded

from app.core.config import settings
from app.metrics import setup_metrics
from app.middleware import add_cors, limiter, RequestIdMiddleware
from app.routers import (
    alerts,
    auth,
    blocklist,
    diagnostics,
    health,
    network,
    settings as settings_router,
    source_balance,
    stats,
    unbound,
    ws_logs,
)

log = structlog.get_logger()

_background_tasks: list[asyncio.Task] = []


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup
    log.info("unbound-dashboard v2 iniciando", db_path=settings.db_path)

    # Aplica migrations DuckDB automaticamente no startup
    from app.db import run_migrations
    run_migrations()

    # Inicia workers em background
    from app.workers import AlertChecker, JsonExporter, LogWatcher, StatsAggregator

    watcher = LogWatcher()
    aggregator = StatsAggregator()
    checker = AlertChecker()
    exporter = JsonExporter()

    _background_tasks.extend([
        asyncio.create_task(watcher.start(), name="log_watcher"),
        asyncio.create_task(aggregator.start(), name="stats_aggregator"),
        asyncio.create_task(checker.start(), name="alert_checker"),
        asyncio.create_task(exporter.start(), name="json_exporter"),
    ])

    log.info("workers iniciados, API pronta")
    yield

    # Shutdown — cancela workers
    log.info("unbound-dashboard v2 encerrando")
    await watcher.stop()
    await aggregator.stop()
    await checker.stop()
    await exporter.stop()

    for task in _background_tasks:
        task.cancel()
    await asyncio.gather(*_background_tasks, return_exceptions=True)
    _background_tasks.clear()


app = FastAPI(
    title="Unbound Dashboard API",
    version="2.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
    lifespan=lifespan,
)

# Middlewares
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)
app.add_middleware(RequestIdMiddleware)
add_cors(app)

# Métricas Prometheus em GET /metrics
setup_metrics(app)

app.include_router(auth.router)
app.include_router(stats.router)
app.include_router(alerts.router)
app.include_router(settings_router.router)
app.include_router(blocklist.router)
app.include_router(diagnostics.router)
app.include_router(health.router)
app.include_router(network.router)
app.include_router(unbound.router)
app.include_router(source_balance.router)
app.include_router(ws_logs.router)


@app.get("/healthz", tags=["health"])
async def healthz() -> dict:
    return {"status": "ok", "version": "2.0.0"}
