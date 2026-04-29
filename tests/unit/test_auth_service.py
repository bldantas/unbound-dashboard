"""Testes unitários do AuthService."""
from __future__ import annotations

from datetime import datetime, timezone
from unittest.mock import AsyncMock, patch

import pytest

from app.domain.user import AccountLocked, InvalidCredentials, Role, User
from app.services.auth_service import AuthService


import bcrypt as _bcrypt


def _hash(plain: str) -> str:
    return _bcrypt.hashpw(plain.encode(), _bcrypt.gensalt()).decode()


def _make_user(**kwargs) -> User:
    defaults = dict(
        id=1,
        username="admin",
        password_hash="$2b$12$placeholder",
        role=Role.ADMIN,
        is_active=True,
        failed_logins=0,
        locked_until=None,
    )
    return User(**{**defaults, **kwargs})


@pytest.fixture
def repo():
    return AsyncMock()


@pytest.fixture
def svc(repo) -> AuthService:
    return AuthService(repo)


@pytest.mark.asyncio
async def test_login_user_not_found_raises_invalid_credentials(svc, repo):
    repo.find_by_username.return_value = None
    with pytest.raises(InvalidCredentials):
        await svc.login("nobody", "password")


@pytest.mark.asyncio
async def test_login_wrong_password_increments_failed_count(svc, repo):
    user = _make_user(password_hash=_hash("correct"))
    repo.find_by_username.return_value = user

    with pytest.raises(InvalidCredentials):
        await svc.login("admin", "wrong")

    repo.update_failed_logins.assert_called_once_with(1, 1, None)


@pytest.mark.asyncio
async def test_login_5_failures_locks_account(svc, repo):
    user = _make_user(password_hash=_hash("correct"), failed_logins=4)
    repo.find_by_username.return_value = user

    with pytest.raises(InvalidCredentials):
        await svc.login("admin", "wrong")

    _, _, lock_until = repo.update_failed_logins.call_args.args
    assert lock_until is not None, "conta deve ser bloqueada após 5 falhas"


@pytest.mark.asyncio
async def test_login_locked_account_raises_account_locked(svc, repo):
    from datetime import timedelta
    user = _make_user(
        locked_until=datetime.now(timezone.utc) + timedelta(minutes=10)
    )
    repo.find_by_username.return_value = user

    with pytest.raises(AccountLocked):
        await svc.login("admin", "any")


@pytest.mark.asyncio
async def test_login_success_resets_failed_logins(svc, repo):
    user = _make_user(password_hash=_hash("secret"), failed_logins=2)
    repo.find_by_username.return_value = user

    result = await svc.login("admin", "secret")

    repo.reset_failed_logins.assert_called_once_with(1)
    assert result["token_type"] == "bearer"
    assert result["role"] == "admin"
    assert "access_token" in result
