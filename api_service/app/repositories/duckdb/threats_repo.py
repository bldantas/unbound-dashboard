"""Queries de ameaças DNS — alimentam /api/v1/threats/data (espelho do api/threats_data.php v1)."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_fetchall, db_fetchone


async def daily_totals() -> dict[str, int]:
    """Soma histórica de queries e bloqueios — pré-agregada em daily_stats."""
    row = await db_fetchone(
        """
        SELECT
            COALESCE(SUM(blocked_count), 0)  AS blocked,
            COALESCE(SUM(total_queries), 0)  AS total
        FROM daily_stats
        """
    )
    if not row:
        return {"blocked": 0, "total": 0}
    return {"blocked": int(row["blocked"] or 0), "total": int(row["total"] or 0)}


async def blocklist_count() -> int:
    row = await db_fetchone("SELECT COUNT(*) AS n FROM blocklist_domains")
    return int(row["n"]) if row else 0


async def recent_blocked(limit: int) -> list[dict]:
    """Últimos N eventos blocked, com category/severity do blocklist_domains via LEFT JOIN."""
    return await db_fetchall(
        """
        SELECT q.timestamp, q.client_ip, q.domain, q.action,
               b.category, b.severity
        FROM (
            SELECT timestamp, client_ip, domain, action
            FROM query_logs
            WHERE action = 'blocked'
            ORDER BY timestamp DESC
            LIMIT ?
        ) q
        LEFT JOIN blocklist_domains b ON q.domain = b.domain
        """,
        [limit],
    )


async def top_blocked_clients(limit: int = 10) -> list[dict]:
    return await db_fetchall(
        """
        SELECT client_ip, COUNT(*) AS hits
        FROM query_logs
        WHERE action = 'blocked'
        GROUP BY client_ip
        ORDER BY hits DESC
        LIMIT ?
        """,
        [limit],
    )


async def db_count_category(category: str) -> int:
    """COUNT(*) de blocklist_domains pra uma categoria específica."""
    row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM blocklist_domains WHERE category = ?",
        [category],
    )
    return int(row["n"]) if row else 0


async def counts_by_category() -> dict[str, int]:
    """Retorna {category: count} pra todas categorias presentes."""
    rows = await db_fetchall(
        "SELECT category, COUNT(*) AS n FROM blocklist_domains GROUP BY category"
    )
    return {str(r["category"] or ""): int(r["n"]) for r in rows}


async def clear_category(category: str) -> int:
    """DELETE WHERE category = ?. Retorna quantos foram apagados."""
    from app.repositories.duckdb.connection import db_execute

    row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM blocklist_domains WHERE category = ?",
        [category],
    )
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute("DELETE FROM blocklist_domains WHERE category = ?", [category])
    return n


async def bulk_insert(entries: list[dict]) -> int:
    """
    Bulk UPSERT (INSERT ... ON CONFLICT DO UPDATE).
    `entries` = [{"domain": str, "category": str, "severity": str|None}, ...].
    Retorna count de entries processadas (skip se domain/category vazios).
    """
    from app.repositories.duckdb.connection import db_execute

    count = 0
    for e in entries:
        domain = (e.get("domain") or "").strip().lower()
        category = e.get("category") or ""
        severity = e.get("severity")
        if not domain or not category:
            continue
        await db_execute(
            """
            INSERT INTO blocklist_domains (domain, category, severity) VALUES (?, ?, ?)
            ON CONFLICT (domain) DO UPDATE SET
              category = EXCLUDED.category,
              severity = COALESCE(EXCLUDED.severity, blocklist_domains.severity)
            """,
            [domain, category, severity],
        )
        count += 1
    return count


async def top_blocked_domains_with_blacklist(limit: int = 10) -> list[dict]:
    """Top domínios bloqueados QUE ESTÃO na blocklist (INNER JOIN — exclui sem categoria)."""
    return await db_fetchall(
        """
        SELECT q.domain, COUNT(*) AS hits
        FROM query_logs q
        JOIN blocklist_domains b ON q.domain = b.domain
        WHERE q.action = 'blocked'
        GROUP BY q.domain
        ORDER BY hits DESC
        LIMIT ?
        """,
        [limit],
    )
