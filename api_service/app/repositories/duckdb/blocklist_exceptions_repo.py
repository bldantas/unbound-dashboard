"""CRUD de blocklist_exceptions (allowlist global)."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone


async def list_all() -> list[dict]:
    return await db_fetchall(
        """
        SELECT domain, reason, created_by, created_at
        FROM blocklist_exceptions
        ORDER BY created_at DESC, domain
        """
    )


async def list_domains() -> list[str]:
    """Só os domínios — pra injetar como local-zone transparent no Unbound."""
    rows = await db_fetchall("SELECT domain FROM blocklist_exceptions ORDER BY domain")
    return [str(r["domain"]) for r in rows]


async def add(domain: str, *, reason: str | None = None, created_by: str | None = None) -> bool:
    """INSERT ON CONFLICT DO NOTHING. Retorna True se foi novo."""
    d = (domain or "").strip().lower()
    if not d:
        return False
    existing = await db_fetchone(
        "SELECT 1 FROM blocklist_exceptions WHERE domain = ?",
        [d],
    )
    if existing:
        return False
    await db_execute(
        "INSERT INTO blocklist_exceptions (domain, reason, created_by) VALUES (?, ?, ?)",
        [d, reason, created_by],
    )
    return True


async def remove(domain: str) -> bool:
    d = (domain or "").strip().lower()
    if not d:
        return False
    existing = await db_fetchone(
        "SELECT 1 FROM blocklist_exceptions WHERE domain = ?",
        [d],
    )
    if not existing:
        return False
    await db_execute("DELETE FROM blocklist_exceptions WHERE domain = ?", [d])
    return True


async def count() -> int:
    row = await db_fetchone("SELECT COUNT(*) AS n FROM blocklist_exceptions")
    return int(row["n"]) if row else 0
