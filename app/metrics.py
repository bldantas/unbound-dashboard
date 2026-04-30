"""
Métricas Prometheus expostas em GET /metrics.

Métricas customizadas:
  unbound_queries_ingested_total  — total de queries ingeridas pelo LogWatcher
  unbound_queries_blocked_total   — total de queries bloqueadas
  unbound_cache_hit_ratio         — proporção de cache hits (gauge)
  unbound_worker_errors_total     — erros nos workers asyncio

Métricas automáticas (prometheus-fastapi-instrumentator):
  http_requests_total, http_request_duration_seconds, etc.
"""

from __future__ import annotations

from prometheus_client import Counter, Gauge, Histogram
from prometheus_fastapi_instrumentator import Instrumentator

# ---------------------------------------------------------------------------
# Contadores de domínio DNS
# ---------------------------------------------------------------------------

queries_ingested = Counter(
    "unbound_queries_ingested_total",
    "Total de queries DNS ingeridas pelo LogWatcher",
)

queries_blocked = Counter(
    "unbound_queries_blocked_total",
    "Total de queries DNS bloqueadas",
)

queries_resolved = Counter(
    "unbound_queries_resolved_total",
    "Total de queries DNS resolvidas",
)

queries_cached = Counter(
    "unbound_queries_cached_total",
    "Total de queries DNS respondidas do cache",
)

# ---------------------------------------------------------------------------
# Gauges de estado
# ---------------------------------------------------------------------------

cache_hit_ratio = Gauge(
    "unbound_cache_hit_ratio",
    "Proporção de cache hits Unbound (0.0 – 1.0)",
)

unbound_up = Gauge(
    "unbound_up",
    "1 se o daemon Unbound está rodando, 0 caso contrário",
)

worker_queue_size = Gauge(
    "unbound_log_watcher_queue_size",
    "Número de entradas no buffer do LogWatcher aguardando flush para DuckDB",
)

# ---------------------------------------------------------------------------
# Contadores de erros
# ---------------------------------------------------------------------------

worker_errors = Counter(
    "unbound_worker_errors_total",
    "Total de erros nos workers asyncio",
    labelnames=["worker"],
)

# ---------------------------------------------------------------------------
# Histograma de latência de ingestão (bulk insert DuckDB)
# ---------------------------------------------------------------------------

bulk_insert_duration = Histogram(
    "unbound_bulk_insert_duration_seconds",
    "Duração do bulk INSERT no DuckDB",
    buckets=[0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0],
)


# ---------------------------------------------------------------------------
# Helper para registrar o instrumentator no app FastAPI
# ---------------------------------------------------------------------------

def setup_metrics(app) -> None:  # type: ignore[no-untyped-def]
    """
    Chame no startup para ativar métricas HTTP automáticas em GET /metrics.

    Uso em main.py:
        from app.metrics import setup_metrics
        setup_metrics(app)
    """
    Instrumentator(
        should_group_status_codes=True,
        should_ignore_untemplated=True,
        excluded_handlers=["/metrics", "/healthz"],
    ).instrument(app).expose(app, include_in_schema=False, tags=["observability"])
