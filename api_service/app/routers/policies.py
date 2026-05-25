"""Endpoints administrativos /api/v1/policies — split-horizon DNS (V11).

Reusa as capabilities blocklist.read/write porque é a mesma natureza
(controle de bloqueio DNS).
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Body, Depends, HTTPException, status

from app.core.deps import require_capability
from app.repositories.duckdb import client_policies_repo as repo

router = APIRouter(prefix="/api/v1/policies", tags=["policies"])


def _shape(row: dict) -> dict:
    created = row.get("created_at")
    return {
        "id": row.get("id"),
        "slug": row["slug"],
        "name": row["name"],
        "description": row.get("description"),
        "enabled": bool(row.get("enabled", True)),
        "sort_order": int(row.get("sort_order") or 100),
        "created_at": created.isoformat() if created else None,
    }


# ============================================================
# Policies CRUD
# ============================================================


@router.get("")
async def list_policies(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> dict:
    rows = await repo.summary()
    out = []
    for r in rows:
        d = _shape(r)
        d["ranges_count"] = int(r.get("ranges_count") or 0)
        d["blocks_count"] = int(r.get("blocks_count") or 0)
        d["allows_count"] = int(r.get("allows_count") or 0)
        out.append(d)
    return {"policies": out}


@router.get("/full-enabled")
async def list_full_enabled(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> dict:
    """Lista enabled+ranges+blocks+allows. Consumida pelo PHP pra gerar views.conf."""
    policies = await repo.list_all_full_enabled()
    return {"policies": policies}


@router.get("/{slug}")
async def get_policy(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> dict:
    p = await repo.full(slug)
    if not p:
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    return {
        **_shape(p),
        "ranges": [
            {"id": r["id"], "cidr": r["cidr"], "label": r.get("label")}
            for r in p["ranges"]
        ],
        "blocks": p["blocks"],
        "allows": p["allows"],
    }


@router.post("", status_code=status.HTTP_201_CREATED)
async def create_policy(
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    slug = (body.get("slug") or "").strip().lower()
    name = (body.get("name") or "").strip()
    description = (body.get("description") or "").strip() or None
    if not repo.validate_slug(slug):
        raise HTTPException(status_code=400, detail="slug inválido (use a-z 0-9 _ -, 2-50 chars, começa com letra)")
    if not name:
        raise HTTPException(status_code=400, detail="name é obrigatório")
    if await repo.get(slug):
        raise HTTPException(status_code=409, detail=f"slug '{slug}' já existe")
    row = await repo.create(slug, name, description)
    return {"policy": _shape(row)}


@router.patch("/{slug}")
async def update_policy(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    if not await repo.get(slug):
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    await repo.update(
        slug,
        name=body.get("name"),
        description=body.get("description"),
        enabled=body.get("enabled"),
    )
    return {"policy": _shape(await repo.get(slug))}


@router.delete("/{slug}")
async def delete_policy(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
) -> dict:
    deleted = await repo.delete(slug)
    if not deleted:
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    return {"deleted": True, "slug": slug}


# ============================================================
# Ranges (CIDRs)
# ============================================================


@router.post("/{slug}/ranges", status_code=status.HTTP_201_CREATED)
async def add_range(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    policy = await repo.get(slug)
    if not policy:
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    cidr = (body.get("cidr") or "").strip()
    label = (body.get("label") or "").strip() or None
    if not repo.validate_cidr(cidr):
        raise HTTPException(status_code=400, detail="CIDR inválido (use ex: 192.168.1.0/24 ou 10.0.0.5)")
    range_id = await repo.add_range(int(policy["id"]), cidr, label)
    return {"added": range_id is not None, "id": range_id, "cidr": cidr}


@router.delete("/{slug}/ranges/{range_id}")
async def remove_range(
    slug: str,
    range_id: int,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
) -> dict:
    removed = await repo.remove_range(range_id)
    return {"removed": removed, "id": range_id}


# ============================================================
# Blocks (extras NXDOMAIN na view)
# ============================================================


@router.post("/{slug}/blocks", status_code=status.HTTP_201_CREATED)
async def add_block(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    policy = await repo.get(slug)
    if not policy:
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    domain = (body.get("domain") or "").strip().lower()
    if not domain:
        raise HTTPException(status_code=400, detail="domain é obrigatório")
    added = await repo.add_block(int(policy["id"]), domain)
    return {"added": added, "domain": domain}


@router.delete("/{slug}/blocks/{domain}")
async def remove_block(
    slug: str,
    domain: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
) -> dict:
    policy = await repo.get(slug)
    if not policy:
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    removed = await repo.remove_block(int(policy["id"]), domain)
    return {"removed": removed, "domain": domain.lower().strip()}


# ============================================================
# Allows (transparent na view)
# ============================================================


@router.post("/{slug}/allows", status_code=status.HTTP_201_CREATED)
async def add_allow(
    slug: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
    body: dict = Body(...),
) -> dict:
    policy = await repo.get(slug)
    if not policy:
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    domain = (body.get("domain") or "").strip().lower()
    if not domain:
        raise HTTPException(status_code=400, detail="domain é obrigatório")
    added = await repo.add_allow(int(policy["id"]), domain)
    return {"added": added, "domain": domain}


@router.delete("/{slug}/allows/{domain}")
async def remove_allow(
    slug: str,
    domain: str,
    _: Annotated[dict, Depends(require_capability("blocklist.write"))],
) -> dict:
    policy = await repo.get(slug)
    if not policy:
        raise HTTPException(status_code=404, detail=f"policy '{slug}' não existe")
    removed = await repo.remove_allow(int(policy["id"]), domain)
    return {"removed": removed, "domain": domain.lower().strip()}
