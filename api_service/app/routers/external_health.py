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

from fastapi import APIRouter, Depends, HTTPException, Query, Request

from app.core.deps import require_admin, require_capability
from app.repositories.duckdb import settings_repo
from app.services import admin_audit_service, external_health_service

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


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


@router.get("/retention/settings")
async def get_retention(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return {
        "days": await settings_repo.get_int("external_health_retention_days", 90),
        "default": 90,
        "last_run": await settings_repo.get("external_health_pruner_last_run", "") or "",
        "last_deleted": await settings_repo.get_int("external_health_pruner_last_deleted", 0),
    }


@router.put("/retention/settings")
async def update_retention(
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    days = int(body.get("days", 90))
    if days < 7 or days > 3650:
        raise HTTPException(status_code=400, detail="days must be 7..3650")
    await settings_repo.bulk_upsert([
        {"setting_key": "external_health_retention_days", "setting_value": str(days)}
    ])
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="external_health.retention.update",
        category="config",
        details={"days": days},
    )
    return {"days": days}
