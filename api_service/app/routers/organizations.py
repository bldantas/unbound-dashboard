"""
/api/v1/organizations — CRUD de orgs (multi-tenant infra v2.80).

GET    /          — lista orgs (admin only) + user_count
POST   /          — cria org com slug único
PUT    /{id}      — atualiza name/description/is_active (slug é imutável)
DELETE /{id}      — bloqueia se há users vinculados
POST   /assign-user — atribui org_id a um usuário (org_id null = system global)
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Path, Request

from app.core.deps import require_admin
from app.services import admin_audit_service, organizations_service

router = APIRouter(prefix="/api/v1/organizations", tags=["organizations"])


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


@router.get("/")
async def list_orgs(
    _: Annotated[dict, Depends(require_admin)],
) -> dict:
    items = await organizations_service.list_orgs()
    return {"items": items, "count": len(items)}


@router.post("/", status_code=201)
async def create_org(
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    try:
        out = await organizations_service.create_org(
            name=str(body.get("name", "")),
            slug=str(body.get("slug", "")),
            description=str(body.get("description", "")),
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="org.create", category="user",
        target_type="org", target_id=str(out.get("id", "?")),
        details={"name": out.get("name"), "slug": out.get("slug")},
    )
    return out


@router.put("/{org_id}")
async def update_org(
    org_id: Annotated[int, Path(ge=1)],
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    try:
        ok = await organizations_service.update_org(org_id, body)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    if not ok:
        raise HTTPException(status_code=400, detail="nada pra atualizar")
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="org.update", category="user",
        target_type="org", target_id=str(org_id),
        details={"fields": list(body.keys())},
    )
    return {"updated": True}


@router.delete("/{org_id}", status_code=204)
async def delete_org(
    org_id: Annotated[int, Path(ge=1)],
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> None:
    out = await organizations_service.delete_org(org_id)
    if not out.get("ok"):
        raise HTTPException(status_code=400, detail=out)
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="org.delete", category="user",
        target_type="org", target_id=str(org_id),
    )


@router.post("/assign-user")
async def assign_user(
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    user_id = int(body.get("user_id", 0))
    org_id = body.get("org_id")
    org_id = int(org_id) if org_id else None
    if user_id < 1:
        raise HTTPException(status_code=400, detail="user_id obrigatório")
    ok = await organizations_service.assign_user(user_id, org_id)
    if not ok:
        raise HTTPException(status_code=404, detail="user ou org não encontrados")
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="org.assign_user", category="user",
        target_type="user", target_id=str(user_id),
        details={"org_id": org_id},
    )
    return {"ok": True}
