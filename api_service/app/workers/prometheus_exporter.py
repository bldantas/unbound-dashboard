"""
PrometheusExporter — atualiza Gauges com snapshot de unbound_stats_service.

Tick: 60s. Reusa o cache do `unbound_stats_service.get_stats()` (TTL 60s,
single source of truth pro dashboard); sem chamadas extras ao unbound-control.

Também conta alertas ativos por severidade direto no DuckDB.
"""

from __future__ import annotations

import asyncio

import structlog

from app.core.metrics import (
    unbound_alerts_active,
    unbound_blocks,
    unbound_cache,
    unbound_cache_memory_bytes,
    unbound_dnssec,
    unbound_hit_ratio,
    unbound_latency_ms,
    unbound_online,
    unbound_qps,
    unbound_request_list,
    unbound_total_queries,
    unbound_uptime_seconds,
    worker_errors,
)
from app.repositories.duckdb.connection import db_fetchall

log = structlog.get_logger(__name__)

EXPORT_INTERVAL = 60


def _parse_bytes(human: str) -> float:
    """Converte '3.86 MB' → bytes. Usado nos counters de cache mem."""
    if not isinstance(human, str):
        return 0.0
    h = human.strip()
    try:
        num, unit = h.split(" ", 1)
        n = float(num)
        unit = unit.upper().strip()
        mult = {"B": 1, "KB": 1024, "MB": 1024**2, "GB": 1024**3}.get(unit, 1)
        return n * mult
    except (ValueError, AttributeError):
        return 0.0


class PrometheusExporter:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        # Aguarda 30s pra unbound_stats_service ter cache populado
        await asyncio.sleep(30)
        while self._running:
            try:
                await self._run_once()
            except Exception as exc:  # noqa: BLE001
                log.error("prometheus_exporter.cycle_failed", error=str(exc))
                worker_errors.labels(worker="prometheus_exporter").inc()
            await asyncio.sleep(EXPORT_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_once(self) -> None:
        from app.services import unbound_stats_service

        s = await unbound_stats_service.get_stats()

        unbound_online.set(1 if s.get("online") else 0)
        unbound_qps.set(float(s.get("qps", 0)))
        unbound_hit_ratio.set(float(s.get("hit_ratio", 0)))
        unbound_total_queries.set(float(s.get("total_queries", 0)))
        unbound_uptime_seconds.set(float(s.get("uptime", 0)))

        unbound_latency_ms.labels(kind="avg").set(float(s.get("latency_avg", 0)))
        unbound_latency_ms.labels(kind="median").set(float(s.get("latency_median", 0)))
        unbound_latency_ms.labels(kind="p50").set(float(s.get("latency_p50", 0)))
        unbound_latency_ms.labels(kind="p95").set(float(s.get("latency_p95", 0)))
        unbound_latency_ms.labels(kind="p99").set(float(s.get("latency_p99", 0)))

        unbound_cache.labels(kind="hits").set(float(s.get("cache_hits", 0)))
        unbound_cache.labels(kind="miss").set(float(s.get("cache_miss", 0)))
        unbound_cache.labels(kind="prefetch").set(float(s.get("prefetch", 0)))

        unbound_cache_memory_bytes.labels(kind="rrset").set(_parse_bytes(s.get("rrset_mem", "0 B")))
        unbound_cache_memory_bytes.labels(kind="msg").set(_parse_bytes(s.get("msg_mem", "0 B")))

        unbound_request_list.labels(kind="avg").set(float(s.get("req_list_avg", 0)))
        unbound_request_list.labels(kind="max").set(float(s.get("req_list_max", 0)))

        unbound_dnssec.labels(kind="secure").set(float(s.get("dnssec_secure", 0)))
        unbound_dnssec.labels(kind="bogus").set(float(s.get("dnssec_bogus", 0)))
        unbound_dnssec.labels(kind="ratio").set(float(s.get("dnssec_ratio", 0)))

        blocks = s.get("blocks", {})
        for cat in ("adware", "phishing", "judicial"):
            unbound_blocks.labels(category=cat).set(float(blocks.get(cat, 0)))

        # Alertas ativos por severidade — direto na DB
        try:
            rows = await db_fetchall(
                """
                SELECT severity, COUNT(*) AS n
                FROM alerts
                WHERE resolved_at IS NULL
                GROUP BY severity
                """,
                [],
            )
            sev_counts = {r["severity"]: int(r["n"]) for r in rows}
            for sev in ("critical", "warning", "info"):
                unbound_alerts_active.labels(severity=sev).set(float(sev_counts.get(sev, 0)))
        except Exception as exc:  # noqa: BLE001
            log.warning("prometheus_exporter.alerts_query_failed", error=str(exc))
