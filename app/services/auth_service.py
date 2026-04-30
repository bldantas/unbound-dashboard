from __future__ import annotations

from datetime import datetime, timedelta, timezone

from app.core.security import (
    create_access_token,
    hash_password,
    verify_password,
    verify_password_dummy,
)
from app.domain.user import (
    AccountInactive,
    AccountLocked,
    InvalidCredentials,
    Role,
    User,
)
from app.repositories.duckdb.user_repo import UserRepository

_MAX_FAILED = 5
_LOCKOUT_MINUTES = 15


class AuthService:
    def __init__(self, repo: UserRepository) -> None:
        self._repo = repo

    async def login(self, username: str, password: str) -> dict:
        """
        Autentica usuário e retorna access_token JWT.
        - Timing-safe: custo bcrypt executado mesmo quando usuário não existe.
        - Lockout: 5 falhas → bloqueio por 15 minutos.
        """
        user = await self._repo.find_by_username(username)

        if user is None:
            verify_password_dummy()  # timing-safe
            raise InvalidCredentials

        if not user.is_active:
            raise AccountInactive

        if user.is_locked():
            raise AccountLocked

        if not verify_password(password, user.password_hash):
            count = user.failed_logins + 1
            lock_until = (
                # Naive UTC — compatível com DuckDB TIMESTAMP (sem timezone)
                datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(minutes=_LOCKOUT_MINUTES)
                if count >= _MAX_FAILED
                else None
            )
            await self._repo.update_failed_logins(user.id, count, lock_until)
            raise InvalidCredentials

        await self._repo.reset_failed_logins(user.id)

        token = create_access_token({"sub": str(user.id), "role": user.role.value})
        return {
            "access_token": token,
            "token_type": "bearer",
            "role": user.role.value,
        }

    async def create_user(
        self,
        username: str,
        password: str,
        role: Role = Role.VIEWER,
        email: str | None = None,
    ) -> None:
        """Cria um novo usuário com senha hasheada."""
        pw_hash = hash_password(password)
        await self._repo.create(username, pw_hash, role, email)
