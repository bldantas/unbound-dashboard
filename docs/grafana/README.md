# Grafana Dashboards — Unbound Dashboard

Dashboards prontos pra importar consumindo o endpoint Prometheus
`/metrics` da API.

## Pré-requisitos

1. **Prometheus configurado** com scrape do `/metrics`:

   ```yaml
   scrape_configs:
     - job_name: 'unbound-dashboard'
       scrape_interval: 30s
       static_configs:
         - targets: ['127.0.0.1:8001']   # ou via Apache: 'dashboard.local:443/metrics'
   ```

2. **Grafana** com datasource Prometheus configurado.

## Importar

1. Grafana → Dashboards → **New → Import**
2. Upload do JSON ou cole o conteúdo
3. Na tela seguinte, selecione seu datasource Prometheus
   (substitui o placeholder `PROMETHEUS_DS`)
4. Clique **Import**

## Dashboards disponíveis

### `unbound-overview.json` — Visão Geral

- 6 stats no topo: Online, QPS, Hit Ratio, Latência P95, Alertas
  Critical, Uptime
- Timeseries:
  - Latência (P50/P95/P99 + média)
  - QPS + Hit Ratio
  - Cache memory (RRset/Msg)
  - Cache hits/miss/prefetch
  - Alertas ativos por severidade
  - Request list (avg/max)
  - Worker errors rate (5m)

Refresh: 30s.

## Métricas expostas pela API

| Métrica | Tipo | Labels | Descrição |
|---|---|---|---|
| `unbound_online` | gauge | — | 1 se unbound-control responde |
| `unbound_qps` | gauge | — | Queries por segundo |
| `unbound_hit_ratio` | gauge | — | Cache hit % (0..100) |
| `unbound_latency_milliseconds` | gauge | kind={avg,median,p50,p95,p99} | Latência DNS |
| `unbound_total_queries` | gauge | — | Total queries since boot |
| `unbound_cache` | gauge | kind={hits,miss,prefetch} | Counters de cache |
| `unbound_cache_memory_bytes` | gauge | kind={rrset,msg} | Memória usada |
| `unbound_request_list` | gauge | kind={avg,max} | Queue depth |
| `unbound_dnssec` | gauge | kind={secure,bogus,ratio} | DNSSEC counters |
| `unbound_uptime_seconds` | gauge | — | Uptime |
| `unbound_alerts_active` | gauge | severity={critical,warning,info} | Alertas ativos |
| `unbound_blocks` | gauge | category={adware,phishing,judicial} | Domínios bloqueados |
| `unbound_queries_ingested_total` | counter | action | Queries ingestionadas |
| `unbound_worker_errors_total` | counter | worker | Falhas em workers |
| `unbound_worker_queue_size` | gauge | — | LogWatcher queue size |

Métricas FastAPI HTTP (request_count, latency) também ficam expostas
automaticamente via `prometheus-fastapi-instrumentator`.
