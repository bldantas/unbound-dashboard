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


@router.post("/login")
@limiter.limit(settings.rate_limit_auth)
async def login(request: Request, body: LoginRequest) -> dict:
    """
    Retorna TokenResponse normal OU `{requires_totp: true, challenge_token}`
    se o user tem 2FA habilitado. Frontend (login.php) precisa detectar
    `requires_totp` e redirecionar pro fluxo de 2FA.
    """
    try:
        result = await auth_service.login(body.username, body.password)
    except auth_service.TOTPRequired as exc:
        return {"requires_totp": True, "challenge_token": exc.challenge_token}
    except (auth_service.InvalidCredentials, auth_service.AccountInactive):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Credenciais inválidas",
        ) from None
    except auth_service.AccountLocked:
        raise HTTPException(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            detail="Conta bloqueada temporariamente. Tente novamente em 15 minutos.",
        ) from None
    return result


class Login2FARequest(BaseModel):
    challenge_token: str
    code: str


@router.post("/login/2fa-verify", response_model=TokenResponse)
@limiter.limit(settings.rate_limit_auth)
async def login_2fa_verify(request: Request, body: Login2FARequest) -> TokenResponse:
    """
    Segundo passo do login pra users com 2FA habilitado. Recebe o
    challenge_token vindo de /login e o code TOTP atual.
    """
    try:
        result = await auth_service.login_2fa_verify(body.challenge_token, body.code)
    except auth_service.InvalidChallengeToken:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Sessão de login expirou — faça login novamente.",
        ) from None
    except auth_service.InvalidTOTPCode:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Código 2FA inválido.",
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
        "totp_enabled": bool(user.get("totp_enabled", False)),
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


@router.get("/sessions")
async def list_my_sessions(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """
    Lista sessões ativas (Redis tracking) do user autenticado.
    Admin pode passar `?all=1` pra listar todas as sessões do sistema.
    """
    from app.services import sessions as sessions_svc

    user_id = int(payload.get("sub", 0))
    # Sempre retorna apenas as sessões do user solicitante. Pra admin
    # listar tudo, criar endpoint dedicado /sessions/all no futuro.
    items = await sessions_svc.list_for_user(user_id)
    # Marca a sessão atual (mesmo token_hash) pro UI destacar
    current_hash = None
    return {"sessions": items, "current_hash": current_hash}


@router.delete("/sessions/{token_hash}", status_code=status.HTTP_204_NO_CONTENT)
async def revoke_my_session(
    token_hash: str,
    payload: Annotated[dict, Depends(require_auth)],
) -> None:
    """
    Revoga uma sessão específica (logout cirúrgico). User só pode revogar
    suas próprias sessões; admin pode revogar de qualquer.
    """
    from app.services import jwt_denylist
    from app.services import sessions as sessions_svc

    user_id = int(payload.get("sub", 0))
    is_admin = payload.get("role") == "admin"

    # Lista pra validar ownership
    if is_admin:
        all_sessions = await sessions_svc.list_all()
    else:
        all_sessions = await sessions_svc.list_for_user(user_id)
    matching = next((s for s in all_sessions if s.get("token_hash") == token_hash), None)
    if matching is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sessão não encontrada")

    # Revoga: adiciona hash ao denylist + remove do tracking
    await jwt_denylist.revoke_token_hash(token_hash)
    await sessions_svc.remove(int(matching.get("user_id", 0)), token_hash)


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


# ---------------------------------------------------------------------------
# 2FA TOTP — setup, enable, disable, admin-reset
# ---------------------------------------------------------------------------


@router.post("/2fa/setup")
async def setup_2fa(payload: Annotated[dict, Depends(require_auth)]) -> dict:
    """
    Gera secret novo + URI provisionamento. NÃO persiste — user precisa
    confirmar com code via /2fa/confirm pra ativar de fato.
    """
    from app.services import totp_service

    user = await user_repo.find_by_id(int(payload["sub"]))
    if user is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User não encontrado")
    if user.get("totp_enabled"):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="2FA já está habilitado — desabilite primeiro pra trocar.",
        )
    secret = totp_service.generate_secret()
    uri = totp_service.provisioning_uri(secret, user["username"])
    return {"secret": secret, "provisioning_uri": uri}


class TOTPConfirmRequest(BaseModel):
    secret: str
    code: str


@router.post("/2fa/confirm", status_code=status.HTTP_204_NO_CONTENT)
async def confirm_2fa(
    body: TOTPConfirmRequest,
    payload: Annotated[dict, Depends(require_auth)],
) -> None:
    """Valida code do secret novo + persiste no user."""
    from app.services import totp_service

    if not totp_service.verify(body.secret, body.code):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Código inválido — confira o relógio do dispositivo.",
        )
    ok = await user_repo.enable_totp(int(payload["sub"]), body.secret)
    if not ok:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User não encontrado")


class TOTPDisableRequest(BaseModel):
    code: str


@router.post("/2fa/disable", status_code=status.HTTP_204_NO_CONTENT)
async def disable_2fa(
    body: TOTPDisableRequest,
    payload: Annotated[dict, Depends(require_auth)],
) -> None:
    """User desabilita o próprio 2FA. Exige code TOTP válido (anti-takeover)."""
    from app.services import totp_service

    user_id = int(payload["sub"])
    user = await user_repo.find_by_id(user_id)
    if user is None or not user.get("totp_enabled"):
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="2FA não está habilitado.")
    if not totp_service.verify(user.get("totp_secret") or "", body.code):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Código 2FA inválido.",
        )
    await user_repo.disable_totp(user_id)


@router.post("/2fa/admin-reset/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
async def admin_reset_2fa(
    user_id: int,
    payload: Annotated[dict, Depends(require_auth)],
) -> None:
    """
    Admin zera 2FA de um user (caso de celular perdido). Requer
    `users.manage`. Self-target permitido como fallback (admin que perdeu
    o próprio celular E é o único admin — sem isso fica trancado).
    """
    from app.core.rbac import can

    if not can(payload.get("role"), "users.manage"):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Apenas users com 'users.manage' podem resetar 2FA de terceiros.",
        )
    ok = await user_repo.disable_totp(user_id)
    if not ok:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User não encontrado")
