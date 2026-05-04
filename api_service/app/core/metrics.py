"""
Métricas Prometheus — instrumentação automática FastAPI + counters customizados
para workers. Expostas em GET /metrics (sem auth — Prometheus scraper precisa
acessar livre via loopback/Apache).
"""

from __future__ import annotations

from prometheus_client import Counter, Gauge
from prometheus_fastapi_instrumentator import Instrumentator

# Métricas customizadas dos workers
queries_ingested = Counter(
    "unbound_queries_ingested_total",
    "Total de query_logs lidos pelo log_watcher e gravados no DuckDB",
    labelnames=["action"],
)

worker_queue_size = Gauge(
    "unbound_worker_queue_size",
    "Tamanho atual da fila do log_watcher (linhas pendentes de flush)",
)

worker_errors = Counter(
    "unbound_worker_errors_total",
    "Falhas em ciclos de workers (flush_failed, cycle_failed)",
    labelnames=["worker"],
)

duckdb_query_duration = Gauge(
    "unbound_duckdb_last_query_duration_seconds",
    "Duração da última query DuckDB (medida em writes via db_append)",
    labelnames=["operation"],
)


def setup_metrics(app) -> None:
    """Instrumentação automática FastAPI (request_count, latency, etc.) + /metrics."""
    Instrumentator(
        should_group_status_codes=True,
        should_ignore_untemplated=False,
        should_respect_env_var=False,
        excluded_handlers=["/metrics"],
    ).instrument(app).expose(app, endpoint="/metrics", include_in_schema=False)
