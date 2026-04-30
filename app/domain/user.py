from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone
from enum import StrEnum
from typing import Optional


class Role(StrEnum):
    ADMIN = "admin"
    VIEWER = "viewer"


class DomainError(Exception):
    """Exceção base de domínio — não vazar detalhes internos para o cliente."""


class InvalidCredentials(DomainError):
    pass


class AccountLocked(DomainError):
    pass


class AccountInactive(DomainError):
    pass


class UserNotFound(DomainError):
    pass


@dataclass
class User:
    id: int
    username: str
    password_hash: str
    role: Role
    is_active: bool
    failed_logins: int
    locked_until: Optional[datetime] = None
    email: Optional[str] = None
    created_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))
    updated_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))

    def is_locked(self) -> bool:
        if self.locked_until is None:
            return False
        # DuckDB TIMESTAMP é naive UTC; normaliza ambos para naive antes de comparar
        lu = self.locked_until.replace(tzinfo=None) if self.locked_until.tzinfo else self.locked_until
        now = datetime.now(timezone.utc).replace(tzinfo=None)
        return lu > now
