"""
Endpoints /api/v1/grafana — JSON amigável pra Grafana Infinity datasource.

Diferente de /metrics (Prometheus, formato texto), aqui retornamos JSON
plano que o "Infinity Plugin" do Grafana parseia direto. Útil pra quem
não quer rodar Prometheus scraper só pra puxar 5-10 métricas.

Endpoints:
  GET /api/v1/grafana/snapshot   — métricas chave instantâneas (lista flat)
  GET /api/v1/grafana/timeseries — pontos hourly_stats em formato {time, value}

Auth: dashboard.read (qualquer user autenticado). Token API funciona,
ideal pra Grafana usar X-Api-Token sem expor credenciais humanas.
"""

from __future__ import annotations

from datetime import UTC, datetime
from typing import Annotated, Literal

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability

router = APIRouter(prefix="/api/v1/grafana", tags=["grafana"])


@router.get("/snapshot")
async def snapshot(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> list[dict]:
    """
    Lista flat de métricas atuais. Cada item: {name, value, unit, timestamp}.
    Formato pensado pro "Infinity" datasource (parser JSON do Grafana).
    """
    from app.services import unbound_stats_service
    from app.repositories.duckdb.connection import db_fetchone

    stats = await unbound_stats_service.get_stats()
    now_iso = datetime.now(UTC).isoformat()

    # Hoje pré-agregado
    today = datetime.now(UTC).date()
    daily = await db_fetchone(
        "SELECT total_queries, blocked_count FROM daily_stats WHERE stat_date = ?",
        [today],
    )
    queries_today = int(daily["total_queries"]) if daily else 0
    blocked_today = int(daily["blocked_count"]) if daily else 0

    return [
        {"name": "qps", "value": float(stats.get("qps", 0) or 0), "unit": "qps", "timestamp": now_iso},
        {"name": "hit_ratio", "value": float(stats.get("hit_ratio", 0) or 0), "unit": "percent", "timestamp": now_iso},
        {"name": "latency_avg_ms", "value": float(stats.get("latency_avg", 0) or 0), "unit": "ms", "timestamp": now_iso},
        {"name": "latency_median_ms", "value": float(stats.get("latency_median", 0) or 0), "unit": "ms", "timestamp": now_iso},
        {"name": "dnssec_ratio", "value": float(stats.get("dnssec_ratio", 0) or 0), "unit": "percent", "timestamp": now_iso},
        {"name": "dnssec_secure", "value": int(stats.get("dnssec_secure", 0) or 0), "unit": "count", "timestamp": now_iso},
        {"name": "dnssec_bogus", "value": int(stats.get("dnssec_bogus", 0) or 0), "unit": "count", "timestamp": now_iso},
        {"name": "uptime_seconds", "value": int(stats.get("uptime", 0) or 0), "unit": "seconds", "timestamp": now_iso},
        {"name": "queries_today", "value": queries_today, "unit": "count", "timestamp": now_iso},
        {"name": "blocked_today", "value": blocked_today, "unit": "count", "timestamp": now_iso},
        {"name": "online", "value": 1 if stats.get("online") else 0, "unit": "bool", "timestamp": now_iso},
    ]


MetricName = Literal["total", "blocked"]


@router.get("/timeseries")
async def timeseries(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    metric: MetricName = Query("total", description="total|blocked"),
    hours: int = Query(24, ge=1, le=720, description="Janela em horas (1..720)"),
) -> list[dict]:
    """
    Pontos do hourly_stats no formato `[{time: ISO, value: int}]`.
    Pronto pra Grafana series visualization.
    """
    from app.repositories.duckdb.connection import db_fetchall

    now = int(datetime.now(UTC).timestamp())
    since = ((now // 3600) - hours) * 3600
    col = "total_queries" if metric == "total" else "blocked_count"
    rows = await db_fetchall(
        f"""
        SELECT hour_start, {col} AS value
        FROM hourly_stats
        WHERE hour_start >= ?
        ORDER BY hour_start ASC
        """,
        [since],
    )
    return [
        {
            "time": datetime.fromtimestamp(int(r["hour_start"]), tz=UTC).isoformat(),
            "value": int(r["value"] or 0),
            "metric": metric,
        }
        for r in rows
    ]
