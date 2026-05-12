"""Endpoints de autenticação — login, me, logout, refresh."""

from __future__ import annotations

from datetime import UTC, datetime, timedelta
from typing import Annotated

from fastapi import APIRouter, Depends, Header, HTTPException, Request, status
from jose import ExpiredSignatureError, JWTError, jwt
from pydantic import BaseModel

from app.core.config import settings
from app.core.deps import require_auth
from app.core.rate_limit import limiter
from app.core.security import create_access_token
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


@router.post("/refresh", response_model=TokenResponse)
@limiter.limit(settings.rate_limit_auth)
async def refresh(
    request: Request,
    authorization: Annotated[str | None, Header()] = None,
) -> TokenResponse:
    """
    Renova o JWT do user. Aceita o JWT atual (ainda válido OU expirado
    nos últimos `_REFRESH_GRACE_MINUTES`) e retorna um novo com TTL
    completo. Útil pra sliding session — frontend chama proativamente
    quando o JWT está prestes a expirar.

    Segurança: como aceita JWT expirado por até N min, atacante que
    rouba JWT consegue renovar dentro dessa janela. Mantemos grace
    curto (10min) pra minimizar. Revogação real precisa de denylist
    em Redis (fora de escopo aqui).

    Validações:
      - Conta ainda existe + ativa (não-bloqueada). Se admin desativar
        o user, o JWT velho não consegue renovar mais.
    """
    if not authorization or not authorization.lower().startswith("bearer "):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Authorization header ausente",
        )
    token = authorization.split(None, 1)[1].strip()

    # Decode SEM validar exp — queremos aceitar tokens recém-expirados.
    try:
        payload = jwt.decode(
            token,
            settings.jwt_secret.get_secret_value(),
            algorithms=[settings.jwt_algorithm],
            options={"verify_exp": False},
        )
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED, detail="Token inválido"
        ) from None

    exp_ts = int(payload.get("exp", 0))
    now_ts = int(datetime.now(UTC).timestamp())
    grace_seconds = _REFRESH_GRACE_MINUTES * 60
    if exp_ts <= 0 or (now_ts - exp_ts) > grace_seconds:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Token expirado há mais de {_REFRESH_GRACE_MINUTES}min — re-login necessário",
        )

    sub = payload.get("sub")
    if not sub:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Token malformado")

    # Re-valida conta no banco — pode ter sido desativada entre login e refresh.
    user = await user_repo.find_by_id(int(sub))
    if user is None or not user.get("is_active"):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Conta inativa ou removida",
        )

    new_token = create_access_token({"sub": str(user["id"]), "role": user["role"]})
    return TokenResponse(access_token=new_token, token_type="bearer", role=user["role"])


# Grace window pra refresh (em minutos). JWT expirado há ≤ N min ainda
# pode ser renovado; expirado há mais já exige re-login.
_REFRESH_GRACE_MINUTES = 10


@router.post("/revoke/{user_id}", status_code=status.HTTP_200_OK)
async def revoke_user(
    user_id: int,
    payload: Annotated[dict, Depends(require_auth)],
) -> dict:
    """
    Force-revoke todos os tokens emitidos pra `user_id` até este momento.

    Permissões:
      - Admin pode revogar qualquer user
      - User pode revogar a SI MESMO (auto-logout-everywhere)
    """
    requester_id = int(payload.get("sub", 0))
    is_admin = payload.get("role") == "admin"
    if not (is_admin or requester_id == user_id):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Apenas admin ou o próprio user pode revogar tokens",
        )
    from app.services import jwt_denylist
    ok = await jwt_denylist.revoke_user_tokens(user_id)
    return {"revoked": ok, "user_id": user_id}


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
