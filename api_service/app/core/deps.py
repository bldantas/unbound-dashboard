"""Dependências FastAPI compartilhadas — auth (Bearer JWT) e RBAC."""

from __future__ import annotations

from typing import Annotated

from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.security import JWTError, decode_token
from app.services import sessions
from app.services.jwt_denylist import is_token_hash_revoked, is_user_revoked

_bearer = HTTPBearer(auto_error=True)


async def require_auth(
    request: Request,
    credentials: Annotated[HTTPAuthorizationCredentials, Depends(_bearer)],
) -> dict:
    """
    Exige JWT válido e não-revogado. Retorna o payload.

    Validações:
    1. Assinatura + `exp` (via decode_token)
    2. Denylist per-user: se `iat` < `revoked_at`, rejeita 401.
       Usado quando admin desativa conta — corta todas as sessões do user.
    3. Denylist por token-hash: se a sessão específica foi revogada
       (ex: user clicou "Encerrar sessão dessa máquina" no perfil).

    Side-effect: registra a sessão em Redis pra "Sessões Ativas" UI.
    """
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
