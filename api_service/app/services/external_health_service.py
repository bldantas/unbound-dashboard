"""
external_health_service — recebe probes e calcula SLA agregado.

SLA = (sucessos / total) * 100 numa janela. Default 24h.
Latência percentis (P50/P95/P99) calculados a partir das amostras
em memória (até 50k registros — cap suficiente pra ~1 probe/min × 30d).
"""

from __future__ import annotations

import statistics
from datetime import datetime, timedelta, timezone
from typing import Any

import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)


def _to_iso(v: Any) -> str | None:
    if isinstance(v, datetime):
        return v.isoformat()
    return str(v) if v else None


async def record_probe(probe: dict) -> int:
    """Insere uma observação. Retorna o id."""
    if probe.get("probed_at"):
        try:
            probed_at = datetime.fromisoformat(probe["probed_at"])
        except (ValueError, TypeError):
            probed_at = datetime.now(timezone.utc)
    else:
        probed_at = datetime.now(timezone.utc)

    await db_execute(
        """
        INSERT INTO external_health_probes
            (probed_at, probe_source, target_host, query_name, success, latency_ms,
             response_correct, error)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        """,
        [
            probed_at,
            (probe.get("probe_source") or "")[:80],
            (probe.get("target_host") or "")[:120],
            (probe.get("query_name") or "")[:255],
            bool(probe.get("success")),
            int(probe.get("latency_ms")) if probe.get("latency_ms") is not None else None,
            bool(probe.get("response_correct")) if probe.get("response_correct") is not None else None,
            (probe.get("error") or "")[:500] or None,
        ],
    )
    row = await db_fetchone("SELECT id FROM external_health_probes ORDER BY id DESC LIMIT 1", [])
    return int(row["id"]) if row else 0


async def list_recent(*, probe_source: str | None = None, limit: int = 200, hours: int = 24) -> list[dict]:
    where = ["probed_at >= NOW() - (INTERVAL '1 hour' * ?)"]
    params: list = [int(hours)]
    if probe_source:
        where.append("probe_source = ?")
        params.append(probe_source)
    params.append(int(limit))
    rows = await db_fetchall(
        f"""
        SELECT id, probed_at, probe_source, target_host, query_name,
               success, latency_ms, response_correct, error
        FROM external_health_probes
        WHERE {' AND '.join(where)}
        ORDER BY probed_at DESC
        LIMIT ?
        """,
        params,
    )
    out = []
    for r in rows:
        out.append({
            "id": int(r["id"]),
            "probed_at": _to_iso(r.get("probed_at")),
            "probe_source": r.get("probe_source"),
            "target_host": r.get("target_host"),
            "query_name": r.get("query_name"),
            "success": bool(r.get("success")),
            "latency_ms": r.get("latency_ms"),
            "response_correct": r.get("response_correct"),
            "error": r.get("error"),
        })
    return out


async def sla(*, hours: int = 24, probe_source: str | None = None) -> dict:
    where = ["probed_at >= NOW() - (INTERVAL '1 hour' * ?)"]
    params: list = [int(hours)]
    if probe_source:
        where.append("probe_source = ?")
        params.append(probe_source)
    where_sql = " AND ".join(where)

    summary = await db_fetchone(
        f"""
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN success THEN 1 ELSE 0 END) AS ok,
               SUM(CASE WHEN response_correct THEN 1 ELSE 0 END) AS correct
        FROM external_health_probes
        WHERE {where_sql}
        """,
        params,
    )
    total = int(summary["total"]) if summary else 0
    ok = int(summary.get("ok") or 0) if summary else 0
    correct = int(summary.get("correct") or 0) if summary else 0

    rows = await db_fetchall(
        f"""
        SELECT latency_ms FROM external_health_probes
        WHERE {where_sql} AND success = true AND latency_ms IS NOT NULL
        """,
        params,
    )
    latencies = sorted([int(r["latency_ms"]) for r in rows if r.get("latency_ms") is not None])

    def pct(p: float) -> float:
        if not latencies:
            return 0.0
        idx = max(0, min(len(latencies) - 1, int(len(latencies) * p / 100.0)))
        return float(latencies[idx])

    return {
        "hours": hours,
        "probe_source": probe_source,
        "total_probes": total,
        "success_count": ok,
        "correct_count": correct,
        "sla_uptime_pct": round((ok / total) * 100.0, 3) if total > 0 else 0.0,
        "sla_correct_pct": round((correct / total) * 100.0, 3) if total > 0 else 0.0,
        "latency_p50_ms": pct(50),
        "latency_p95_ms": pct(95),
        "latency_p99_ms": pct(99),
        "latency_avg_ms": round(statistics.mean(latencies), 1) if latencies else 0.0,
    }


async def list_sources(*, hours: int = 168) -> list[dict]:
    """Distintos probe_source nas últimas N horas."""
    rows = await db_fetchall(
        """
        SELECT probe_source, COUNT(*) AS n, MAX(probed_at) AS last_at
        FROM external_health_probes
        WHERE probed_at >= NOW() - (INTERVAL '1 hour' * ?)
          AND probe_source IS NOT NULL
        GROUP BY probe_source
        ORDER BY n DESC
        """,
        [int(hours)],
    )
    return [
        {
            "probe_source": r["probe_source"],
            "count": int(r["n"]),
            "last_at": _to_iso(r.get("last_at")),
        }
        for r in rows
    ]


async def prune_old(days: int) -> int:
    row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM external_health_probes WHERE probed_at < NOW() - (INTERVAL '1 day' * ?)",
        [int(days)],
    )
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute(
            "DELETE FROM external_health_probes WHERE probed_at < NOW() - (INTERVAL '1 day' * ?)",
            [int(days)],
        )
    return n
