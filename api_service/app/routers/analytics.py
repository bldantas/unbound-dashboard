"""Endpoints analíticos /api/v1/analytics (B.3).

Todos os endpoints aceitam ?window=1h|24h|7d|30d. Default 24h.
Capability `dashboard.read` (qualquer usuário autenticado) — sem dados sensíveis.
"""

from __future__ import annotations

import csv
import io
from datetime import datetime, timezone
from typing import Annotated, Literal

from fastapi import APIRouter, Depends, Query, Request
from fastapi.responses import StreamingResponse

from app.core.deps import require_capability
from app.repositories.duckdb import analytics_repo

router = APIRouter(prefix="/api/v1/analytics", tags=["analytics"])

WindowParam = Literal["1h", "24h", "7d", "30d"]


@router.get("/summary")
async def get_summary(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    return await analytics_repo.summary(window)


@router.get("/timeseries")
async def get_timeseries(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    points = await analytics_repo.timeseries(window)
    return {
        "window": window,
        "bucket_seconds": analytics_repo.bucket_seconds(window),
        "points": points,
    }


@router.get("/by-query-type")
async def get_by_query_type(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    return {"window": window, "items": await analytics_repo.by_query_type(window)}


@router.get("/top-domains")
async def get_top_domains(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    limit: int = Query(20, ge=1, le=200),
    action: Literal["blocked", "resolved", "cached", "nxdomain_upstream"] | None = Query(None),
) -> dict:
    return {
        "window": window,
        "limit": limit,
        "action": action,
        "items": await analytics_repo.top_domains(window, limit=limit, action=action),
    }


@router.get("/top-clients")
async def get_top_clients(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    limit: int = Query(20, ge=1, le=200),
) -> dict:
    return {
        "window": window,
        "limit": limit,
        "items": await analytics_repo.top_clients(window, limit=limit),
    }


@router.get("/action-breakdown")
async def get_action_breakdown(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
) -> dict:
    return {"window": window, "items": await analytics_repo.action_breakdown(window)}


# ============================================================
# Busca paginada em query_logs (B.4)
# ============================================================


_GEO_CAP = 5000  # cap de linhas pré-filtro quando country filter está ativo


@router.get("/queries/search")
async def search_queries(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    client_ip: str = Query("", max_length=64),
    domain: str = Query("", max_length=255),
    query_type: str = Query("", max_length=10),
    action: str = Query("", max_length=30),
    country: str = Query("", max_length=4),
    page: int = Query(1, ge=1, le=10000),
    per_page: int = Query(50, ge=1, le=200),
) -> dict:
    # Sem filtro de país: paginação SQL normal.
    if not country:
        offset = (page - 1) * per_page
        total, rows = await analytics_repo.search_queries(
            window=window,
            client_ip=client_ip or None,
            domain=domain or None,
            query_type=query_type or None,
            action=action or None,
            offset=offset,
            limit=per_page,
        )
        total_pages = max(1, (total + per_page - 1) // per_page)
        return {
            "window": window,
            "total": total,
            "page": page,
            "per_page": per_page,
            "total_pages": total_pages,
            "rows": rows,
            "country": "",
            "truncated": False,
        }

    # Filtro de país: pega até _GEO_CAP linhas, faz geoip lookup, filtra, pagina in-memory.
    # Total reportado é o total *filtrado* (não o pré-filtro), capped em _GEO_CAP.
    from app.services import geoip_service

    _pre_total, rows = await analytics_repo.search_queries(
        window=window,
        client_ip=client_ip or None,
        domain=domain or None,
        query_type=query_type or None,
        action=action or None,
        offset=0,
        limit=_GEO_CAP,
    )
    truncated = _pre_total > _GEO_CAP
    if not rows:
        return {
            "window": window, "total": 0, "page": page, "per_page": per_page,
            "total_pages": 1, "rows": [], "country": country, "truncated": truncated,
        }

    ips = list({r["client_ip"] for r in rows})
    geo_map = await geoip_service.lookup_many(ips)
    target = country.upper()
    filtered = [r for r in rows if geo_map.get(r["client_ip"], {}).get("country_code") == target]
    total = len(filtered)
    total_pages = max(1, (total + per_page - 1) // per_page)
    start = (page - 1) * per_page
    page_rows = filtered[start : start + per_page]
    return {
        "window": window,
        "total": total,
        "page": page,
        "per_page": per_page,
        "total_pages": total_pages,
        "rows": page_rows,
        "country": country,
        "truncated": truncated,
    }


# ============================================================
# Anomaly detection (B.5)
# ============================================================

_ANOMALY_KEYS = [
    "anomaly_enabled",
    "anomaly_dga_window_seconds",
    "anomaly_dga_entropy_min",
    "anomaly_dga_min_length",
    "anomaly_dga_min_count_per_client",
    "anomaly_nxdomain_window_seconds",
    "anomaly_nxdomain_spike_ratio",
    "anomaly_nxdomain_spike_min_count",
    "anomaly_new_client_baseline_days",
    "anomaly_new_client_window_seconds",
    "anomaly_new_client_min_queries",
]


@router.get("/anomaly/settings")
async def get_anomaly_settings(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    from app.repositories.duckdb import settings_repo
    from app.workers.anomaly_detector import DEFAULTS

    out = {}
    for k in _ANOMALY_KEYS:
        v = await settings_repo.get(k, DEFAULTS[k])
        out[k] = v
    return {"settings": out, "defaults": DEFAULTS}


@router.put("/anomaly/settings")
async def update_anomaly_settings(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    body: dict,
) -> dict:
    from app.repositories.duckdb import settings_repo

    entries = []
    for k, v in body.items():
        if k in _ANOMALY_KEYS:
            entries.append({"setting_key": k, "setting_value": str(v)})
    if not entries:
        return {"updated": 0}
    n = await settings_repo.bulk_upsert(entries)
    return {"updated": n}


@router.get("/anomaly/recent")
async def get_anomaly_recent(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    limit: int = Query(100, ge=1, le=500),
    include_resolved: bool = Query(False),
) -> dict:
    """Lista detecções (alerts com type LIKE 'anomaly_%')."""
    from app.repositories.duckdb.connection import db_fetchall

    where = "type LIKE 'anomaly_%'"
    if not include_resolved:
        where += " AND resolved_at IS NULL"

    rows = await db_fetchall(
        f"""
        SELECT id, type, severity, message, started_at, resolved_at
        FROM alerts
        WHERE {where}
        ORDER BY started_at DESC
        LIMIT ?
        """,
        [int(limit)],
    )
    return {
        "items": [
            {
                "id": int(r["id"]),
                "type": str(r["type"]),
                "category": str(r["type"]).split(":")[0],
                "subject": ":".join(str(r["type"]).split(":")[1:]) if ":" in str(r["type"]) else "",
                "severity": str(r["severity"]),
                "message": str(r.get("message") or ""),
                "started_at": r["started_at"].isoformat() if r.get("started_at") else None,
                "resolved_at": r["resolved_at"].isoformat() if r.get("resolved_at") else None,
            }
            for r in rows
        ]
    }


@router.post("/anomaly/run-now")
async def run_anomaly_now(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Roda os 3 checks uma vez (independente de anomaly_enabled). Útil pra teste."""
    from app.workers import anomaly_detector as ad

    return await ad.run_once()


@router.get("/queries/export-csv")
async def export_csv(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    client_ip: str = Query("", max_length=64),
    domain: str = Query("", max_length=255),
    query_type: str = Query("", max_length=10),
    action: str = Query("", max_length=30),
    country: str = Query("", max_length=4),
    limit: int = Query(10000, ge=1, le=100000),
) -> StreamingResponse:
    """Export CSV — capped em 100k linhas pra evitar OOM."""
    _, rows = await analytics_repo.search_queries(
        window=window,
        client_ip=client_ip or None,
        domain=domain or None,
        query_type=query_type or None,
        action=action or None,
        offset=0,
        limit=limit,
    )

    # Filtro de país aplicado pós-query (mesma estratégia do search).
    country_map: dict[str, str] = {}
    if country:
        from app.services import geoip_service

        ips = list({r["client_ip"] for r in rows})
        geo_map = await geoip_service.lookup_many(ips)
        country_map = {ip: info.get("country_code", "??") for ip, info in geo_map.items()}
        target = country.upper()
        rows = [r for r in rows if country_map.get(r["client_ip"]) == target]

    buf = io.StringIO()
    writer = csv.writer(buf)
    header = ["timestamp_iso", "timestamp_epoch", "client_ip", "domain", "query_type", "action"]
    if country:
        header.append("country_code")
    writer.writerow(header)
    for r in rows:
        iso = datetime.fromtimestamp(r["timestamp"], tz=timezone.utc).isoformat()
        row = [iso, r["timestamp"], r["client_ip"], r["domain"], r["query_type"], r["action"]]
        if country:
            row.append(country_map.get(r["client_ip"], "??"))
        writer.writerow(row)
    buf.seek(0)

    filename = f"unbound-queries-{datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')}.csv"
    return StreamingResponse(
        iter([buf.getvalue()]),
        media_type="text/csv",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )


# --- B.3: Retention + hourly rollups ---

_RETENTION_KEYS = ("query_log_retention_enabled", "query_log_retention_days")
_RETENTION_DEFAULTS = {
    "query_log_retention_enabled": "1",
    "query_log_retention_days": "90",
}


@router.get("/retention/settings")
async def get_retention_settings(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    """Settings + estado da última execução do pruner."""
    from app.repositories.duckdb import settings_repo
    from app.repositories.duckdb.connection import db_fetchone

    out = {k: await settings_repo.get(k, _RETENTION_DEFAULTS[k]) for k in _RETENTION_KEYS}
    last = {
        "last_run":     await settings_repo.get("query_log_pruner_last_run"),
        "last_deleted": await settings_repo.get("query_log_pruner_last_deleted"),
        "last_cutoff":  await settings_repo.get("query_log_pruner_last_cutoff"),
    }
    row = await db_fetchone("SELECT COUNT(*) AS n, MIN(timestamp) AS oldest FROM query_logs")
    return {
        "settings": out,
        "defaults": _RETENTION_DEFAULTS,
        "last_run": last,
        "current": {
            "total_rows": int(row["n"] or 0) if row else 0,
            "oldest_epoch": int(row["oldest"] or 0) if row and row.get("oldest") else None,
        },
    }


@router.put("/retention/settings")
async def update_retention_settings(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    body: dict,
) -> dict:
    from app.repositories.duckdb import settings_repo

    entries = []
    for k, v in body.items():
        if k in _RETENTION_KEYS:
            entries.append({"setting_key": k, "setting_value": str(v)})
    if not entries:
        return {"updated": 0}
    n = await settings_repo.bulk_upsert(entries)
    return {"updated": n}


@router.post("/retention/prune-now")
async def prune_now(
    request: Request,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Dispara prune imediato (ignora schedule)."""
    pruner = getattr(request.app.state, "query_log_pruner", None)
    if pruner is None:
        return {"started": False, "error": "pruner not registered"}
    result = await pruner.run_now()
    return {"started": True, **result}


@router.get("/hourly")
async def get_hourly_stats(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    hours: int = Query(24, ge=1, le=720),
) -> dict:
    """Últimas N horas de hourly_stats. Usado em /observability."""
    from app.repositories.duckdb.connection import db_fetchall

    now = int(datetime.now(timezone.utc).timestamp())
    since = ((now // 3600) - hours) * 3600
    rows = await db_fetchall(
        """
        SELECT hour_start, total_queries, blocked_count
        FROM hourly_stats
        WHERE hour_start >= ?
        ORDER BY hour_start ASC
        """,
        [since],
    )
    return {
        "hours": hours,
        "points": [
            {
                "hour_start": int(r["hour_start"]),
                "total": int(r["total_queries"] or 0),
                "blocked": int(r["blocked_count"] or 0),
            }
            for r in rows
        ],
    }
