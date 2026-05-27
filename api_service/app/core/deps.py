"""Dependências FastAPI compartilhadas — auth (Bearer JWT ou API token) e RBAC."""

from __future__ import annotations

from typing import Annotated

from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.security import JWTError, decode_token
from app.services import sessions
from app.services.jwt_denylist import is_token_hash_revoked, is_user_revoked

# auto_error=False — temos auth alternativa (X-Api-Token) então não levantamos
# 401 imediato se o Bearer estiver ausente; tentamos o api token antes.
_bearer = HTTPBearer(auto_error=False)


async def require_auth(
    request: Request,
    credentials: Annotated[HTTPAuthorizationCredentials | None, Depends(_bearer)] = None,
) -> dict:
    """
    Aceita JWT (Authorization: Bearer ...) OU API Token (X-Api-Token: ...).
    Retorna um payload normalizado em ambos os casos:
      - JWT → payload do decode (sub, role, iat, exp, ...)
      - API token → {"sub": "api-token", "role": "admin", "auth_kind": "api_token",
                     "api_token_id": N, "api_token_label": "..."}

    API tokens são considerados "admin" pra fins de RBAC — geram acesso
    pleno ao agent. Granularidade futura pode mudar isso (capabilities
    por token).

    Validações (JWT path):
    1. Assinatura + `exp` (via decode_token)
    2. Denylist per-user: se `iat` < `revoked_at`, rejeita 401.
       Usado quando admin desativa conta — corta todas as sessões do user.
    3. Denylist por token-hash: se a sessão específica foi revogada
       (ex: user clicou "Encerrar sessão dessa máquina" no perfil).

    Side-effect: registra a sessão em Redis pra "Sessões Ativas" UI.
    """
    # === API Token path (header X-Api-Token) ===
    api_token = request.headers.get("x-api-token")
    if api_token:
        from app.services import api_tokens

        xff = request.headers.get("x-forwarded-for", "")
        source_ip = xff.split(",")[0].strip() if xff else (request.client.host if request.client else "")
        meta = await api_tokens.verify(api_token, source_ip=source_ip)
        if meta is None:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="API token inválido ou revogado",
            )
        return {
            "sub": "api-token",
            "role": "admin",
            "auth_kind": "api_token",
            "api_token_id": meta["id"],
            "api_token_label": meta["label"],
        }

    # === JWT path (header Authorization: Bearer ...) ===
    if credentials is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Authorization ausente — use Bearer JWT ou X-Api-Token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    token = credentials.credentials
    try:
        payload = decode_token(token)
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado",
            headers={"WWW-Authenticate": "Bearer"},
        ) from None

    try:
        user_id = int(payload.get("sub", 0))
        iat = payload.get("iat")
        exp = payload.get("exp")
    except (TypeError, ValueError):
        user_id = 0
        iat = None
        exp = None

    if user_id and await is_user_revoked(user_id, iat):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token revogado — conta foi desativada ou banida",
            headers={"WWW-Authenticate": "Bearer"},
        )

    token_hash = sessions.hash_token(token)
    if await is_token_hash_revoked(token_hash):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Sessão encerrada — faça login novamente",
            headers={"WWW-Authenticate": "Bearer"},
        )

    # Tracking: best-effort, não derruba a request se Redis off
    if user_id and isinstance(iat, int) and isinstance(exp, int):
        # IP do cliente atrás do proxy (Apache passa X-Forwarded-For)
        xff = request.headers.get("x-forwarded-for", "")
        ip = xff.split(",")[0].strip() if xff else (request.client.host if request.client else "")
        ua = request.headers.get("user-agent", "")
        await sessions.track(user_id, token_hash, ip or "?", ua or "?", iat, exp)

    return payload


async def resolve_viewer_org_id(payload: dict) -> int | None:
    """Resolve a org_id do caller pra filtros multi-tenant.

    - API token → None (sempre global, age como system admin).
    - User com `org_id` NULL no DB → None (system admin, vê tudo).
    - User com `org_id = N` → N (vê globais + da própria org).
    """
    if payload.get("auth_kind") == "api_token":
        return None
    try:
        user_id = int(payload.get("sub", 0))
    except (TypeError, ValueError):
        return None
    if user_id < 1:
        return None
    from app.repositories.duckdb.connection import db_fetchone
    row = await db_fetchone("SELECT org_id FROM users WHERE id = ?", [user_id])
    if not row or row.get("org_id") is None:
        return None
    return int(row["org_id"])


async def require_admin(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """Exige role = 'admin' no payload do JWT."""
    if payload.get("role") != "admin":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado: requer privilégios de administrador",
        )
    return payload


def require_capability(capability: str):
    """
    Factory de dependency que valida uma capability RBAC.

    Uso em endpoints:
        @router.put("/foo", dependencies=[Depends(require_capability("config.write"))])

    Ou injetando payload:
        async def foo(payload: dict = Depends(require_capability("alerts.resolve"))):

    Capability inexistente = 403 (deny by default).
    """
    from app.core.rbac import can

    async def _dep(payload: Annotated[dict, Depends(require_auth)]) -> dict:
        role = payload.get("role")
        if not can(role, capability):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Acesso negado: requer permissão '{capability}'",
            )
        return payload

    return _dep
