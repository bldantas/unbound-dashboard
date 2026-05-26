"""
/api/v1/auth/oidc — endpoints SSO (Authorization Code).

GET  /config             — admin lê config (sem secret)
PUT  /config             — admin atualiza (string vazia em client_secret preserva valor atual)
POST /probe              — admin valida issuer URL (fetch discovery + JWKS)
GET  /login              — redireciona pro IdP com state CSRF
GET  /callback           — recebe code, troca por tokens, valida id_token,
                           sync/create user, emite JWT local
GET  /public-info        — info pública pra /login.php saber se SSO está habilitado
"""

from __future__ import annotations

from typing import Annotated

import httpx
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


@router.post("/probe")
async def probe_issuer(
    body: dict,
    _: Annotated[dict, Depends(require_admin)],
) -> dict:
    """Valida um issuer URL: fetch do `.well-known/openid-configuration`
    + JWKS (best-effort). Retorna metadata descoberto pra UI mostrar e
    pra admin confirmar antes de salvar.

    Não persiste nada — só faz GETs HTTP. Não exige scopes/client_id.
    """
    issuer = str(body.get("issuer_url") or "").strip().rstrip("/")
    if not issuer.startswith(("http://", "https://")):
        raise HTTPException(status_code=400, detail="issuer_url deve começar com http(s)://")

    discovery_url = f"{issuer}/.well-known/openid-configuration"
    out: dict = {"ok": False, "issuer_url": issuer, "discovery_url": discovery_url}

    try:
        async with httpx.AsyncClient(timeout=10.0, follow_redirects=True) as client:
            r = await client.get(discovery_url, headers={"Accept": "application/json"})
            out["http_status"] = r.status_code
            if r.status_code != 200:
                out["error"] = f"discovery retornou HTTP {r.status_code}"
                return out
            try:
                meta = r.json()
            except ValueError:
                out["error"] = "discovery não retornou JSON válido"
                return out

            # Issuer no discovery deve bater (modulo trailing slash)
            disc_issuer = str(meta.get("issuer") or "").rstrip("/")
            issuer_match = (disc_issuer == issuer)
            out["meta_issuer"] = disc_issuer
            out["issuer_match"] = issuer_match
            for k in (
                "authorization_endpoint", "token_endpoint", "userinfo_endpoint",
                "jwks_uri", "end_session_endpoint",
            ):
                v = meta.get(k)
                if v:
                    out[k] = v
            for k in (
                "scopes_supported", "response_types_supported",
                "id_token_signing_alg_values_supported",
                "grant_types_supported", "code_challenge_methods_supported",
            ):
                v = meta.get(k)
                if isinstance(v, list):
                    out[k] = v

            # JWKS probe (best-effort)
            jwks_uri = meta.get("jwks_uri")
            if jwks_uri:
                try:
                    jr = await client.get(jwks_uri, headers={"Accept": "application/json"})
                    if jr.status_code == 200:
                        jwks = jr.json()
                        keys = jwks.get("keys", []) if isinstance(jwks, dict) else []
                        out["jwks_keys"] = len(keys)
                    else:
                        out["jwks_error"] = f"JWKS HTTP {jr.status_code}"
                except (httpx.HTTPError, ValueError) as exc:
                    out["jwks_error"] = str(exc)[:200]

            out["ok"] = bool(out.get("authorization_endpoint") and out.get("token_endpoint"))
            if not out["ok"]:
                out["error"] = "metadata sem authorization/token endpoint"
    except httpx.HTTPError as exc:
        out["error"] = f"falha de rede: {str(exc)[:200]}"
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
