"""CRUD de client_policies, ranges, blocks e allows (V11).

Modelo: cada policy tem 1..N ranges (CIDR/IP), 0..N domínios extras pra bloquear
e 0..N domínios pra permitir explicitamente. Geração do views.conf consome
`full(slug)` ou `list_all_full()` pra montar as views do Unbound.
"""

from __future__ import annotations

import re

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

_SLUG_RE = re.compile(r"^[a-z][a-z0-9_-]{1,49}$")
_CIDR_RE = re.compile(
    r"^("
    r"(?:25[0-5]|2[0-4]\d|1?\d?\d)(?:\.(?:25[0-5]|2[0-4]\d|1?\d?\d)){3}(?:/(?:3[0-2]|[12]?\d))?"  # IPv4 / opcional /N
    r"|"
    r"[0-9a-fA-F:]+(?:/(?:1[01]\d|12[0-8]|\d{1,2}))?"                                              # IPv6 / opcional /N
    r")$"
)


def validate_slug(s: str) -> bool:
    return bool(_SLUG_RE.match(s or ""))


def validate_cidr(s: str) -> bool:
    return bool(_CIDR_RE.match(s or ""))


# ============================================================
# Policies
# ============================================================


async def list_all(viewer_org_id: int | None = None) -> list[dict]:
    """Lista policies. `viewer_org_id`:
    - None → admin global, vê todas.
    - int  → vê globais (org_id IS NULL) + da própria org.
    """
    if viewer_org_id is None:
        return await db_fetchall(
            """
            SELECT p.id, p.slug, p.name, p.description, p.enabled, p.sort_order,
                   p.created_at, p.org_id, o.name AS org_name, o.slug AS org_slug
            FROM client_policies p
            LEFT JOIN organizations o ON o.id = p.org_id
            ORDER BY p.sort_order, p.name
            """
        )
    return await db_fetchall(
        """
        SELECT p.id, p.slug, p.name, p.description, p.enabled, p.sort_order,
               p.created_at, p.org_id, o.name AS org_name, o.slug AS org_slug
        FROM client_policies p
        LEFT JOIN organizations o ON o.id = p.org_id
        WHERE p.org_id IS NULL OR p.org_id = ?
        ORDER BY p.sort_order, p.name
        """,
        [int(viewer_org_id)],
    )


async def get(slug: str) -> dict | None:
    return await db_fetchone(
        "SELECT * FROM client_policies WHERE slug = ?",
        [slug],
    )


async def get_by_id(policy_id: int) -> dict | None:
    return await db_fetchone(
        "SELECT * FROM client_policies WHERE id = ?",
        [int(policy_id)],
    )


async def create(slug: str, name: str, description: str | None, org_id: int | None = None) -> dict:
    """Cria policy. Retorna a linha completa. ON CONFLICT no slug → erro do duckdb.

    `org_id`: None = global; int = pertence à org especificada.
    """
    await db_execute(
        """
        INSERT INTO client_policies (slug, name, description, org_id) VALUES (?, ?, ?, ?)
        """,
        [slug, name, description, int(org_id) if org_id else None],
    )
    row = await get(slug)
    assert row is not None  # acabamos de inserir
    return row


async def update(slug: str, *, name: str | None = None, description: str | None = None, enabled: bool | None = None) -> bool:
    fields, args = [], []
    if name is not None:
        fields.append("name = ?")
        args.append(name)
    if description is not None:
        fields.append("description = ?")
        args.append(description)
    if enabled is not None:
        fields.append("enabled = ?")
        args.append(bool(enabled))
    if not fields:
        return False
    args.append(slug)
    await db_execute(
        f"UPDATE client_policies SET {', '.join(fields)} WHERE slug = ?",
        args,
    )
    return True


async def delete(slug: str) -> bool:
    row = await get(slug)
    if not row:
        return False
    pid = int(row["id"])
    # Cleanup cascateado: deleta ranges/blocks/allows antes do policy
    await db_execute("DELETE FROM client_policy_ranges WHERE policy_id = ?", [pid])
    await db_execute("DELETE FROM client_policy_blocks WHERE policy_id = ?", [pid])
    await db_execute("DELETE FROM client_policy_allows WHERE policy_id = ?", [pid])
    await db_execute("DELETE FROM client_policies WHERE id = ?", [pid])
    return True


# ============================================================
# Ranges
# ============================================================


async def list_ranges(policy_id: int) -> list[dict]:
    return await db_fetchall(
        """
        SELECT id, policy_id, cidr, label
        FROM client_policy_ranges
        WHERE policy_id = ?
        ORDER BY cidr
        """,
        [int(policy_id)],
    )


async def add_range(policy_id: int, cidr: str, label: str | None) -> int | None:
    """Retorna id da nova range, ou None se já existia (idempotente)."""
    existing = await db_fetchone(
        "SELECT id FROM client_policy_ranges WHERE policy_id = ? AND cidr = ?",
        [int(policy_id), cidr],
    )
    if existing:
        return None
    await db_execute(
        "INSERT INTO client_policy_ranges (policy_id, cidr, label) VALUES (?, ?, ?)",
        [int(policy_id), cidr, label],
    )
    row = await db_fetchone(
        "SELECT id FROM client_policy_ranges WHERE policy_id = ? AND cidr = ?",
        [int(policy_id), cidr],
    )
    return int(row["id"]) if row else None


async def remove_range(range_id: int) -> bool:
    existing = await db_fetchone(
        "SELECT 1 FROM client_policy_ranges WHERE id = ?",
        [int(range_id)],
    )
    if not existing:
        return False
    await db_execute("DELETE FROM client_policy_ranges WHERE id = ?", [int(range_id)])
    return True


# ============================================================
# Blocks (always_nxdomain extras pra view)
# ============================================================


async def list_blocks(policy_id: int) -> list[dict]:
    return await db_fetchall(
        """
        SELECT domain, added_at FROM client_policy_blocks
        WHERE policy_id = ?
        ORDER BY domain
        """,
        [int(policy_id)],
    )


async def add_block(policy_id: int, domain: str) -> bool:
    d = (domain or "").strip().lower()
    if not d:
        return False
    existing = await db_fetchone(
        "SELECT 1 FROM client_policy_blocks WHERE policy_id = ? AND domain = ?",
        [int(policy_id), d],
    )
    if existing:
        return False
    await db_execute(
        "INSERT INTO client_policy_blocks (policy_id, domain) VALUES (?, ?)",
        [int(policy_id), d],
    )
    return True


async def remove_block(policy_id: int, domain: str) -> bool:
    d = (domain or "").strip().lower()
    existing = await db_fetchone(
        "SELECT 1 FROM client_policy_blocks WHERE policy_id = ? AND domain = ?",
        [int(policy_id), d],
    )
    if not existing:
        return False
    await db_execute(
        "DELETE FROM client_policy_blocks WHERE policy_id = ? AND domain = ?",
        [int(policy_id), d],
    )
    return True


# ============================================================
# Allows (transparent extras pra view)
# ============================================================


async def list_allows(policy_id: int) -> list[dict]:
    return await db_fetchall(
        """
        SELECT domain, added_at FROM client_policy_allows
        WHERE policy_id = ?
        ORDER BY domain
        """,
        [int(policy_id)],
    )


async def add_allow(policy_id: int, domain: str) -> bool:
    d = (domain or "").strip().lower()
    if not d:
        return False
    existing = await db_fetchone(
        "SELECT 1 FROM client_policy_allows WHERE policy_id = ? AND domain = ?",
        [int(policy_id), d],
    )
    if existing:
        return False
    await db_execute(
        "INSERT INTO client_policy_allows (policy_id, domain) VALUES (?, ?)",
        [int(policy_id), d],
    )
    return True


async def remove_allow(policy_id: int, domain: str) -> bool:
    d = (domain or "").strip().lower()
    existing = await db_fetchone(
        "SELECT 1 FROM client_policy_allows WHERE policy_id = ? AND domain = ?",
        [int(policy_id), d],
    )
    if not existing:
        return False
    await db_execute(
        "DELETE FROM client_policy_allows WHERE policy_id = ? AND domain = ?",
        [int(policy_id), d],
    )
    return True


# ============================================================
# Agregações
# ============================================================


async def summary(viewer_org_id: int | None = None) -> list[dict]:
    """Lista de policies com count de ranges/blocks/allows. Pra UI de listagem.

    `viewer_org_id`: None = admin global; int = filtra por própria org + globais.
    """
    if viewer_org_id is None:
        return await db_fetchall(
            """
            SELECT
                p.id, p.slug, p.name, p.description, p.enabled, p.sort_order, p.created_at,
                p.org_id, o.name AS org_name, o.slug AS org_slug,
                COALESCE((SELECT COUNT(*) FROM client_policy_ranges  WHERE policy_id = p.id), 0) AS ranges_count,
                COALESCE((SELECT COUNT(*) FROM client_policy_blocks  WHERE policy_id = p.id), 0) AS blocks_count,
                COALESCE((SELECT COUNT(*) FROM client_policy_allows  WHERE policy_id = p.id), 0) AS allows_count
            FROM client_policies p
            LEFT JOIN organizations o ON o.id = p.org_id
            ORDER BY p.sort_order, p.name
            """
        )
    return await db_fetchall(
        """
        SELECT
            p.id, p.slug, p.name, p.description, p.enabled, p.sort_order, p.created_at,
            p.org_id, o.name AS org_name, o.slug AS org_slug,
            COALESCE((SELECT COUNT(*) FROM client_policy_ranges  WHERE policy_id = p.id), 0) AS ranges_count,
            COALESCE((SELECT COUNT(*) FROM client_policy_blocks  WHERE policy_id = p.id), 0) AS blocks_count,
            COALESCE((SELECT COUNT(*) FROM client_policy_allows  WHERE policy_id = p.id), 0) AS allows_count
        FROM client_policies p
        LEFT JOIN organizations o ON o.id = p.org_id
        WHERE p.org_id IS NULL OR p.org_id = ?
        ORDER BY p.sort_order, p.name
        """,
        [int(viewer_org_id)],
    )


async def full(slug: str) -> dict | None:
    """Policy + ranges + blocks + allows numa estrutura aninhada."""
    policy = await get(slug)
    if not policy:
        return None
    pid = int(policy["id"])
    return {
        **policy,
        "ranges": await list_ranges(pid),
        "blocks": [r["domain"] for r in await list_blocks(pid)],
        "allows": [r["domain"] for r in await list_allows(pid)],
    }


async def list_all_full_enabled() -> list[dict]:
    """Lista todas as policies com `enabled=true`, com ranges/blocks/allows
    e org_exceptions (split-horizon de blocklist por org — v2.105).

    org_exceptions: domínios em `blocklist_exceptions` com `org_id` igual ao
    `org_id` da policy. Vão entrar como `local-zone "X." transparent` no view
    block do Unbound — sobrescreve o `always_nxdomain` global da blocklist
    pros clientes daquela view, na prática "destravando" o domínio só pra org.

    Dedupe vs allows é feito aqui pra evitar linhas duplicadas no .conf.

    Consumida pelo PHP UnboundConfigManager.generateViewsConf via API.
    """
    policies = await db_fetchall(
        """
        SELECT id, slug, name, enabled, sort_order, org_id
        FROM client_policies
        WHERE enabled = true
        ORDER BY sort_order, name
        """
    )
    out: list[dict] = []
    for p in policies:
        pid = int(p["id"])
        oid = p.get("org_id")
        # Carrega exceções da org da policy. Skip se:
        #   - policy não tem org (legacy / explicitamente global)
        #   - policy é "global" (org_id == 0 sentinela do blocklist_exceptions),
        #     porque essas exceções já entram no zonefile global via
        #     blocklist_sources_repo.domains_to_block() — duplicar é redundante
        org_exceptions: list[str] = []
        if oid is not None and int(oid) > 0:
            rows = await db_fetchall(
                "SELECT domain FROM blocklist_exceptions WHERE org_id = ? ORDER BY domain",
                [int(oid)],
            )
            org_exceptions = [str(r["domain"]) for r in rows]

        allows_list = [r["domain"] for r in await list_allows(pid)]
        # Dedupe org_exceptions vs allows — se o operador já tinha o mesmo
        # domínio em allows da policy, não duplica no .conf
        allows_set = {d.lower() for d in allows_list}
        org_exceptions = [d for d in org_exceptions if d.lower() not in allows_set]

        out.append(
            {
                "id": pid,
                "slug": p["slug"],
                "name": p["name"],
                "org_id": oid,
                "ranges": [r["cidr"] for r in await list_ranges(pid)],
                "blocks": [r["domain"] for r in await list_blocks(pid)],
                "allows": allows_list,
                "org_exceptions": org_exceptions,
            }
        )
    return out
