"""CRUD de blocklist_exceptions (allowlist multi-tenant).

Após V29, a tabela tem PK composto `(domain, org_id)` onde `org_id = 0`
representa allowlist global (visível a todas as orgs, gerenciável só por
admin global). `org_id = N` representa exceção da org N (visível só pra
admin global + members da org N).

Diferente de outras tabelas tenant que usam `org_id IS NULL` pra global,
aqui usamos sentinela `0` por causa do PK composto (DuckDB não suporta
NULL na PK). Os filtros do repo encapsulam essa diferença.

Viewer semantics:
- `viewer_org_id = None` (system admin global) → vê tudo (org=0 + qualquer N)
- `viewer_org_id = N` (org-scoped) → vê globais (org=0) + da própria org (N)
"""

from __future__ import annotations

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

GLOBAL_ORG = 0


def _coerce_org(org_id: int | None) -> int:
    """Normaliza None → GLOBAL_ORG (0). Pra writes."""
    if org_id is None:
        return GLOBAL_ORG
    return int(org_id)


async def list_all(viewer_org_id: int | None = None) -> list[dict]:
    """Lista com filtro tenant. system admin vê tudo; user org-scoped vê globais + da própria."""
    if viewer_org_id is None:
        rows = await db_fetchall(
            """
            SELECT domain, org_id, reason, created_by, created_at
            FROM blocklist_exceptions
            ORDER BY org_id, created_at DESC, domain
            """
        )
    else:
        rows = await db_fetchall(
            """
            SELECT domain, org_id, reason, created_by, created_at
            FROM blocklist_exceptions
            WHERE org_id = ? OR org_id = ?
            ORDER BY org_id, created_at DESC, domain
            """,
            [GLOBAL_ORG, int(viewer_org_id)],
        )
    return rows


async def list_domains_global() -> list[str]:
    """Só os domínios globais — pra injetar como local-zone transparent no
    Unbound. Exceções org-scoped ficam fora até split-horizon de blocklist
    ser implementado."""
    rows = await db_fetchall(
        "SELECT domain FROM blocklist_exceptions WHERE org_id = ? ORDER BY domain",
        [GLOBAL_ORG],
    )
    return [str(r["domain"]) for r in rows]


async def add(
    domain: str,
    *,
    org_id: int | None = None,
    reason: str | None = None,
    created_by: str | None = None,
) -> bool:
    """INSERT idempotente. Retorna True se foi novo."""
    d = (domain or "").strip().lower()
    if not d:
        return False
    oid = _coerce_org(org_id)
    existing = await db_fetchone(
        "SELECT 1 FROM blocklist_exceptions WHERE domain = ? AND org_id = ?",
        [d, oid],
    )
    if existing:
        return False
    await db_execute(
        "INSERT INTO blocklist_exceptions (domain, org_id, reason, created_by) VALUES (?, ?, ?, ?)",
        [d, oid, reason, created_by],
    )
    return True


async def remove(domain: str, *, org_id: int | None = None) -> bool:
    d = (domain or "").strip().lower()
    if not d:
        return False
    oid = _coerce_org(org_id)
    existing = await db_fetchone(
        "SELECT 1 FROM blocklist_exceptions WHERE domain = ? AND org_id = ?",
        [d, oid],
    )
    if not existing:
        return False
    await db_execute(
        "DELETE FROM blocklist_exceptions WHERE domain = ? AND org_id = ?",
        [d, oid],
    )
    return True


async def count(viewer_org_id: int | None = None) -> int:
    if viewer_org_id is None:
        row = await db_fetchone("SELECT COUNT(*) AS n FROM blocklist_exceptions")
    else:
        row = await db_fetchone(
            "SELECT COUNT(*) AS n FROM blocklist_exceptions WHERE org_id = ? OR org_id = ?",
            [GLOBAL_ORG, int(viewer_org_id)],
        )
    return int(row["n"]) if row else 0


async def add_many(
    domains: list[str],
    *,
    org_id: int | None = None,
    reason: str | None = None,
    created_by: str | None = None,
) -> dict:
    """Bulk INSERT. Retorna {added, skipped_dup, skipped_invalid}."""
    oid = _coerce_org(org_id)
    added = 0
    skipped_dup = 0
    skipped_invalid = 0
    seen: set[str] = set()
    for raw in domains:
        d = (raw or "").strip().lower()
        if not d or "." not in d or len(d) > 255 or any(c.isspace() for c in d):
            skipped_invalid += 1
            continue
        if d in seen:
            skipped_dup += 1
            continue
        seen.add(d)
        existing = await db_fetchone(
            "SELECT 1 FROM blocklist_exceptions WHERE domain = ? AND org_id = ?",
            [d, oid],
        )
        if existing:
            skipped_dup += 1
            continue
        await db_execute(
            "INSERT INTO blocklist_exceptions (domain, org_id, reason, created_by) VALUES (?, ?, ?, ?)",
            [d, oid, reason, created_by],
        )
        added += 1
    return {"added": added, "skipped_dup": skipped_dup, "skipped_invalid": skipped_invalid}


async def remove_many(domains: list[str], *, org_id: int | None = None) -> dict:
    """Bulk DELETE. Retorna {removed, not_found}."""
    oid = _coerce_org(org_id)
    removed = 0
    not_found = 0
    for raw in domains:
        d = (raw or "").strip().lower()
        if not d:
            not_found += 1
            continue
        existing = await db_fetchone(
            "SELECT 1 FROM blocklist_exceptions WHERE domain = ? AND org_id = ?",
            [d, oid],
        )
        if not existing:
            not_found += 1
            continue
        await db_execute(
            "DELETE FROM blocklist_exceptions WHERE domain = ? AND org_id = ?",
            [d, oid],
        )
        removed += 1
    return {"removed": removed, "not_found": not_found}
