"""
organizations_service — multi-tenant minimalista (CRUD de orgs).

Esta versão entrega só infraestrutura. Não particiona dados existentes
nem filtra listings por org — outras tabelas continuam globais. Próximas
iterações vão estender: tenant data filtering, RBAC per-org, etc.
"""

from __future__ import annotations

import re
from datetime import datetime
from typing import Any

import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

_SLUG_RE = re.compile(r"^[a-z0-9][a-z0-9-]{0,79}$")


def _to_iso(v: Any) -> str | None:
    return v.isoformat() if isinstance(v, datetime) else (str(v) if v else None)


def _row_to_dict(r: dict) -> dict:
    return {
        "id": int(r["id"]),
        "name": r["name"],
        "slug": r["slug"],
        "description": r.get("description") or "",
        "is_active": bool(r.get("is_active", True)),
        "created_at": _to_iso(r.get("created_at")),
        "updated_at": _to_iso(r.get("updated_at")),
    }


async def list_orgs(include_inactive: bool = True) -> list[dict]:
    where = "" if include_inactive else "WHERE is_active = true"
    rows = await db_fetchall(
        f"SELECT * FROM organizations {where} ORDER BY name ASC", []
    )
    out = []
    for r in rows:
        d = _row_to_dict(r)
        cnt_row = await db_fetchone(
            "SELECT COUNT(*) AS n FROM users WHERE org_id = ?", [d["id"]]
        )
        d["user_count"] = int(cnt_row["n"]) if cnt_row else 0
        out.append(d)
    return out


async def create_org(name: str, slug: str, description: str = "") -> dict:
    name = (name or "").strip()
    slug = (slug or "").strip().lower()
    if not name:
        raise ValueError("name obrigatório")
    if not _SLUG_RE.match(slug):
        raise ValueError("slug inválido (use a-z, 0-9, hífen; começa com letra/dígito; max 80)")
    await db_execute(
        "INSERT INTO organizations (name, slug, description) VALUES (?, ?, ?)",
        [name[:120], slug[:80], (description or "")[:500]],
    )
    row = await db_fetchone("SELECT * FROM organizations WHERE slug = ?", [slug])
    return _row_to_dict(row) if row else {}


async def update_org(org_id: int, body: dict) -> bool:
    sets = []
    params: list = []
    if "name" in body:
        v = str(body["name"]).strip()
        if not v:
            raise ValueError("name vazio")
        sets.append("name = ?")
        params.append(v[:120])
    if "description" in body:
        sets.append("description = ?")
        params.append(str(body["description"] or "")[:500])
    if "is_active" in body:
        sets.append("is_active = ?")
        params.append(bool(body["is_active"]))
    # slug é imutável — força regenerar
    if not sets:
        return False
    sets.append("updated_at = NOW()")
    params.append(int(org_id))
    await db_execute(f"UPDATE organizations SET {', '.join(sets)} WHERE id = ?", params)
    return True


async def delete_org(org_id: int) -> dict:
    """Bloqueia delete se houver users vinculados — força realocação primeiro."""
    cnt_row = await db_fetchone(
        "SELECT COUNT(*) AS n FROM users WHERE org_id = ?", [int(org_id)]
    )
    if cnt_row and int(cnt_row["n"]) > 0:
        return {"ok": False, "error": f"{int(cnt_row['n'])} usuários ainda vinculados — realoque antes"}
    row = await db_fetchone(
        "SELECT id FROM organizations WHERE id = ?", [int(org_id)]
    )
    if not row:
        return {"ok": False, "error": "org não encontrada"}
    await db_execute("DELETE FROM organizations WHERE id = ?", [int(org_id)])
    return {"ok": True}


async def assign_user(user_id: int, org_id: int | None) -> bool:
    """Atribui org_id a um usuário. None = remover (vira system global)."""
    if org_id is not None:
        row = await db_fetchone(
            "SELECT id FROM organizations WHERE id = ?", [int(org_id)]
        )
        if not row:
            return False
    await db_execute(
        "UPDATE users SET org_id = ? WHERE id = ?",
        [int(org_id) if org_id else None, int(user_id)],
    )
    return True
