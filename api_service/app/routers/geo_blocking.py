"""Endpoints /api/v1/geo-blocking — gerenciamento de bloqueio por país."""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability
from app.services import geo_blocking_service

router = APIRouter(prefix="/api/v1/geo-blocking", tags=["geo-blocking"])


@router.get("/status")
async def status(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    """Settings + lista de países + preview do include atual."""
    settings = await geo_blocking_service.get_settings()
    countries = await geo_blocking_service.list_countries()
    preview = await geo_blocking_service.preview()
    return {
        "settings": settings["settings"],
        "defaults": settings["defaults"],
        "countries": countries,
        "total_blocked": sum(1 for c in countries if c["blocked"]),
        "preview_cidrs": preview["total_cidrs"],
        "ipv4_count": preview["ipv4_count"],
        "ipv6_count": preview["ipv6_count"],
        "target_path": preview["target_path"],
    }


@router.put("/settings")
async def update_settings(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    body: dict,
) -> dict:
    updated = await geo_blocking_service.update_settings(body)
    return {"updated": updated}


@router.post("/countries")
async def add_country(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    body: dict,
) -> dict:
    """body: {country_code, country_name, blocked?: true, refresh?: true}."""
    cc = str(body.get("country_code", "")).strip().upper()
    name = str(body.get("country_name", cc)).strip()
    blocked = bool(body.get("blocked", True))
    do_refresh = bool(body.get("refresh", True))
    res = await geo_blocking_service.add_country(cc, name, blocked=blocked)
    if not res.get("ok"):
        return res
    if do_refresh:
        refresh = await geo_blocking_service.refresh_country(cc)
        res["refresh"] = refresh
    return res


@router.delete("/countries/{country_code}")
async def remove_country(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    country_code: str,
) -> dict:
    return await geo_blocking_service.remove_country(country_code)


@router.put("/countries/{country_code}/blocked")
async def set_blocked(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    country_code: str,
    body: dict,
) -> dict:
    blocked = bool(body.get("blocked", True))
    return await geo_blocking_service.set_blocked(country_code, blocked)


@router.post("/countries/{country_code}/refresh")
async def refresh_country(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    country_code: str,
) -> dict:
    return await geo_blocking_service.refresh_country(country_code)


@router.post("/refresh-all")
async def refresh_all(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    only_blocked: bool = Query(True),
) -> dict:
    return await geo_blocking_service.refresh_all(only_blocked=only_blocked)


@router.get("/preview")
async def preview(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await geo_blocking_service.preview()


@router.post("/apply")
async def apply(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    return await geo_blocking_service.apply()
