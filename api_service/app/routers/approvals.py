"""
/api/v1/approvals — workflow 2nd-approver.

GET    /config           — admin lê setting + lista de actions
PUT    /config           — admin atualiza enabled/actions/ttl_hours
GET    /pending          — lista requests pending
GET    /list             — histórico geral (cap 200)
POST   /{id}/approve     — admin diferente do requester aprova
POST   /{id}/reject      — admin diferente do requester rejeita
POST   /{id}/mark-executed — marca como executado após replay manual
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, Request

from app.core.deps import require_admin, require_capability
from app.services import admin_audit_service, approval_service

router = APIRouter(prefix="/api/v1/approvals", tags=["approvals"])


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


@router.get("/config")
async def get_config(_: Annotated[dict, Depends(require_admin)]) -> dict:
    return await approval_service.get_config()


@router.put("/config")
async def update_config(
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    enabled = body.get("enabled")
    actions = body.get("actions")
    ttl_hours = body.get("ttl_hours")
    out = await approval_service.update_config(
        enabled=bool(enabled) if enabled is not None else None,
        actions=str(actions) if actions is not None else None,
        ttl_hours=int(ttl_hours) if ttl_hours is not None else None,
    )
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="approvals.config.update",
        category="config",
        details=out,
    )
    return out


@router.get("/pending")
async def list_pending(
    _: Annotated[dict, Depends(require_capability("users.read"))],
) -> dict:
    items = await approval_service.list_pending()
    return {"items": items, "count": len(items)}


@router.get("/list")
async def list_all(
    _: Annotated[dict, Depends(require_capability("users.read"))],
    limit: int = 200,
) -> dict:
    items = await approval_service.list_all(limit=limit)
    return {"items": items, "count": len(items)}


@router.post("/{request_id}/approve")
async def approve(
    request_id: Annotated[int, Path(ge=1)],
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    approver_id = user.get("user_id") or _coerce_int(user.get("sub"))
    out = await approval_service.approve(
        request_id, approver_id, user.get("username"),
    )
    if not out.get("ok"):
        raise HTTPException(status_code=400, detail=out)
    await admin_audit_service.log(
        actor_id=approver_id,
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="approvals.approve",
        category="config",
        target_type="approval_request",
        target_id=str(request_id),
    )
    return out


@router.post("/{request_id}/reject")
async def reject(
    request_id: Annotated[int, Path(ge=1)],
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    approver_id = user.get("user_id") or _coerce_int(user.get("sub"))
    out = await approval_service.reject(
        request_id, approver_id, user.get("username"), reason=str(body.get("reason", "")),
    )
    if not out.get("ok"):
        raise HTTPException(status_code=400, detail=out)
    await admin_audit_service.log(
        actor_id=approver_id,
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="approvals.reject",
        category="config",
        target_type="approval_request",
        target_id=str(request_id),
        details={"reason": body.get("reason")},
    )
    return out


@router.post("/{request_id}/execute")
async def execute(
    request_id: Annotated[int, Path(ge=1)],
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    """Dispatcha o handler registrado da action. Replay automático sem
    precisar do request HTTP original."""
    out = await approval_service.execute_request(request_id, executor_user=user)
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="approvals.execute",
        category="config",
        target_type="approval_request",
        target_id=str(request_id),
        details={"result_ok": out.get("ok"), "error": out.get("error") if not out.get("ok") else None},
    )
    if not out.get("ok"):
        raise HTTPException(status_code=400, detail=out)
    return out


@router.get("/handlers")
async def list_handlers(
    _: Annotated[dict, Depends(require_admin)],
) -> dict:
    """Quais actions têm handler dispatchável automaticamente."""
    return {"actions": approval_service.list_action_handlers()}


@router.post("/{request_id}/mark-executed")
async def mark_executed(
    request_id: Annotated[int, Path(ge=1)],
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    ok = await approval_service.mark_executed(request_id, result=body.get("result"))
    if not ok:
        raise HTTPException(status_code=400, detail="request não aprovado")
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="approvals.mark_executed",
        category="config",
        target_type="approval_request",
        target_id=str(request_id),
    )
    return {"ok": True}
