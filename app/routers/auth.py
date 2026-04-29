from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from typing import Annotated

from app.core.deps import require_auth
from app.domain.user import AccountInactive, AccountLocked, InvalidCredentials
from app.repositories.duckdb.user_repo import UserRepository
from app.services.auth_service import AuthService

router = APIRouter(prefix="/api/v2/auth", tags=["auth"])


class LoginRequest(BaseModel):
    username: str
    password: str


class TokenResponse(BaseModel):
    access_token: str
    token_type: str
    role: str


async def _get_auth_service() -> AuthService:
    return AuthService(UserRepository())


@router.post("/login", response_model=TokenResponse)
async def login(
    body: LoginRequest,
    svc: AuthService = Depends(_get_auth_service),
) -> TokenResponse:
    try:
        result = await svc.login(body.username, body.password)
    except (InvalidCredentials, AccountInactive):
        # Mesma mensagem para não revelar qual campo está errado
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Credenciais inválidas",
        )
    except AccountLocked:
        raise HTTPException(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            detail="Conta bloqueada temporariamente. Tente novamente em 15 minutos.",
        )
    return TokenResponse(**result)


@router.get("/me")
async def me(
    payload: Annotated[dict, Depends(require_auth)],
    repo: UserRepository = Depends(lambda: UserRepository()),
) -> dict:
    user = await repo.find_by_id(int(payload["sub"]))
    if user is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Usuário não encontrado")
    return {
        "id": user.id,
        "username": user.username,
        "role": user.role.value,
        "email": user.email,
        "is_active": user.is_active,
    }


@router.post("/logout", status_code=status.HTTP_204_NO_CONTENT)
async def logout(
    _: Annotated[dict, Depends(require_auth)],
) -> None:
    # JWT stateless: o cliente descarta o token localmente.
    # Para invalidação real implementar denylist no Redis (futuro).
    return None
