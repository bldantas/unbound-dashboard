"""
Multi-host sync — gera payload de config local (blocklists + policies) pra
push pro agent, e aplica payload recebido (agent side).

Filosofia: replica APENAS o que faz sentido sincronizar. Não envia bytes
de blocklist baixados (cada agent baixa as URLs ele mesmo), só os flags
"qual source está ativa onde". Policies replicam tudo: ranges, blocks,
allows — porque elas definem comportamento, não dados externos.
"""

from __future__ import annotations

from typing import Any

import structlog

from app.repositories.duckdb import blocklist_sources_repo, client_policies_repo
from app.repositories.duckdb.connection import db_execute

log = structlog.get_logger(__name__)


# ============================================================
# MASTER — build outbound payload
# ============================================================


async def build_blocklists_payload() -> list[dict[str, Any]]:
    """Lista de fontes com flags + URL — agent reaproveita pra baixar."""
    rows = await blocklist_sources_repo.list_all()
    return [
        {
            "slug": r["slug"],
            "url": r.get("url"),
            "index_enabled": bool(r["index_enabled"]),
            "block_enabled": bool(r["block_enabled"]),
        }
        for r in rows
    ]


async def build_policies_payload() -> list[dict[str, Any]]:
    """Policies completas (slug/name/enabled/ranges/blocks/allows)."""
    out: list[dict[str, Any]] = []
    for p in await client_policies_repo.list_all():
        slug = p["slug"]
        full = await client_policies_repo.full(slug)
        if not full:
            continue
        out.append(
            {
                "slug": full["slug"],
                "name": full["name"],
                "description": full.get("description") or "",
                "enabled": bool(full["enabled"]),
                "ranges": [
                    {"cidr": r["cidr"], "label": r.get("label")}
                    for r in full["ranges"]
                ],
                "blocks": full["blocks"],
                "allows": full["allows"],
            }
        )
    return out


# ============================================================
# AGENT — apply inbound payload (UPSERT semantics)
# ============================================================


async def apply_blocklists(items: list[dict[str, Any]]) -> dict[str, int]:
    """
    Aplica flags das blocklist sources recebidas. Só atualiza sources
    que JÁ existem localmente (não cria fontes novas — o catálogo é
    fixo no agent). Retorna {updated, skipped_unknown}.
    """
    updated = 0
    skipped = 0
    for it in items:
        slug = it.get("slug")
        if not slug:
            skipped += 1
            continue
        existing = await blocklist_sources_repo.get(slug)
        if not existing:
            skipped += 1
            continue
        await blocklist_sources_repo.set_flags(
            slug,
            index_enabled=bool(it.get("index_enabled", False)),
            block_enabled=bool(it.get("block_enabled", False)),
        )
        updated += 1
    return {"updated": updated, "skipped_unknown": skipped}


async def apply_policies(items: list[dict[str, Any]]) -> dict[str, int]:
    """
    Replica policies por slug. Para cada policy do payload:
      - Cria se não existir; atualiza name/description/enabled se existir.
      - Substitui ranges, blocks e allows pelo conteúdo do payload (idempotente).
    Policies locais NÃO presentes no payload são preservadas (não deleta —
    o sync é aditivo/atualizador, não destrutivo).
    """
    created = 0
    updated = 0
    for it in items:
        slug = it.get("slug")
        name = it.get("name") or slug
        if not slug or not client_policies_repo.validate_slug(slug):
            continue
        existing = await client_policies_repo.get(slug)
        if not existing:
            await client_policies_repo.create(slug, name, it.get("description"))
            created += 1
            existing = await client_policies_repo.get(slug)
        else:
            await client_policies_repo.update(
                slug,
                name=name,
                description=it.get("description"),
                enabled=bool(it.get("enabled", True)),
            )
            updated += 1
        if not existing:
            continue
        pid = int(existing["id"])

        # Replace ranges (idempotente, simples DELETE+INSERT — small N)
        await db_execute("DELETE FROM client_policy_ranges WHERE policy_id = ?", [pid])
        for r in it.get("ranges", []):
            cidr = r.get("cidr", "")
            if not client_policies_repo.validate_cidr(cidr):
                continue
            await client_policies_repo.add_range(pid, cidr, r.get("label"))

        # Replace blocks / allows
        await db_execute("DELETE FROM client_policy_blocks WHERE policy_id = ?", [pid])
        for dom in it.get("blocks", []):
            if isinstance(dom, str) and dom.strip():
                await client_policies_repo.add_block(pid, dom.strip().lower())

        await db_execute("DELETE FROM client_policy_allows WHERE policy_id = ?", [pid])
        for dom in it.get("allows", []):
            if isinstance(dom, str) and dom.strip():
                await client_policies_repo.add_allow(pid, dom.strip().lower())

    return {"created": created, "updated": updated, "total_received": len(items)}
