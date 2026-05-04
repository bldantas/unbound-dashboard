"""Repository de settings (key/value) — leitura de config persistida."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_fetchone


async def get(key: str, default: str | None = None) -> str | None:
    row = await db_fetchone(
        "SELECT setting_value FROM settings WHERE setting_key = ?",
        [key],
    )
    return str(row["setting_value"]) if row else default


async def get_int(key: str, default: int = 0) -> int:
    value = await get(key)
    if value is None:
        return default
    try:
        return int(value)
    except ValueError:
        return default


async def get_bool(key: str, default: bool = False) -> bool:
    value = await get(key)
    if value is None:
        return default
    return value.strip().lower() in {"yes", "true", "1", "on"}


async def list_all() -> list[dict]:
    """Lista todos os settings — usado pra config backup export."""
    from app.repositories.duckdb.connection import db_fetchall

    return await db_fetchall("SELECT setting_key, setting_value FROM settings ORDER BY setting_key")


async def bulk_upsert(entries: list[dict]) -> int:
    """
    UPSERT de N pares key/value. Usado por config backup restore.
    `entries` = [{"setting_key": str, "setting_value": str}, ...].
    Retorna count de entries processadas.
    """
    from app.repositories.duckdb.connection import db_execute

    count = 0
    for entry in entries:
        key = entry.get("setting_key", "")
        value = str(entry.get("setting_value", ""))
        if not key:
            continue
        await db_execute(
            """
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value
            """,
            [key, value],
        )
        count += 1
    return count
