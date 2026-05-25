"""Queries de ameaças DNS — alimentam /api/v1/threats/data e /api/v1/blocklist/*.

Desde V9 (multi-source), `blocklist_domains` foi substituída por
`blocklist_entries (domain, source_slug)` + `blocklist_sources` (catálogo).
`category` e `severity` agora vêm de JOIN com sources. Contagens "por domínio"
usam DISTINCT — um domínio em N fontes conta 1 vez nas métricas de domain;
contagens "por linha" (de procedência) somam tudo.
"""

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
    """Contagem de domínios únicos (DISTINCT, ignora duplicações entre sources)."""
    row = await db_fetchone("SELECT COUNT(DISTINCT domain) AS n FROM blocklist_entries")
    return int(row["n"]) if row else 0


async def recent_blocked(limit: int) -> list[dict]:
    """Últimos N eventos blocked, com category/severity via JOIN com sources.

    Se o domínio está em mais de uma source, escolhe a de maior severidade
    (high > medium > low) — agg via MAX num CASE, evita explosão do JOIN.
    """
    return await db_fetchall(
        """
        WITH recent AS (
            SELECT timestamp, client_ip, domain, action
            FROM query_logs
            WHERE action = 'blocked'
            ORDER BY timestamp DESC
            LIMIT ?
        ),
        cat AS (
            SELECT e.domain,
                   ANY_VALUE(s.category) AS category,
                   MAX(CASE s.severity WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END) AS sev_rank
            FROM blocklist_entries e
            JOIN blocklist_sources s ON e.source_slug = s.slug
            WHERE e.domain IN (SELECT domain FROM recent)
            GROUP BY e.domain
        )
        SELECT r.timestamp, r.client_ip, r.domain, r.action,
               c.category,
               CASE c.sev_rank WHEN 3 THEN 'high' WHEN 2 THEN 'medium' WHEN 1 THEN 'low' ELSE NULL END AS severity
        FROM recent r
        LEFT JOIN cat c ON r.domain = c.domain
        ORDER BY r.timestamp DESC
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
    """COUNT(DISTINCT domain) das entries cuja source tem essa categoria."""
    row = await db_fetchone(
        """
        SELECT COUNT(DISTINCT e.domain) AS n
        FROM blocklist_entries e
        JOIN blocklist_sources s ON e.source_slug = s.slug
        WHERE s.category = ?
        """,
        [category],
    )
    return int(row["n"]) if row else 0


async def counts_by_category() -> dict[str, int]:
    """Retorna {category: COUNT(DISTINCT domain)} para todas as categorias presentes."""
    rows = await db_fetchall(
        """
        SELECT s.category, COUNT(DISTINCT e.domain) AS n
        FROM blocklist_entries e
        JOIN blocklist_sources s ON e.source_slug = s.slug
        GROUP BY s.category
        """
    )
    return {str(r["category"] or ""): int(r["n"]) for r in rows}


async def clear_source(source_slug: str) -> int:
    """DELETE WHERE source_slug = ?. Usado pelo syncer ao re-baixar uma fonte."""
    from app.repositories.duckdb.connection import db_execute

    row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM blocklist_entries WHERE source_slug = ?",
        [source_slug],
    )
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute("DELETE FROM blocklist_entries WHERE source_slug = ?", [source_slug])
    return n


async def clear_category(category: str) -> int:
    """Compat-shim: limpa todas as entries das sources que pertencem a `category`.

    Mantido pra não quebrar `POST /api/v1/blocklist/clear-category`. Novos
    chamadores devem usar `clear_source` que é mais preciso.
    """
    from app.repositories.duckdb.connection import db_execute

    row = await db_fetchone(
        """
        SELECT COUNT(*) AS n
        FROM blocklist_entries e
        JOIN blocklist_sources s ON e.source_slug = s.slug
        WHERE s.category = ?
        """,
        [category],
    )
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute(
            """
            DELETE FROM blocklist_entries
            WHERE source_slug IN (SELECT slug FROM blocklist_sources WHERE category = ?)
            """,
            [category],
        )
    return n


async def bulk_insert_for_source(source_slug: str, domains: list[str], *, batch_size: int = 5000) -> int:
    """Bulk INSERT de domínios em UMA source. Idempotente (ON CONFLICT DO NOTHING).

    Bate VALUES multi-row em lotes grandes — fonte com 400k domínios fica em
    ~poucos segundos em vez de minutos. Usa uma única conexão writer por
    batch (vai pro _writer_executor singleton serializado).
    """
    from app.repositories.duckdb.connection import db_execute

    cleaned: list[str] = []
    for raw in domains:
        d = (raw or "").strip().lower()
        if d:
            cleaned.append(d)
    if not cleaned:
        return 0

    inserted = 0
    for i in range(0, len(cleaned), batch_size):
        chunk = cleaned[i : i + batch_size]
        placeholders = ",".join(["(?, ?)"] * len(chunk))
        args: list = []
        for d in chunk:
            args.extend([d, source_slug])
        await db_execute(
            f"""
            INSERT INTO blocklist_entries (domain, source_slug) VALUES {placeholders}
            ON CONFLICT (domain, source_slug) DO NOTHING
            """,
            args,
        )
        inserted += len(chunk)
    return inserted


async def bulk_insert(entries: list[dict]) -> int:
    """Compat-shim: aceita o formato antigo [{domain, category, severity}].

    Mapeia category → source_slug (primeira source que casa). Mantido pra não
    quebrar `POST /api/v1/blocklist/bulk-insert`. Novos chamadores devem usar
    `bulk_insert_for_source`.
    """
    from app.repositories.duckdb.connection import db_execute

    cat_to_slug: dict[str, str | None] = {}

    async def resolve_slug(cat: str) -> str | None:
        if cat in cat_to_slug:
            return cat_to_slug[cat]
        row = await db_fetchone(
            "SELECT slug FROM blocklist_sources WHERE category = ? ORDER BY sort_order LIMIT 1",
            [cat],
        )
        slug = row["slug"] if row else None
        cat_to_slug[cat] = slug
        return slug

    count = 0
    for e in entries:
        domain = (e.get("domain") or "").strip().lower()
        category = e.get("category") or ""
        if not domain or not category:
            continue
        slug = await resolve_slug(category)
        if not slug:
            continue
        await db_execute(
            """
            INSERT INTO blocklist_entries (domain, source_slug) VALUES (?, ?)
            ON CONFLICT (domain, source_slug) DO NOTHING
            """,
            [domain, slug],
        )
        count += 1
    return count


async def top_blocked_domains_with_blacklist(limit: int = 10) -> list[dict]:
    """Top domínios bloqueados que existem em alguma blocklist (INNER JOIN)."""
    return await db_fetchall(
        """
        SELECT q.domain, COUNT(*) AS hits
        FROM query_logs q
        WHERE q.action = 'blocked'
          AND q.domain IN (SELECT DISTINCT domain FROM blocklist_entries)
        GROUP BY q.domain
        ORDER BY hits DESC
        LIMIT ?
        """,
        [limit],
    )


# ============================================================
# Busca paginada — alimenta a tabela de /blocklist.php e /catalog.php.
# Filtros: q (LIKE), category (via JOIN sources), tld (sufixo).
# Pós-V9: domain pode aparecer em múltiplas sources, então usamos
# DISTINCT ON domain pra não duplicar resultados na UI.
# ============================================================


def _build_distinct_query(
    *,
    q: str,
    category: str | None,
    tld: str | None,
    select: str,
    suffix: str = "",
    args_tail: list | None = None,
) -> tuple[str, list]:
    """Constrói query com DISTINCT no domain + JOIN sources (filtro por category)."""
    conds: list[str] = []
    args: list = []
    if q:
        conds.append("e.domain LIKE ?")
        args.append(f"%{q.lower()}%")
    if category:
        conds.append("s.category = ?")
        args.append(category)
    if tld:
        conds.append("e.domain LIKE ?")
        args.append(f"%.{tld.lower()}")
    where = ("WHERE " + " AND ".join(conds)) if conds else ""
    if args_tail:
        args = args + list(args_tail)
    sql = f"""
        {select}
        FROM blocklist_entries e
        JOIN blocklist_sources s ON e.source_slug = s.slug
        {where}
        {suffix}
    """
    return sql, args


async def search_blocklist(
    *,
    q: str = "",
    category: str | None = None,
    tld: str | None = None,
    offset: int = 0,
    limit: int = 50,
) -> list[dict]:
    """Busca paginada. Retorna 1 linha por domínio (DISTINCT), com category/severity
    "máximas" entre todas as sources que contêm o domínio.
    """
    sql, args = _build_distinct_query(
        q=q,
        category=category,
        tld=tld,
        select="""
        SELECT e.domain,
               ANY_VALUE(s.category) AS category,
               CASE MAX(CASE s.severity WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END)
                    WHEN 3 THEN 'high' WHEN 2 THEN 'medium' WHEN 1 THEN 'low' ELSE NULL END AS severity
        """,
        suffix="GROUP BY e.domain ORDER BY e.domain ASC LIMIT ? OFFSET ?",
        args_tail=[limit, offset],
    )
    return await db_fetchall(sql, args)


async def count_blocklist(
    *,
    q: str = "",
    category: str | None = None,
    tld: str | None = None,
) -> int:
    """Conta domínios únicos que casam os filtros — pra paginação."""
    sql, args = _build_distinct_query(
        q=q,
        category=category,
        tld=tld,
        select="SELECT COUNT(DISTINCT e.domain) AS n",
    )
    row = await db_fetchone(sql, args)
    return int(row["n"]) if row else 0


async def top_tlds(category: str | None = None, limit: int = 20) -> dict[str, int]:
    """TLD = última parte do domain. Conta domínios únicos por TLD."""
    sql, args = _build_distinct_query(
        q="",
        category=category,
        tld=None,
        select="""
        SELECT split_part(e.domain, '.', -1) AS tld,
               COUNT(DISTINCT e.domain) AS n
        """,
        suffix="GROUP BY tld HAVING tld <> '' ORDER BY n DESC LIMIT ?",
        args_tail=[limit],
    )
    rows = await db_fetchall(sql, args)
    return {str(r["tld"]): int(r["n"]) for r in rows}
