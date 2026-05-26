"""
/api/v1/auth/oidc — endpoints SSO (Authorization Code).

GET  /config             — admin lê config (sem secret)
PUT  /config             — admin atualiza (string vazia em client_secret preserva valor atual)
GET  /login              — redireciona pro IdP com state CSRF
GET  /callback           — recebe code, troca por tokens, valida id_token,
                           sync/create user, emite JWT local
GET  /public-info        — info pública pra /login.php saber se SSO está habilitado
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Query, Request
from fastapi.responses import RedirectResponse

from app.core.config import settings
from app.core.deps import require_admin, require_auth
from app.core.security import create_access_token
from app.services import admin_audit_service, oidc_service

router = APIRouter(prefix="/api/v1/auth/oidc", tags=["oidc"])


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


@router.get("/public-info")
async def public_info() -> dict:
    """Sem auth — login.php usa pra decidir se mostra botão 'Entrar com SSO'."""
    cfg = await oidc_service.get_config(include_secret=False)
    return {
        "enabled": cfg["enabled"],
        "issuer_url": cfg["issuer_url"] if cfg["enabled"] else "",
    }


@router.get("/config")
async def get_config(_: Annotated[dict, Depends(require_admin)]) -> dict:
    return await oidc_service.get_config(include_secret=False)


@router.put("/config")
async def update_config(
    body: dict,
    user: Annotated[dict, Depends(require_admin)],
    request: Request,
) -> dict:
    try:
        out = await oidc_service.update_config(body)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="oidc.config.update",
        category="config",
        details={"fields": list(body.keys())},
    )
    return out


def _build_callback_url(request: Request) -> str:
    """Constrói o callback URL absoluto a partir do request atual."""
    # Respeita X-Forwarded-Proto/Host (Apache reverso)
    scheme = request.headers.get("x-forwarded-proto", request.url.scheme)
    host = request.headers.get("x-forwarded-host", request.headers.get("host", ""))
    if not host:
        host = request.url.netloc
    return f"{scheme}://{host}/api/v1/auth/oidc/callback"


@router.get("/login")
async def oidc_login(request: Request) -> RedirectResponse:
    """Sem auth — quem chega aqui ainda não fez login. Redireciona pro IdP."""
    callback = _build_callback_url(request)
    try:
        out = await oidc_service.build_auth_url(callback)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    return RedirectResponse(url=out["url"], status_code=302)


@router.get("/callback")
async def oidc_callback(
    request: Request,
    code: str = Query(...),
    state: str = Query(...),
    error: str | None = Query(None),
) -> RedirectResponse:
    """IdP redirecionou pra cá com ?code=&state=. Troca por tokens + cria sessão."""
    ip = request.client.host if request.client else None

    if error:
        await admin_audit_service.log(
            actor_id=None, actor_username=None, actor_ip=ip,
            action="oidc.callback.idp_error", category="auth",
            details={"error": error},
        )
        return RedirectResponse(url="/login.php?error=oidc_idp", status_code=302)

    callback = _build_callback_url(request)
    try:
        user = await oidc_service.handle_callback(code, state, callback)
    except ValueError as exc:
        await admin_audit_service.log(
            actor_id=None, actor_username=None, actor_ip=ip,
            action="oidc.callback.fail", category="auth",
            details={"reason": str(exc)},
        )
        return RedirectResponse(
            url=f"/login.php?error=oidc&detail={str(exc)[:100]}",
            status_code=302,
        )

    # Emite JWT local
    token = create_access_token({
        "sub": str(user["id"]),
        "role": user["role"],
        "user_id": user["id"],
        "username": user["username"],
    })

    await admin_audit_service.log(
        actor_id=user["id"], actor_username=user["username"], actor_ip=ip,
        action="login.oidc.success", category="auth",
        details={"email": user.get("email")},
    )

    # Redireciona pro frontend com JWT em fragment (UI captura via window.location.hash)
    return RedirectResponse(url=f"/login.php#oidc={token}", status_code=302)
