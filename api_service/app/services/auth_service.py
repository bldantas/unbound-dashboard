"""
AuthService — login, lockout, emissão de JWT.

Lições aplicadas (audit memory):
  - Timing-safe: bcrypt dummy verify quando user não existe (impede enumeração).
  - Lockout: 5 falhas consecutivas → bloqueia por 15 minutos. Reseta a contagem
    em login bem-sucedido.
  - JWT contém apenas `sub` (id) e `role` no payload — NÃO inclui dados
    sensíveis (email, hash). Cliente pega resto via /me.
"""

from __future__ import annotations

from datetime import UTC, datetime, timedelta

from app.core.security import (
    create_access_token,
    hash_password,
    verify_password,
    verify_password_dummy,
)
from app.repositories.duckdb import user_repo

_MAX_FAILED = 5
_LOCKOUT_MINUTES = 15


class AuthError(Exception):
    """Base de erros de auth — capturada no router."""


class InvalidCredentials(AuthError):
    pass


class AccountInactive(AuthError):
    pass


class AccountLocked(AuthError):
    pass


def _now_utc_naive() -> datetime:
    """DuckDB 1.5 TIMESTAMP é naive (sem tzinfo). Usar UTC naive pra comparação."""
    return datetime.now(UTC).replace(tzinfo=None)


async def login(username: str, password: str) -> dict:
    """
    Autentica e retorna {access_token, token_type, role}.
    Lança InvalidCredentials / AccountInactive / AccountLocked.
    """
    user = await user_repo.find_by_username(username)

    if user is None:
        verify_password_dummy()  # timing-safe
        raise InvalidCredentials

    if not user["is_active"]:
        raise AccountInactive

    locked_until = user.get("locked_until")
    if locked_until and locked_until > _now_utc_naive():
        raise AccountLocked

    if not verify_password(password, user["password_hash"]):
        count = int(user["failed_logins"] or 0) + 1
        new_lock = (
            _now_utc_naive() + timedelta(minutes=_LOCKOUT_MINUTES) if count >= _MAX_FAILED else None
        )
        await user_repo.update_failed_logins(user["id"], count, new_lock)
        raise InvalidCredentials

    await user_repo.reset_failed_logins(user["id"])

    token = create_access_token({"sub": str(user["id"]), "role": user["role"]})
    return {
        "access_token": token,
        "token_type": "bearer",
        "role": user["role"],
    }


async def change_password(user_id: int, old_password: str, new_password: str) -> None:
    """
    Verifica senha atual, valida nova (>=6 chars), grava hash novo.
    Chamado tanto pelo endpoint quanto pelo bridge PHP em Auth::updatePassword.
    """
    if len(new_password) < 6:
        raise InvalidCredentials  # reuso — frontend traduz mensagem
    user = await user_repo.find_by_username_with_hash(user_id)
    if user is None or not verify_password(old_password, user["password_hash"]):
        raise InvalidCredentials
    await user_repo.update_password_hash(user_id, hash_password(new_password))
