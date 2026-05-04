"""Endpoints de autenticação — login, me, logout."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request, status
from pydantic import BaseModel

from app.core.config import settings
from app.core.deps import require_auth
from app.core.rate_limit import limiter
from app.repositories.duckdb import user_repo
from app.services import auth_service

router = APIRouter(prefix="/api/v1/auth", tags=["auth"])


class LoginRequest(BaseModel):
    username: str
    password: str


class TokenResponse(BaseModel):
    access_token: str
    token_type: str
    role: str


@router.post("/login", response_model=TokenResponse)
@limiter.limit(settings.rate_limit_auth)
async def login(request: Request, body: LoginRequest) -> TokenResponse:
    try:
        result = await auth_service.login(body.username, body.password)
    except (auth_service.InvalidCredentials, auth_service.AccountInactive):
        # Mesma resposta pra ambos — não revela se usuário existe.
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Credenciais inválidas",
        ) from None
    except auth_service.AccountLocked:
        raise HTTPException(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            detail="Conta bloqueada temporariamente. Tente novamente em 15 minutos.",
        ) from None
    return TokenResponse(**result)


@router.get("/me")
async def me(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    user = await user_repo.find_by_id(int(payload["sub"]))
    if user is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Usuário não encontrado")
    return {
        "id": user["id"],
        "username": user["username"],
        "role": user["role"],
        "email": user.get("email"),
        "is_active": user["is_active"],
    }


@router.post("/logout", status_code=status.HTTP_204_NO_CONTENT)
async def logout(_: Annotated[dict, Depends(require_auth)]) -> None:
    """
    JWT é stateless — cliente apenas descarta o token localmente.
    Pra invalidação real (revogação imediata pré-expiração), implementar
    denylist em Redis com TTL = exp do JWT. Hoje fora de escopo.
    """
    return None


class ChangePasswordRequest(BaseModel):
    old_password: str
    new_password: str


@router.put("/me/password", status_code=status.HTTP_204_NO_CONTENT)
async def change_password(
    body: ChangePasswordRequest,
    payload: Annotated[dict, Depends(require_auth)],
) -> None:
    """
    Altera a senha do usuário autenticado. Chamado pelo PHP Auth::updatePassword
    para sincronizar DuckDB com o novo hash do MariaDB durante a transição.
    """
    try:
        await auth_service.change_password(
            user_id=int(payload["sub"]),
            old_password=body.old_password,
            new_password=body.new_password,
        )
    except auth_service.InvalidCredentials:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Senha atual incorreta ou nova senha muito curta",
        ) from None


# ---------------------------------------------------------------------------
# Password reset (esqueceu a senha) — endpoints públicos
# ---------------------------------------------------------------------------


class PasswordResetRequest(BaseModel):
    email: str


class PasswordResetConfirm(BaseModel):
    token: str
    new_password: str


@router.post("/password-reset/request")
@limiter.limit("5/minute")
async def request_password_reset(request: Request, body: PasswordResetRequest) -> dict:
    """
    Gera token de reset se email pertence a user ativo. Retorna o token (cru)
    pra o caller (PHP) enviar por email — Python NÃO envia email diretamente.
    Resposta sempre 200 (timing-safe; não revela se email existe).
    """
    from app.services import users_service

    raw_token = await users_service.request_password_reset(body.email)
    return {"token": raw_token, "valid_for_minutes": 10 if raw_token else 0}


@router.post("/password-reset/confirm", status_code=status.HTTP_204_NO_CONTENT)
@limiter.limit("10/minute")
async def confirm_password_reset(request: Request, body: PasswordResetConfirm) -> None:
    from app.services import users_service

    try:
        await users_service.confirm_password_reset(body.token, body.new_password)
    except users_service.WeakPassword:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Senha deve ter no mínimo 6 caracteres.",
        ) from None
    except users_service.InvalidResetToken:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Link de recuperação inválido ou expirado.",
        ) from None
