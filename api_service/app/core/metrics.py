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

# Métricas do Unbound — atualizadas 1x/min pelo PrometheusExporter worker.
# Snapshot de unbound_stats_service.get_stats() exposto pra Prometheus.

unbound_online = Gauge(
    "unbound_online",
    "1 se unbound-control responde, 0 caso contrário",
)
unbound_qps = Gauge(
    "unbound_qps",
    "Queries por segundo (total/uptime)",
)
unbound_hit_ratio = Gauge(
    "unbound_hit_ratio",
    "Cache hit ratio em % (0..100)",
)
unbound_latency_ms = Gauge(
    "unbound_latency_milliseconds",
    "Latência DNS em milissegundos por percentil/agregação",
    labelnames=["kind"],   # avg | median | p50 | p95 | p99
)
unbound_total_queries = Gauge(
    "unbound_total_queries",
    "Total de queries desde o boot do Unbound",
)
unbound_cache = Gauge(
    "unbound_cache",
    "Counters de cache (hits/miss/prefetch)",
    labelnames=["kind"],   # hits | miss | prefetch
)
unbound_cache_memory_bytes = Gauge(
    "unbound_cache_memory_bytes",
    "Memória usada pelos caches em bytes",
    labelnames=["kind"],   # rrset | msg
)
unbound_request_list = Gauge(
    "unbound_request_list",
    "Profundidade da fila de requests em andamento",
    labelnames=["kind"],   # avg | max
)
unbound_dnssec = Gauge(
    "unbound_dnssec",
    "Counters DNSSEC (secure/bogus/ratio)",
    labelnames=["kind"],   # secure | bogus | ratio
)
unbound_uptime_seconds = Gauge(
    "unbound_uptime_seconds",
    "Uptime do Unbound em segundos",
)
unbound_alerts_active = Gauge(
    "unbound_alerts_active",
    "Alertas ativos (resolved_at IS NULL) por severidade",
    labelnames=["severity"],   # critical | warning | info
)
unbound_blocks = Gauge(
    "unbound_blocks",
    "Domínios bloqueados por categoria",
    labelnames=["category"],   # adware | phishing | judicial
)


def setup_metrics(app) -> None:
    """Instrumentação automática FastAPI (request_count, latency, etc.) + /metrics."""
    Instrumentator(
        should_group_status_codes=True,
        should_ignore_untemplated=False,
        should_respect_env_var=False,
        excluded_handlers=["/metrics"],
    ).instrument(app).expose(app, endpoint="/metrics", include_in_schema=False)
