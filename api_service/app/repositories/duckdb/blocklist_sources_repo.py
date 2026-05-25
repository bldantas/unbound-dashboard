"""CRUD de blocklist_sources (catálogo de fontes) + agregações."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

_VALID_SEVERITY = {"low", "medium", "high"}


async def list_all() -> list[dict]:
    """Lista todas as sources ordenadas por sort_order, com last_count fresh."""
    return await db_fetchall(
        """
        SELECT slug, name, description, url, format, category, severity,
               index_enabled, block_enabled, is_builtin, sort_order,
               last_sync, last_count, last_error
        FROM blocklist_sources
        ORDER BY sort_order, name
        """
    )


async def get(slug: str) -> dict | None:
    return await db_fetchone(
        "SELECT * FROM blocklist_sources WHERE slug = ?",
        [slug],
    )


async def set_flags(slug: str, *, index_enabled: bool | None = None, block_enabled: bool | None = None) -> bool:
    """Atualiza index_enabled e/ou block_enabled. Retorna True se algo mudou."""
    fields: list[str] = []
    args: list = []
    if index_enabled is not None:
        fields.append("index_enabled = ?")
        args.append(bool(index_enabled))
    if block_enabled is not None:
        fields.append("block_enabled = ?")
        args.append(bool(block_enabled))
    if not fields:
        return False
    args.append(slug)
    await db_execute(
        f"UPDATE blocklist_sources SET {', '.join(fields)} WHERE slug = ?",
        args,
    )
    return True


async def mark_synced(slug: str, count: int, error: str | None = None) -> None:
    """Carimba last_sync=now, last_count, last_error."""
    await db_execute(
        """
        UPDATE blocklist_sources
           SET last_sync = NOW(),
               last_count = ?,
               last_error = ?
         WHERE slug = ?
        """,
        [int(count), error, slug],
    )


async def domains_to_block() -> list[str]:
    """Domínios distintos a bloquear no Unbound: união das sources com
    block_enabled=true MENOS as exceptions. Ordem ASC pra consistência do
    .conf gerado (diff-friendly).
    """
    rows = await db_fetchall(
        """
        SELECT DISTINCT e.domain
        FROM blocklist_entries e
        JOIN blocklist_sources s ON e.source_slug = s.slug
        WHERE s.block_enabled = true
          AND e.domain NOT IN (SELECT domain FROM blocklist_exceptions)
        ORDER BY e.domain
        """
    )
    return [str(r["domain"]) for r in rows]


async def stats_per_source() -> list[dict]:
    """Conta entries reais por source (não confia no last_count)."""
    return await db_fetchall(
        """
        SELECT s.slug, s.name, s.category, s.index_enabled, s.block_enabled,
               s.last_sync, s.last_error,
               COALESCE((SELECT COUNT(*) FROM blocklist_entries WHERE source_slug = s.slug), 0) AS count
        FROM blocklist_sources s
        ORDER BY s.sort_order, s.name
        """
    )
