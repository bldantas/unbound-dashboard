"""
/api/v1/notifications — feed + ops de notificações (alerts + anomalies).

Tabela canônica = `alerts`. Anomalias viram alerts com type `anomaly_*`.
A bell faz polling de `/feed` (curto, ativos+não-dismissed) e abre o
WS `/api/v1/ws/notifications` pra push em tempo real. A página
`/notifications.php` usa `/list` com filtros + ações de dismiss/resolve.
"""

from __future__ import annotations

from datetime import datetime
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, Query

from app.core.deps import require_auth, require_capability, resolve_viewer_org_id
from app.repositories.duckdb import alert_repo, settings_repo
from app.services import notification_prefs_service

router = APIRouter(prefix="/api/v1/notifications", tags=["notifications"])


def _format_row(r: dict) -> dict:
    t = str(r["type"] or "")
    category = "anomaly" if t.startswith("anomaly_") else "alert"
    url = "/anomalies.php" if category == "anomaly" else "/alerts.php"
    def to_iso(v):
        return v.isoformat() if isinstance(v, datetime) else (str(v) if v else None)
    return {
        "id": int(r["id"]),
        "category": category,
        "type": t,
        "severity": str(r["severity"] or "info"),
        "message": str(r.get("message") or ""),
        "started_at": to_iso(r.get("started_at")),
        "resolved_at": to_iso(r.get("resolved_at")),
        "is_dismissed": bool(r.get("is_dismissed", False)),
        "duration_secs": int(r.get("duration_secs") or 0),
        "url": url,
    }


@router.get("/feed")
async def feed(
    payload: Annotated[dict, Depends(require_capability("dashboard.read"))],
    limit: int = Query(30, ge=1, le=200),
) -> dict:
    """Bell payload — só ativos + não-dismissed, mais recente primeiro."""
    viewer_org = await resolve_viewer_org_id(payload)
    out = await alert_repo.list_filtered(
        resolved=False, dismissed=False, limit=limit, offset=0,
        viewer_org_id=viewer_org,
    )
    items = [_format_row(r) for r in out["items"]]
    return {"items": items, "count": len(items)}


@router.get("/list")
async def list_full(
    payload: Annotated[dict, Depends(require_capability("dashboard.read"))],
    severity: str | None = Query(None, pattern="^(critical|warning|info)$"),
    type_prefix: str | None = Query(None, max_length=100),
    resolved: bool | None = Query(None),
    dismissed: bool | None = Query(None),
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
) -> dict:
    """Feed completo pra página dedicada — com filtros e paginação."""
    viewer_org = await resolve_viewer_org_id(payload)
    out = await alert_repo.list_filtered(
        severity=severity,
        type_prefix=type_prefix,
        resolved=resolved,
        dismissed=dismissed,
        limit=limit,
        offset=offset,
        viewer_org_id=viewer_org,
    )
    return {
        "items": [_format_row(r) for r in out["items"]],
        "total": out["total"],
        "limit": limit,
        "offset": offset,
    }


@router.post("/{alert_id}/dismiss", status_code=204)
async def dismiss(
    _: Annotated[dict, Depends(require_capability("alerts.resolve"))],
    alert_id: Annotated[int, Path(ge=1)],
) -> None:
    """Mark-as-read server-side. Some do bell sem resolver o alerta."""
    ok = await alert_repo.dismiss_by_id(alert_id)
    if not ok:
        raise HTTPException(status_code=404, detail="not found or already dismissed")


@router.post("/dismiss-all")
async def dismiss_all(
    _: Annotated[dict, Depends(require_capability("alerts.resolve"))],
) -> dict:
    """Mark-all-as-read. Usado pelo botão 'Marcar todas como lidas' do bell."""
    n = await alert_repo.dismiss_all_active()
    return {"dismissed": n}


@router.get("/retention/settings")
async def get_retention(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    days = await settings_repo.get_int("notifications_retention_days", 30)
    return {"days": days, "default": 30}


@router.put("/retention/settings")
async def update_retention(
    body: dict,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    days = int(body.get("days", 30))
    if days < 1 or days > 365:
        raise HTTPException(status_code=400, detail="days must be 1..365")
    await settings_repo.bulk_upsert([
        {"setting_key": "notifications_retention_days", "setting_value": str(days)}
    ])
    return {"days": days}


@router.post("/prune-now")
async def prune_now(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    """Roda manualmente o prune (admin-only, usa setting atual)."""
    days = await settings_repo.get_int("notifications_retention_days", 30)
    n = await alert_repo.prune_old(days)
    return {"pruned": n, "days": days}


# ---------------------------------------------------------------------------
# Per-user notification preferences (V26)
# ---------------------------------------------------------------------------


def _resolve_user_id(payload: dict) -> int:
    if payload.get("auth_kind") == "api_token":
        raise HTTPException(status_code=403, detail="prefs não disponíveis via API token")
    try:
        return int(payload.get("sub", 0))
    except (TypeError, ValueError):
        raise HTTPException(status_code=400, detail="sub inválido no payload")


@router.get("/prefs")
async def get_my_prefs(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    user_id = _resolve_user_id(payload)
    if user_id < 1:
        raise HTTPException(status_code=400, detail="user_id inválido")
    return await notification_prefs_service.get(user_id)


@router.put("/prefs")
async def update_my_prefs(
    body: dict,
    payload: Annotated[dict, Depends(require_auth)],
) -> dict:
    user_id = _resolve_user_id(payload)
    if user_id < 1:
        raise HTTPException(status_code=400, detail="user_id inválido")
    try:
        return await notification_prefs_service.update(user_id, body)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
