"""Dependências FastAPI compartilhadas — auth (Bearer JWT) e RBAC."""

from __future__ import annotations

from typing import Annotated

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.security import JWTError, decode_token

_bearer = HTTPBearer(auto_error=True)


async def require_auth(
    credentials: Annotated[HTTPAuthorizationCredentials, Depends(_bearer)],
) -> dict:
    """Exige JWT válido. Retorna o payload (com `sub` + `role`)."""
    try:
        return decode_token(credentials.credentials)
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado",
            headers={"WWW-Authenticate": "Bearer"},
        ) from None


async def require_admin(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """Exige role = 'admin' no payload do JWT."""
    if payload.get("role") != "admin":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado: requer privilégios de administrador",
        )
    return payload
