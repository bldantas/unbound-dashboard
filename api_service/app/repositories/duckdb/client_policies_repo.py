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


async def list_all() -> list[dict]:
    return await db_fetchall(
        """
        SELECT id, slug, name, description, enabled, sort_order, created_at
        FROM client_policies
        ORDER BY sort_order, name
        """
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


async def create(slug: str, name: str, description: str | None) -> dict:
    """Cria policy. Retorna a linha completa. ON CONFLICT no slug → erro do duckdb."""
    await db_execute(
        """
        INSERT INTO client_policies (slug, name, description) VALUES (?, ?, ?)
        """,
        [slug, name, description],
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


async def summary() -> list[dict]:
    """Lista de policies com count de ranges/blocks/allows. Pra UI de listagem."""
    return await db_fetchall(
        """
        SELECT
            p.id, p.slug, p.name, p.description, p.enabled, p.sort_order, p.created_at,
            COALESCE((SELECT COUNT(*) FROM client_policy_ranges  WHERE policy_id = p.id), 0) AS ranges_count,
            COALESCE((SELECT COUNT(*) FROM client_policy_blocks  WHERE policy_id = p.id), 0) AS blocks_count,
            COALESCE((SELECT COUNT(*) FROM client_policy_allows  WHERE policy_id = p.id), 0) AS allows_count
        FROM client_policies p
        ORDER BY p.sort_order, p.name
        """
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
    """Lista todas as policies com `enabled=true`, com ranges/blocks/allows.

    Consumida pelo PHP UnboundConfigManager.generateViewsConf via API.
    """
    policies = await db_fetchall(
        """
        SELECT id, slug, name, enabled, sort_order
        FROM client_policies
        WHERE enabled = true
        ORDER BY sort_order, name
        """
    )
    out: list[dict] = []
    for p in policies:
        pid = int(p["id"])
        out.append(
            {
                "id": pid,
                "slug": p["slug"],
                "name": p["name"],
                "ranges": [r["cidr"] for r in await list_ranges(pid)],
                "blocks": [r["domain"] for r in await list_blocks(pid)],
                "allows": [r["domain"] for r in await list_allows(pid)],
            }
        )
    return out
