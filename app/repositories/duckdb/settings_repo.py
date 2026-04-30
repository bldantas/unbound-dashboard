from __future__ import annotations

from typing import Optional

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone


class SettingsRepository:
    """Par chave-valor persistido na tabela `settings`."""

    async def get(self, key: str) -> Optional[str]:
        row = await db_fetchone(
            "SELECT value FROM settings WHERE key = ?", [key]
        )
        return row["value"] if row else None

    async def set(self, key: str, value: str) -> None:
        await db_execute(
            """
            INSERT INTO settings (key, value) VALUES (?, ?)
            ON CONFLICT (key) DO UPDATE SET value = excluded.value
            """,
            [key, value],
        )

    async def delete(self, key: str) -> None:
        await db_execute("DELETE FROM settings WHERE key = ?", [key])

    async def all(self) -> dict[str, str]:
        rows = await db_fetchall("SELECT key, value FROM settings ORDER BY key")
        return {r["key"]: r["value"] for r in rows}

    async def bulk_set(self, data: dict[str, str]) -> None:
        for key, value in data.items():
            await self.set(key, value)
