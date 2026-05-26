"""
/api/v1/external-health — recebe probes de monitores externos.

POST /report — body com {probe_source, target_host, query_name, success,
                          latency_ms, response_correct, error?, probed_at?}
                Aceita auth via X-Api-Token (api_tokens V6) ou JWT admin.
                Recomendado: token dedicado 'external-monitor' com role admin.

GET /list  — últimos N probes (admin/readonly_admin)
GET /sla   — métricas agregadas (SLA, P50/P95/P99, etc)
GET /sources — distintos probe_source recentes
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability
from app.services import external_health_service

router = APIRouter(prefix="/api/v1/external-health", tags=["external-health"])


@router.post("/report")
async def report(
    body: dict,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    pid = await external_health_service.record_probe(body)
    return {"ok": True, "id": pid}


@router.get("/list")
async def list_probes(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    probe_source: str | None = Query(None, max_length=80),
    hours: int = Query(24, ge=1, le=720),
    limit: int = Query(200, ge=1, le=2000),
) -> dict:
    items = await external_health_service.list_recent(
        probe_source=probe_source, hours=hours, limit=limit,
    )
    return {"items": items, "count": len(items)}


@router.get("/sla")
async def get_sla(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    hours: int = Query(24, ge=1, le=720),
    probe_source: str | None = Query(None, max_length=80),
) -> dict:
    return await external_health_service.sla(hours=hours, probe_source=probe_source)


@router.get("/sources")
async def list_sources(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    hours: int = Query(168, ge=1, le=8760),
) -> dict:
    sources = await external_health_service.list_sources(hours=hours)
    return {"sources": sources, "count": len(sources)}
