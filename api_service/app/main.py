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
    approvals,
    audit,
    auth,
    backup_offsite,
    blocklist,
    cluster,
    compliance,
    dns_security,
    doh_inbound,
    exports,
    external_health,
    geo_blocking,
    geoip,
    grafana,
    ha,
    health,
    history,
    host,
    hosts,
    notifications,
    observability,
    oidc,
    organizations,
    policies,
    rate_limits,
    stats,
    threats,
    updates,
    users,
    webhooks,
    ws_notifications,
    ws_queries,
)
from app.routers import (
    secrets as secrets_router,
)
from app.routers import unbound as unbound_router
from app.workers import (
    AlertChecker,
    AnomalyDetector,
    AuditPruner,
    BackupUploader,
    BaselineLearner,
    BlocklistSyncer,
    DigestSender,
    ExternalHealthPruner,
    GeoBlockUpdater,
    HAPeerMonitor,
    HostPoller,
    LogWatcher,
    NotificationPruner,
    PrometheusExporter,
    QueryLogPruner,
    RestoreTestRunner,
    StatsAggregator,
    UnboundCollector,
    UpdateChecker,
)

log = structlog.get_logger()

_background_tasks: list[asyncio.Task] = []

# Orçamento do shutdown — somado fica bem abaixo do TimeoutStopSec=30 do unit
# (deployments/systemd/unbound-dashboard-api.service).
_SHUTDOWN_DRAIN_TIMEOUT_S = 10
_SHUTDOWN_CHECKPOINT_ATTEMPTS = 3
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

    # Aviso se SECRETS_MASTER_KEY não está configurada (cifra de OIDC/HA secrets)
    from app.services import cipher_service

    if not cipher_service.is_available():
        log.warning(
            "secrets_store.master_key_missing",
            hint="Defina SECRETS_MASTER_KEY no env pra cifrar OIDC client_secret + HA tokens. "
            "Gere: python -c "
            "'from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())'",
        )
    else:
        # Cifra secrets legacy plaintext que sobraram de pré-master-key
        try:
            from app.services.secrets_migrator import migrate_legacy_secrets

            await migrate_legacy_secrets()
        except Exception as exc:  # noqa: BLE001
            log.warning("secrets_migrator.bootstrap_failed", error=str(exc))

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
    query_log_pruner = QueryLogPruner()
    app.state.query_log_pruner = query_log_pruner  # exposto p/ endpoint "run-now"
    notification_pruner = NotificationPruner()
    app.state.notification_pruner = notification_pruner
    audit_pruner = AuditPruner()
    app.state.audit_pruner = audit_pruner
    prometheus_exporter = PrometheusExporter()
    app.state.prometheus_exporter = prometheus_exporter
    ha_peer_monitor = HAPeerMonitor()
    app.state.ha_peer_monitor = ha_peer_monitor
    external_health_pruner = ExternalHealthPruner()
    app.state.external_health_pruner = external_health_pruner
    restore_test_runner = RestoreTestRunner()
    app.state.restore_test_runner = restore_test_runner
    baseline_learner = BaselineLearner()
    app.state.baseline_learner = baseline_learner
    geo_block_updater = GeoBlockUpdater()
    app.state.geo_block_updater = geo_block_updater
    digest_sender = DigestSender()
    app.state.digest_sender = digest_sender
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
            asyncio.create_task(_supervised("host_poller", host_poller), name="host_poller"),
            asyncio.create_task(
                _supervised("blocklist_syncer", blocklist_syncer_worker), name="blocklist_syncer"
            ),
            asyncio.create_task(
                _supervised("anomaly_detector", anomaly_detector), name="anomaly_detector"
            ),
            asyncio.create_task(
                _supervised("backup_uploader", backup_uploader), name="backup_uploader"
            ),
            asyncio.create_task(
                _supervised("query_log_pruner", query_log_pruner), name="query_log_pruner"
            ),
            asyncio.create_task(
                _supervised("notification_pruner", notification_pruner), name="notification_pruner"
            ),
            asyncio.create_task(_supervised("audit_pruner", audit_pruner), name="audit_pruner"),
            asyncio.create_task(
                _supervised("prometheus_exporter", prometheus_exporter), name="prometheus_exporter"
            ),
            asyncio.create_task(
                _supervised("ha_peer_monitor", ha_peer_monitor), name="ha_peer_monitor"
            ),
            asyncio.create_task(
                _supervised("external_health_pruner", external_health_pruner),
                name="external_health_pruner",
            ),
            asyncio.create_task(
                _supervised("restore_test_runner", restore_test_runner),
                name="restore_test_runner",
            ),
            asyncio.create_task(
                _supervised("baseline_learner", baseline_learner),
                name="baseline_learner",
            ),
            asyncio.create_task(
                _supervised("geo_block_updater", geo_block_updater), name="geo_block_updater"
            ),
            asyncio.create_task(_supervised("digest_sender", digest_sender), name="digest_sender"),
        ]
    )
    log.info("workers iniciados, API pronta")

    # Job de update/restore que reiniciou a API: retoma o monitor para
    # finalizar o status e liberar o lock (ver updater.resume_running_job).
    try:
        from app.services import updater

        resumed = await updater.resume_running_job()
        if resumed is not None:
            _background_tasks.append(resumed)
    except Exception as exc:  # noqa: BLE001
        log.warning("updater.resume_failed", error=str(exc))

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
    await query_log_pruner.stop()
    await notification_pruner.stop()
    await audit_pruner.stop()
    await prometheus_exporter.stop()
    await ha_peer_monitor.stop()
    await external_health_pruner.stop()
    await restore_test_runner.stop()
    await baseline_learner.stop()
    await geo_block_updater.stop()
    await digest_sender.stop()
    for task in _background_tasks:
        task.cancel()
    # Drenagem limitada: um worker preso não pode consumir o TimeoutStopSec do
    # systemd — se estourar, o SIGKILL chega antes do CHECKPOINT abaixo.
    try:
        await asyncio.wait_for(
            asyncio.gather(*_background_tasks, return_exceptions=True),
            timeout=_SHUTDOWN_DRAIN_TIMEOUT_S,
        )
    except TimeoutError:
        log.warning("workers.drain_timeout", timeout_s=_SHUTDOWN_DRAIN_TIMEOUT_S)
    _background_tasks.clear()

    # Grava o WAL no arquivo principal antes de sair. Sem isso, o próximo
    # processo que abrir o banco (ex.: create_admin.py no install.sh, logo após
    # `systemctl stop`) precisa reproduzir o WAL, e o DuckDB 1.5.x falha nesse
    # replay quando há DDL com DEFAULT nextval()/now() (ver app/db/migrate.py::_checkpoint).
    # Retry: threads de executor que ainda terminam uma escrita fazem o
    # CHECKPOINT falhar com "other write transactions active".
    from app.repositories.duckdb.connection import db_execute

    for attempt in range(1, _SHUTDOWN_CHECKPOINT_ATTEMPTS + 1):
        try:
            await db_execute("CHECKPOINT")
            log.info("duckdb.checkpoint_on_shutdown.ok", attempt=attempt)
            break
        except Exception as exc:  # noqa: BLE001
            log.warning("duckdb.checkpoint_on_shutdown.failed", attempt=attempt, error=str(exc))
            if attempt < _SHUTDOWN_CHECKPOINT_ATTEMPTS:
                await asyncio.sleep(1)

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
app.include_router(approvals.router)
app.include_router(audit.router)
app.include_router(auth.router)
app.include_router(backup_offsite.router)
app.include_router(blocklist.router)
app.include_router(cluster.router)
app.include_router(compliance.router)
app.include_router(dns_security.router)
app.include_router(doh_inbound.router)
app.include_router(exports.router)
app.include_router(external_health.router)
app.include_router(geo_blocking.router)
app.include_router(geoip.router)
app.include_router(grafana.router)
app.include_router(ha.router)
app.include_router(health.router)
app.include_router(history.router)
app.include_router(host.router)
app.include_router(hosts.router)
app.include_router(notifications.router)
app.include_router(observability.router)
app.include_router(oidc.router)
app.include_router(organizations.router)
app.include_router(policies.router)
app.include_router(rate_limits.router)
app.include_router(secrets_router.router)
app.include_router(stats.router)
app.include_router(threats.router)
app.include_router(unbound_router.router)
app.include_router(updates.router)
app.include_router(users.router)
app.include_router(webhooks.router)
app.include_router(ws_notifications.router)
app.include_router(ws_queries.router)
