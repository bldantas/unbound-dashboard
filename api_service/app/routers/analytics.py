"""Endpoints analíticos /api/v1/analytics (B.3).

Todos os endpoints aceitam ?window=1h|24h|7d|30d. Default 24h.
Capability `dashboard.read` (qualquer usuário autenticado) — sem dados sensíveis.
"""

from __future__ import annotations

import csv
import io
from datetime import datetime, timezone
from typing import Annotated, Literal

from fastapi import APIRouter, Depends, Query
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


@router.get("/queries/search")
async def search_queries(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    client_ip: str = Query("", max_length=64),
    domain: str = Query("", max_length=255),
    query_type: str = Query("", max_length=10),
    action: str = Query("", max_length=30),
    page: int = Query(1, ge=1, le=10000),
    per_page: int = Query(50, ge=1, le=200),
) -> dict:
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
    }


@router.get("/queries/export-csv")
async def export_csv(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    window: WindowParam = Query("24h"),
    client_ip: str = Query("", max_length=64),
    domain: str = Query("", max_length=255),
    query_type: str = Query("", max_length=10),
    action: str = Query("", max_length=30),
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

    buf = io.StringIO()
    writer = csv.writer(buf)
    writer.writerow(["timestamp_iso", "timestamp_epoch", "client_ip", "domain", "query_type", "action"])
    for r in rows:
        iso = datetime.fromtimestamp(r["timestamp"], tz=timezone.utc).isoformat()
        writer.writerow([iso, r["timestamp"], r["client_ip"], r["domain"], r["query_type"], r["action"]])
    buf.seek(0)

    filename = f"unbound-queries-{datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')}.csv"
    return StreamingResponse(
        iter([buf.getvalue()]),
        media_type="text/csv",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )
