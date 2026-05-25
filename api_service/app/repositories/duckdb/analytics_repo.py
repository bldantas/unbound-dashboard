"""Agregações analíticas pra /api/v1/analytics (B.3).

Tudo agrega sobre `query_logs`. Janelas aceitas: 1h, 24h, 7d, 30d.
Bucketing aritmético via FLOOR(timestamp / bucket_secs) — DuckDB faz isso
em ~ms mesmo com 20M+ linhas porque tem zonemap por coluna.
"""

from __future__ import annotations

from typing import Literal

from app.repositories.duckdb.connection import db_fetchall, db_fetchone

Window = Literal["1h", "24h", "7d", "30d"]

_WINDOW_SECONDS = {"1h": 3600, "24h": 86400, "7d": 604800, "30d": 2592000}

# Bucket size por janela — escolhido pra dar ~60-100 pontos no gráfico
_BUCKET_SECONDS = {"1h": 60, "24h": 900, "7d": 7200, "30d": 28800}


def window_seconds(w: Window) -> int:
    return _WINDOW_SECONDS.get(w, 86400)


def bucket_seconds(w: Window) -> int:
    return _BUCKET_SECONDS.get(w, 900)


async def summary(window: Window) -> dict:
    """Totais e ratios na janela: total, blocked, cached, resolved, nxdomain, blocked_ratio, cache_ratio."""
    secs = window_seconds(window)
    row = await db_fetchone(
        f"""
        SELECT
            COUNT(*)                                                    AS total,
            COUNT(*) FILTER (WHERE action = 'blocked')                  AS blocked,
            COUNT(*) FILTER (WHERE action = 'cached')                   AS cached,
            COUNT(*) FILTER (WHERE action = 'resolved')                 AS resolved,
            COUNT(*) FILTER (WHERE action = 'nxdomain_upstream')        AS nxdomain,
            COUNT(DISTINCT client_ip)                                   AS unique_clients,
            COUNT(DISTINCT domain)                                      AS unique_domains
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
        """,
        [secs],
    )
    total = int(row["total"] or 0) if row else 0
    blocked = int(row["blocked"] or 0) if row else 0
    cached = int(row["cached"] or 0) if row else 0
    return {
        "window": window,
        "total": total,
        "blocked": blocked,
        "cached": cached,
        "resolved": int(row["resolved"] or 0) if row else 0,
        "nxdomain_upstream": int(row["nxdomain"] or 0) if row else 0,
        "unique_clients": int(row["unique_clients"] or 0) if row else 0,
        "unique_domains": int(row["unique_domains"] or 0) if row else 0,
        "blocked_ratio": round(100.0 * blocked / total, 2) if total else 0.0,
        "cache_ratio": round(100.0 * cached / total, 2) if total else 0.0,
    }


async def timeseries(window: Window) -> list[dict]:
    """Buckets temporais: [{ts, total, blocked, cached, resolved}, ...]."""
    secs = window_seconds(window)
    bucket = bucket_seconds(window)
    # DuckDB faz `/` como float — pra bucketing inteiro precisa FLOOR ou cast.
    rows = await db_fetchall(
        f"""
        SELECT
            (CAST(timestamp / ? AS BIGINT)) * ?                          AS ts,
            COUNT(*)                                                     AS total,
            COUNT(*) FILTER (WHERE action = 'blocked')                   AS blocked,
            COUNT(*) FILTER (WHERE action = 'cached')                    AS cached,
            COUNT(*) FILTER (WHERE action = 'resolved')                  AS resolved
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
        GROUP BY ts
        ORDER BY ts
        """,
        [bucket, bucket, secs],
    )
    return [
        {
            "ts": int(r["ts"]),
            "total": int(r["total"] or 0),
            "blocked": int(r["blocked"] or 0),
            "cached": int(r["cached"] or 0),
            "resolved": int(r["resolved"] or 0),
        }
        for r in rows
    ]


async def by_query_type(window: Window) -> list[dict]:
    """Distribuição por query_type ordenado desc."""
    secs = window_seconds(window)
    rows = await db_fetchall(
        f"""
        SELECT query_type, COUNT(*) AS n
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
        GROUP BY query_type
        ORDER BY n DESC
        """,
        [secs],
    )
    return [{"type": str(r["query_type"] or ""), "count": int(r["n"] or 0)} for r in rows]


async def top_domains(window: Window, limit: int = 20, action: str | None = None) -> list[dict]:
    """Top domínios por count. action = None|'blocked'|'resolved'|'cached'."""
    secs = window_seconds(window)
    conds = ["timestamp >= epoch(NOW()) - ?"]
    args: list = [secs]
    if action:
        conds.append("action = ?")
        args.append(action)
    args.append(int(limit))
    rows = await db_fetchall(
        f"""
        SELECT
            domain,
            COUNT(*)                                  AS total,
            COUNT(*) FILTER (WHERE action='blocked')  AS blocked
        FROM query_logs
        WHERE {' AND '.join(conds)}
        GROUP BY domain
        ORDER BY total DESC
        LIMIT ?
        """,
        args,
    )
    return [
        {"domain": str(r["domain"] or ""), "total": int(r["total"] or 0), "blocked": int(r["blocked"] or 0)}
        for r in rows
    ]


async def top_clients(window: Window, limit: int = 20) -> list[dict]:
    """Top client IPs com ratio bloqueado."""
    secs = window_seconds(window)
    rows = await db_fetchall(
        """
        SELECT
            client_ip,
            COUNT(*)                                  AS total,
            COUNT(*) FILTER (WHERE action='blocked')  AS blocked,
            COUNT(DISTINCT domain)                    AS unique_domains
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
        GROUP BY client_ip
        ORDER BY total DESC
        LIMIT ?
        """,
        [secs, int(limit)],
    )
    out = []
    for r in rows:
        t = int(r["total"] or 0)
        b = int(r["blocked"] or 0)
        out.append(
            {
                "client_ip": str(r["client_ip"] or ""),
                "total": t,
                "blocked": b,
                "unique_domains": int(r["unique_domains"] or 0),
                "blocked_ratio": round(100.0 * b / t, 2) if t else 0.0,
            }
        )
    return out


async def action_breakdown(window: Window) -> list[dict]:
    """Distribuição por action — pra donut chart de blocked vs resolved vs cached."""
    secs = window_seconds(window)
    rows = await db_fetchall(
        """
        SELECT action, COUNT(*) AS n
        FROM query_logs
        WHERE timestamp >= epoch(NOW()) - ?
        GROUP BY action
        ORDER BY n DESC
        """,
        [secs],
    )
    return [{"action": str(r["action"] or ""), "count": int(r["n"] or 0)} for r in rows]
