"""Endpoints administrativos /api/v1/blocklist.

Pós-V9 (multi-source) expõe três famílias:
- Catálogo de fontes:        /sources, /sources/{slug}, /sources/{slug}/sync
- Allowlist global:          /exceptions, /exceptions/{domain}
- Pra UnboundConfigManager:  /domains-to-block (gera o blocked_domains.conf)

Endpoints legados (/counts, /search, /clear-category, /bulk-insert) seguem
intactos pra não quebrar callers PHP atuais — implementação usa os repos
novos por trás dos panos.
"""

from __future__ import annotations

from typing import Annotated, Literal

from fastapi import APIRouter, Body, Depends, HTTPException, Query, status

from app.core.deps import require_capability, resolve_viewer_org_id
from app.repositories.duckdb import (
    blocklist_exceptions_repo,
    blocklist_sources_repo,
    threats_repo,
)
from app.workers import blocklist_syncer

router = APIRouter(prefix="/api/v1/blocklist", tags=["blocklist"])

# Categorias whitelisted — qualquer outro valor é rejeitado pelo pydantic.
_AllowedCategory = Literal["Judicial", "Malware/Adware", "Phishing"]


@router.get("/counts")
async def counts(_: Annotated[dict, Depends(require_capability("blocklist.read"))]) -> dict[str, int]:
    """Retorna count por categoria (Malware/Adware, Phishing, Judicial)."""
    counts = await threats_repo.counts_by_category()
    return {
        "adware": counts.get("Malware/Adware", 0),
        "phishing": counts.get("Phishing", 0),
        "judicial": counts.get("Judicial", 0),
    }


@router.get("/search")
async def search(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
    q: str = Query("", max_length=120, description="Termo a buscar em domain (LIKE %q%)"),
    category: _AllowedCategory | None = Query(None, description="Filtra por categoria; ausente = todas"),
    tld: str = Query("", max_length=20, description="Filtra por TLD (sufixo após o último ponto)"),
    page: int = Query(1, ge=1, le=10000),
    per_page: int = Query(50, ge=1, le=100),
) -> dict:
    """
    Busca paginada em `blocklist_domains` (DuckDB). Substitui o antigo
    `api/blocklist_search.php`, que lia o arquivo flat e só via ANATEL.

    Retorna estrutura compatível com o JS atual de `/blocklist.php`:
    `{success, total, filtered, page, per_page, total_pages, domains, top_tlds, by_category}`.
    """
    q_norm = q.strip()
    tld_norm = tld.strip().lstrip(".")
    cat = category if category else None
    offset = (page - 1) * per_page

    # Total absoluto (sem filtros) — fica visível como "Origem"
    total = await threats_repo.blocklist_count()

    # Filtered: o que casa os filtros atuais
    filtered = await threats_repo.count_blocklist(q=q_norm, category=cat, tld=tld_norm)

    rows = await threats_repo.search_blocklist(
        q=q_norm, category=cat, tld=tld_norm, offset=offset, limit=per_page
    )

    # Top TLDs respeitando o filtro de categoria (mas NÃO q/tld — distribuição global)
    tlds_map = await threats_repo.top_tlds(category=cat, limit=20)

    by_category = await threats_repo.counts_by_category()

    total_pages = max(1, (filtered + per_page - 1) // per_page)

    return {
        "success": True,
        "total": total,
        "filtered": filtered,
        "page": page,
        "per_page": per_page,
        "total_pages": total_pages,
        "domains": [r["domain"] for r in rows],
        "top_tlds": tlds_map,
        "by_category": {
            "judicial": int(by_category.get("Judicial", 0)),
            "adware": int(by_category.get("Malware/Adware", 0)),
            "phishing": int(by_category.get("Phishing", 0)),
        },
    }


@router.post("/clear-category", status_code=status.HTTP_200_OK)
async def clear_category(
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict,
) -> dict:
    """DELETE FROM blocklist_domains WHERE category = ?. Body: {category: str}."""
    category = str(body.get("category") or "").strip()
    if not category:
        return {"deleted": 0}
    deleted = await threats_repo.clear_category(category)
    return {"deleted": deleted}


@router.post("/bulk-insert", status_code=status.HTTP_200_OK)
async def bulk_insert(
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    entries: list[dict],
) -> dict:
    """Bulk UPSERT (legacy shim). Body: [{domain, category, severity}].

    Pós-V9 mapeia category → primeira source que casa. Novos chamadores devem
    usar POST /sources/{slug}/sync no lugar.
    """
    inserted = await threats_repo.bulk_insert(entries)
    return {"inserted": inserted}


# ============================================================
# Sources (catálogo de fontes curadas)
# ============================================================


def _shape_source(row: dict) -> dict:
    """Normaliza retorno do row → JSON friendly (last_sync ISO etc)."""
    last_sync = row.get("last_sync")
    return {
        "slug": row["slug"],
        "name": row["name"],
        "description": row.get("description"),
        "url": row["url"],
        "format": row["format"],
        "category": row["category"],
        "severity": row["severity"],
        "index_enabled": bool(row["index_enabled"]),
        "block_enabled": bool(row["block_enabled"]),
        "is_builtin": bool(row.get("is_builtin", True)),
        "sort_order": int(row.get("sort_order") or 100),
        "last_sync": last_sync.isoformat() if last_sync else None,
        "last_count": int(row.get("last_count") or 0),
        "last_error": row.get("last_error"),
    }


@router.get("/sources")
async def list_sources(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> dict:
    """Lista todas as sources curadas com flags e estatísticas."""
    rows = await blocklist_sources_repo.list_all()
    return {"sources": [_shape_source(r) for r in rows]}


@router.patch("/sources/{slug}")
async def update_source(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    """Toggle index_enabled e/ou block_enabled.

    Body: {"index_enabled": bool?, "block_enabled": bool?}
    Se index_enabled=false e count>0, mantém entries (usuário pode reativar
    depois sem perder dados; pra zerar use POST /sources/{slug}/sync com
    force depois de desligar, ou DELETE explícito futuramente).
    """
    src = await blocklist_sources_repo.get(slug)
    if not src:
        raise HTTPException(status_code=404, detail=f"source '{slug}' não existe")

    idx = body.get("index_enabled")
    blk = body.get("block_enabled")
    if idx is None and blk is None:
        raise HTTPException(status_code=400, detail="forneça index_enabled e/ou block_enabled")

    await blocklist_sources_repo.set_flags(
        slug,
        index_enabled=bool(idx) if idx is not None else None,
        block_enabled=bool(blk) if blk is not None else None,
    )
    updated = await blocklist_sources_repo.get(slug)
    return {"source": _shape_source(updated)}


@router.post("/sources/{slug}/sync")
async def sync_source(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    force: bool = Query(True, description="Se true, sincroniza mesmo se last_sync recente"),
) -> dict:
    """Dispara sync sob demanda. Retorna {status, count, error}."""
    src = await blocklist_sources_repo.get(slug)
    if not src:
        raise HTTPException(status_code=404, detail=f"source '{slug}' não existe")
    result = await blocklist_syncer.sync_source(slug, force=force)
    return result


@router.get("/domains-to-block")
async def domains_to_block(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> dict:
    """União dos domínios em sources com block_enabled=true MENOS exceptions.

    Consumido pelo PHP UnboundConfigManager pra regerar
    /etc/unbound/includes/blocked_domains.conf. Resposta pode ser pesada
    (centenas de milhares); não pagina.
    """
    domains = await blocklist_sources_repo.domains_to_block()
    return {"count": len(domains), "domains": domains}


# ============================================================
# Allowlist (blocklist_exceptions)
# ============================================================


def _resolve_target_org(body_org_id, viewer_org_id: int | None) -> int:
    """Determina org_id de write a partir do body + viewer. Aplica RBAC tenant.

    - viewer_org_id is None (system admin): aceita body.org_id (0 ou N); default 0
    - viewer_org_id = N (org-scoped): força N; body com outro org_id vira 403

    Retorna org_id já normalizado (0 = global).
    """
    if viewer_org_id is None:
        if body_org_id is None or body_org_id == "":
            return 0
        try:
            return int(body_org_id)
        except (TypeError, ValueError) as exc:
            raise HTTPException(status_code=400, detail="org_id inválido") from exc
    # User org-scoped: sempre força a própria org
    if body_org_id is not None and body_org_id != "" and int(body_org_id) != int(viewer_org_id):
        raise HTTPException(
            status_code=403,
            detail="Sem permissão pra criar exceção fora da própria organização",
        )
    return int(viewer_org_id)


@router.get("/exceptions")
async def list_exceptions(
    payload: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> dict:
    """Lista exceções visíveis pro viewer (globais + da própria org)."""
    viewer_org_id = await resolve_viewer_org_id(payload)
    rows = await blocklist_exceptions_repo.list_all(viewer_org_id=viewer_org_id)
    return {
        "count": len(rows),
        "exceptions": [
            {
                "domain": r["domain"],
                "org_id": r.get("org_id") or 0,
                "scope": "global" if (r.get("org_id") or 0) == 0 else f"org:{r.get('org_id')}",
                "reason": r.get("reason"),
                "created_by": r.get("created_by"),
                "created_at": r["created_at"].isoformat() if r.get("created_at") else None,
            }
            for r in rows
        ],
    }


@router.post("/exceptions", status_code=status.HTTP_201_CREATED)
async def add_exception(
    payload: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    """Adiciona exceção. Body: {"domain": str, "reason": str?, "org_id": int?}.

    `org_id` opcional. Admin global default = 0 (allowlist global).
    User org-scoped sempre força a própria org (body.org_id é ignorado/checado).
    """
    domain = (body.get("domain") or "").strip().lower()
    if not domain:
        raise HTTPException(status_code=400, detail="domain é obrigatório")
    viewer_org_id = await resolve_viewer_org_id(payload)
    target_org = _resolve_target_org(body.get("org_id"), viewer_org_id)
    reason = (body.get("reason") or "").strip() or None
    created_by = payload.get("username") if isinstance(payload, dict) else None
    added = await blocklist_exceptions_repo.add(
        domain, org_id=target_org, reason=reason, created_by=created_by,
    )
    return {"added": added, "domain": domain, "org_id": target_org}


@router.delete("/exceptions/{domain}")
async def remove_exception(
    domain: str,
    payload: Annotated[dict, Depends(require_capability("blocklist.write"))],
    org_id: int | None = Query(None, description="0=global, N=org. Default = própria org do viewer ou 0 pra admin global."),
) -> dict:
    """Remove exceção. Sem org_id explícito, admin global apaga a global (0);
    user org-scoped apaga a da própria org."""
    viewer_org_id = await resolve_viewer_org_id(payload)
    target_org = _resolve_target_org(org_id, viewer_org_id)
    removed = await blocklist_exceptions_repo.remove(domain, org_id=target_org)
    return {"removed": removed, "domain": domain.lower().strip(), "org_id": target_org}


@router.post("/exceptions/bulk")
async def bulk_add_exceptions(
    payload: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    """
    Bulk add. Body: `{"domains": [...], "reason": str?, "org_id": int?}`.
    Aceita até 50.000 domínios. Pula inválidos (sem ponto, vazio, com espaço) e
    duplicados (já na tabela ou repetidos no payload).
    """
    domains = body.get("domains") or []
    if not isinstance(domains, list):
        raise HTTPException(status_code=400, detail="`domains` deve ser uma lista de strings")
    if len(domains) > 50000:
        raise HTTPException(status_code=400, detail="Máximo 50.000 domínios por chamada")
    viewer_org_id = await resolve_viewer_org_id(payload)
    target_org = _resolve_target_org(body.get("org_id"), viewer_org_id)
    reason = (body.get("reason") or "").strip() or None
    created_by = payload.get("username") if isinstance(payload, dict) else None
    return await blocklist_exceptions_repo.add_many(
        [str(x) for x in domains],
        org_id=target_org,
        reason=reason,
        created_by=created_by,
    )


@router.post("/exceptions/bulk-delete")
async def bulk_remove_exceptions(
    payload: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    """Bulk delete. Body: `{"domains": [...], "org_id": int?}`."""
    domains = body.get("domains") or []
    if not isinstance(domains, list):
        raise HTTPException(status_code=400, detail="`domains` deve ser uma lista de strings")
    if len(domains) > 50000:
        raise HTTPException(status_code=400, detail="Máximo 50.000 domínios por chamada")
    viewer_org_id = await resolve_viewer_org_id(payload)
    target_org = _resolve_target_org(body.get("org_id"), viewer_org_id)
    return await blocklist_exceptions_repo.remove_many(
        [str(x) for x in domains], org_id=target_org,
    )


@router.get("/exceptions/export.csv")
async def export_exceptions_csv(
    payload: Annotated[dict, Depends(require_capability("blocklist.read"))],
):
    """Download da allowlist em CSV. 1 domínio por linha (compat com import).
    Inclui scope (global ou nome da org) por linha."""
    import csv
    import io

    from fastapi.responses import StreamingResponse

    viewer_org_id = await resolve_viewer_org_id(payload)
    rows = await blocklist_exceptions_repo.list_all(viewer_org_id=viewer_org_id)
    buf = io.StringIO()
    writer = csv.writer(buf)
    writer.writerow(["domain", "org_id", "scope", "reason", "created_by", "created_at"])
    for r in rows:
        oid = r.get("org_id") or 0
        writer.writerow([
            r["domain"],
            oid,
            "global" if oid == 0 else f"org:{oid}",
            r.get("reason") or "",
            r.get("created_by") or "",
            r["created_at"].isoformat() if r.get("created_at") else "",
        ])
    buf.seek(0)
    return StreamingResponse(
        iter([buf.getvalue()]),
        media_type="text/csv",
        headers={"Content-Disposition": 'attachment; filename="allowlist.csv"'},
    )
