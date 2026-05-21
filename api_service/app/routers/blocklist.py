"""Endpoints administrativos /api/v1/blocklist — usados pelo UnboundConfigManager."""

from __future__ import annotations

from typing import Annotated, Literal

from fastapi import APIRouter, Depends, Query, status

from app.core.deps import require_capability
from app.repositories.duckdb import threats_repo

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
    """Bulk UPSERT. Body: [{domain, category, severity}]."""
    inserted = await threats_repo.bulk_insert(entries)
    return {"inserted": inserted}
