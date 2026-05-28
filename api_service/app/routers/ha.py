"""
Endpoints /api/v1/ha — observabilidade do cluster Unbound + manual failover.

GET    /status            — snapshot agregado (KPIs + peers)
GET    /peers             — lista peers
POST   /peers             — cria peer (retorna token raw 1x)
PUT    /peers/{id}        — atualiza campos
DELETE /peers/{id}        — remove peer
POST   /peers/{id}/check  — força healthcheck imediato
POST   /failover          — manual cutover (body: {promote_id, demote_id?})
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, Request
from fastapi.responses import JSONResponse

from app.core.deps import require_capability
from app.services import admin_audit_service, approval_service, ha_service

router = APIRouter(prefix="/api/v1/ha", tags=["ha"])


async def _approval_handler_failover(payload: dict) -> dict:
    promote_id = int(payload.get("promote_id", 0))
    demote_id = payload.get("demote_id")
    demote_id = int(demote_id) if demote_id else None
    if promote_id < 1:
        return {"ok": False, "error": "promote_id ausente no payload"}
    return await ha_service.manual_failover(promote_id, demote_id)


async def _approval_handler_peer_delete(payload: dict) -> dict:
    pid = int(payload.get("peer_id", 0))
    if pid < 1:
        return {"ok": False, "error": "peer_id ausente"}
    deleted = await ha_service.delete_peer(pid)
    return {"ok": deleted, "deleted_peer_id": pid}


approval_service.register_action_handler("ha.failover", _approval_handler_failover)
approval_service.register_action_handler("ha.peer.delete", _approval_handler_peer_delete)


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


@router.get("/status")
async def status(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await ha_service.cluster_status()


@router.get("/peers")
async def list_peers(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    peers = await ha_service.list_peers()
    return {"peers": peers, "count": len(peers)}


@router.post("/peers", status_code=201)
async def create_peer(
    body: dict,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    label = str(body.get("label", "")).strip()
    api_url = str(body.get("api_url", "")).strip()
    role = str(body.get("role", "secondary"))
    priority = int(body.get("priority", 100))
    keep_raw = bool(body.get("keep_raw", False))
    existing_token = (str(body.get("existing_token") or "")).strip() or None
    try:
        out = await ha_service.create_peer(
            label, api_url, role, priority,
            keep_raw=keep_raw,
            existing_token=existing_token,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="ha.peer.create",
        category="host",
        target_type="ha_peer",
        target_id=str(out["id"]),
        details={"label": label, "role": role},
    )
    return out


@router.put("/peers/{peer_id}")
async def update_peer(
    peer_id: Annotated[int, Path(ge=1)],
    body: dict,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    try:
        ok = await ha_service.update_peer(peer_id, body)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    if not ok:
        raise HTTPException(status_code=400, detail="nenhum campo conhecido pra atualizar")
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="ha.peer.update",
        category="host",
        target_type="ha_peer",
        target_id=str(peer_id),
        details={"fields": list(body.keys())},
    )
    return {"updated": True}


@router.put("/peers/{peer_id}/token")
async def set_peer_token(
    peer_id: Annotated[int, Path(ge=1)],
    body: dict,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
) -> dict:
    """Substitui o token de um peer (usado pra "fechar o link" quando
    ambos os lados foram criados sem coordenar)."""
    token = str(body.get("token") or "").strip()
    if not token:
        raise HTTPException(status_code=400, detail="token obrigatório")
    try:
        ok = await ha_service.set_peer_token(peer_id, token)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    if not ok:
        raise HTTPException(status_code=404, detail="peer não encontrado")
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="ha.peer.token_replaced",
        category="host",
        target_type="ha_peer",
        target_id=str(peer_id),
    )
    return {"updated": True}


@router.delete("/peers/{peer_id}", response_model=None)
async def delete_peer(
    peer_id: Annotated[int, Path(ge=1)],
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
):
    ip = request.client.host if request.client else None
    try:
        await approval_service.enforce_approval(
            user=user, request_ip=ip,
            action="ha.peer.delete",
            description=f"Excluir peer HA #{peer_id}",
            payload={"peer_id": peer_id},
        )
    except approval_service.ApprovalRequired as exc:
        return JSONResponse(
            {"approval_pending": True, "request_id": exc.request_id,
             "message": "Aguardando aprovação"},
            status_code=202,
        )
    ok = await ha_service.delete_peer(peer_id)
    if not ok:
        raise HTTPException(status_code=404, detail="peer não encontrado")
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=ip,
        action="ha.peer.delete",
        category="host",
        target_type="ha_peer",
        target_id=str(peer_id),
    )
    return JSONResponse(content=None, status_code=204)


@router.post("/peers/{peer_id}/check")
async def check_peer(
    peer_id: Annotated[int, Path(ge=1)],
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await ha_service.check_peer(peer_id)


@router.post("/failover", response_model=None)
async def manual_failover(
    body: dict,
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
):
    promote_id = int(body.get("promote_id", 0))
    demote_id = body.get("demote_id")
    demote_id = int(demote_id) if demote_id else None
    if promote_id < 1:
        raise HTTPException(status_code=400, detail="promote_id obrigatório")

    ip = request.client.host if request.client else None
    try:
        await approval_service.enforce_approval(
            user=user, request_ip=ip,
            action="ha.failover",
            description=f"Promover peer #{promote_id} → primary" + (f" + demover #{demote_id}" if demote_id else ""),
            payload={"promote_id": promote_id, "demote_id": demote_id},
        )
    except approval_service.ApprovalRequired as exc:
        return JSONResponse(
            {"approval_pending": True, "request_id": exc.request_id,
             "message": "Aguardando aprovação de outro admin em /approvals.php"},
            status_code=202,
        )

    out = await ha_service.manual_failover(promote_id, demote_id)
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="ha.failover",
        category="config",
        target_type="ha_peer",
        target_id=str(promote_id),
        details={"promote_id": promote_id, "demote_id": demote_id, "result": out},
    )
    if not out.get("ok"):
        raise HTTPException(status_code=400, detail=out)
    return out
