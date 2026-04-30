from typing import Annotated

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.core.security import JWTError, decode_token
from app.repositories.duckdb.connection import get_db
from app.repositories.duckdb.user_repo import UserRepository
from app.repositories.redis.connection import get_redis

_bearer = HTTPBearer()


async def get_user_repo(db=Depends(get_db)) -> UserRepository:
    return UserRepository(db)


async def require_auth(
    credentials: Annotated[HTTPAuthorizationCredentials, Depends(_bearer)],
) -> dict:
    """Dependência FastAPI: exige JWT válido. Retorna payload do token."""
    try:
        payload = decode_token(credentials.credentials)
        return payload
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido ou expirado",
            headers={"WWW-Authenticate": "Bearer"},
        )


async def require_admin(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """Dependência FastAPI: exige role=admin."""
    if payload.get("role") != "admin":
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Acesso negado")
    return payload


__all__ = ["get_db", "get_redis", "get_user_repo", "require_auth", "require_admin"]
