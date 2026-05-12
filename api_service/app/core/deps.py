"""Dependências FastAPI compartilhadas — auth (Bearer JWT) e RBAC."""

from __future__ import annotations

from typing import Annotated

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.security import JWTError, decode_token
from app.services.jwt_denylist import is_user_revoked

_bearer = HTTPBearer(auto_error=True)


async def require_auth(
    credentials: Annotated[HTTPAuthorizationCredentials, Depends(_bearer)],
) -> dict:
    """
    Exige JWT válido e não-revogado. Retorna o payload (com `sub` + `role`).

    Validações:
    1. Assinatura + `exp` (via decode_token)
    2. Denylist Redis (per-user revocation): se `iat` < `revoked_at`,
       rejeita com 401 — usado quando admin desativa/banir conta.
    """
    try:
        payload = decode_token(credentials.credentials)
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado",
            headers={"WWW-Authenticate": "Bearer"},
        ) from None

    # Denylist check — fail-open se Redis indisponível
    try:
        user_id = int(payload.get("sub", 0))
        iat = payload.get("iat")
    except (TypeError, ValueError):
        user_id = 0
        iat = None
    if user_id and await is_user_revoked(user_id, iat):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token revogado — conta foi desativada ou banida",
            headers={"WWW-Authenticate": "Bearer"},
        )

    return payload


async def require_admin(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """Exige role = 'admin' no payload do JWT."""
    if payload.get("role") != "admin":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado: requer privilégios de administrador",
        )
    return payload
