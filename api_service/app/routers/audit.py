"""Endpoints /api/v1/audit/* — trilha de auditoria.

- /updates              — histórico de update/restore (V5)
- /admin/list           — trilha geral de ações admin (V15, novo)
- /admin/export-csv     — export CSV do admin_audit (V15)
- /admin/retention/*    — setting + prune-now (admin only)
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Query, Request
from fastapi.responses import PlainTextResponse, Response

from app.core.deps import require_capability, resolve_viewer_org_id
from app.repositories.duckdb import settings_repo
from app.services import admin_audit_service, audit_service, pdf_report_service

router = APIRouter(prefix="/api/v1/audit", tags=["audit"])


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


@router.get("/updates")
async def list_update_audit(
    _: Annotated[dict, Depends(require_capability("users.read"))],
    limit: int = Query(50, ge=1, le=500),
) -> dict:
    """Histórico de updates/restores aplicados via UI."""
    items = await audit_service.list_recent(limit=limit)
    return {"audit": items, "count": len(items)}


@router.get("/admin/list")
async def list_admin_audit(
    user: Annotated[dict, Depends(require_capability("users.read"))],
    category: str | None = Query(None, max_length=32),
    actor_id: int | None = Query(None),
    action_prefix: str | None = Query(None, max_length=80),
    from_ts: int | None = Query(None, ge=0),
    to_ts: int | None = Query(None, ge=0),
    limit: int = Query(100, ge=1, le=500),
    offset: int = Query(0, ge=0),
) -> dict:
    """Lista filtrada do admin_audit."""
    viewer_org = await resolve_viewer_org_id(user)
    return await admin_audit_service.list_filtered(
        category=category, actor_id=actor_id, action_prefix=action_prefix,
        from_ts=from_ts, to_ts=to_ts, limit=limit, offset=offset,
        viewer_org_id=viewer_org,
    )


@router.get("/admin/export-csv")
async def export_admin_audit_csv(
    request: Request,
    user: Annotated[dict, Depends(require_capability("users.read"))],
    category: str | None = Query(None, max_length=32),
    actor_id: int | None = Query(None),
    action_prefix: str | None = Query(None, max_length=80),
    from_ts: int | None = Query(None, ge=0),
    to_ts: int | None = Query(None, ge=0),
) -> PlainTextResponse:
    """Export CSV (cap 10k linhas). Loga o próprio export no audit."""
    viewer_org = await resolve_viewer_org_id(user)
    csv_str = await admin_audit_service.export_csv(
        category=category, actor_id=actor_id, action_prefix=action_prefix,
        from_ts=from_ts, to_ts=to_ts,
        viewer_org_id=viewer_org,
    )
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="audit.export_csv",
        category="data_export",
        details={"filters": {
            "category": category, "actor_id": actor_id,
            "action_prefix": action_prefix, "from_ts": from_ts, "to_ts": to_ts,
        }},
    )
    return PlainTextResponse(
        csv_str,
        media_type="text/csv; charset=utf-8",
        headers={"Content-Disposition": 'attachment; filename="admin_audit.csv"'},
    )


@router.get("/admin/export-pdf")
async def export_admin_audit_pdf(
    request: Request,
    user: Annotated[dict, Depends(require_capability("users.read"))],
    category: str | None = Query(None, max_length=32),
    actor_id: int | None = Query(None),
    action_prefix: str | None = Query(None, max_length=80),
    from_ts: int | None = Query(None, ge=0),
    to_ts: int | None = Query(None, ge=0),
) -> Response:
    """Export PDF (cap 2000 linhas — pra mais use CSV). Loga em audit."""
    viewer_org = await resolve_viewer_org_id(user)
    out = await admin_audit_service.list_filtered(
        category=category, actor_id=actor_id, action_prefix=action_prefix,
        from_ts=from_ts, to_ts=to_ts, limit=2000, offset=0,
        viewer_org_id=viewer_org,
    )
    pdf_bytes = pdf_report_service.admin_audit_pdf(
        out["items"],
        filters={"category": category, "action_prefix": action_prefix, "from_ts": from_ts},
    )
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="audit.export_pdf",
        category="data_export",
        details={"filters": {"category": category, "action_prefix": action_prefix, "from_ts": from_ts}, "rows": len(out["items"])},
    )
    return Response(
        content=pdf_bytes,
        media_type="application/pdf",
        headers={"Content-Disposition": 'attachment; filename="admin_audit.pdf"'},
    )


@router.get("/admin/retention/settings")
async def get_audit_retention(
    _: Annotated[dict, Depends(require_capability("users.read"))],
) -> dict:
    days = await settings_repo.get_int("audit_retention_days", 365)
    return {"days": days, "default": 365}


@router.put("/admin/retention/settings")
async def update_audit_retention(
    body: dict,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    days = int(body.get("days", 365))
    if days < 30 or days > 3650:
        raise HTTPException(status_code=400, detail="days must be 30..3650")
    await settings_repo.bulk_upsert([
        {"setting_key": "audit_retention_days", "setting_value": str(days)}
    ])
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="audit.retention.update",
        category="config",
        details={"days": days},
    )
    return {"days": days}


@router.post("/admin/prune-now")
async def prune_admin_audit(
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    days = await settings_repo.get_int("audit_retention_days", 365)
    n = await admin_audit_service.prune_old(days)
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="audit.prune_now",
        category="config",
        details={"days": days, "pruned": n},
    )
    return {"pruned": n, "days": days}
