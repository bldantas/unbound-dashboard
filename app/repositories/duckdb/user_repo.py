from __future__ import annotations

from datetime import datetime, timezone
from typing import Optional

from app.domain.user import Role, User
from app.repositories.duckdb.connection import db_execute, db_fetchone


class UserRepository:
    async def find_by_username(self, username: str) -> Optional[User]:
        row = await db_fetchone(
            "SELECT id, username, password_hash, role, email, is_active, "
            "failed_logins, locked_until, created_at, updated_at "
            "FROM users WHERE username = ?",
            [username],
        )
        return _row_to_user(row) if row else None

    async def find_by_id(self, user_id: int) -> Optional[User]:
        row = await db_fetchone(
            "SELECT id, username, password_hash, role, email, is_active, "
            "failed_logins, locked_until, created_at, updated_at "
            "FROM users WHERE id = ?",
            [user_id],
        )
        return _row_to_user(row) if row else None

    async def update_failed_logins(
        self, user_id: int, count: int, locked_until: Optional[datetime]
    ) -> None:
        await db_execute(
            "UPDATE users SET failed_logins = ?, locked_until = ?, updated_at = NOW() WHERE id = ?",
            [count, locked_until, user_id],
        )

    async def reset_failed_logins(self, user_id: int) -> None:
        await db_execute(
            "UPDATE users SET failed_logins = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?",
            [user_id],
        )

    async def create(
        self,
        username: str,
        password_hash: str,
        role: Role = Role.VIEWER,
        email: Optional[str] = None,
    ) -> None:
        await db_execute(
            "INSERT INTO users (username, password_hash, role, email) VALUES (?, ?, ?, ?)",
            [username, password_hash, role.value, email],
        )


def _row_to_user(row: dict) -> User:
    locked = row["locked_until"]
    if isinstance(locked, datetime) and locked.tzinfo is None:
        locked = locked.replace(tzinfo=timezone.utc)

    return User(
        id=row["id"],
        username=row["username"],
        password_hash=row["password_hash"],
        role=Role(row["role"]),
        email=row.get("email"),
        is_active=bool(row["is_active"]),
        failed_logins=int(row["failed_logins"]),
        locked_until=locked,
        created_at=row["created_at"],
        updated_at=row["updated_at"],
    )
