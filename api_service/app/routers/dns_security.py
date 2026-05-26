"""
Endpoints /api/v1/dns-security — modo upstream (recursivo/DoT) e DNSSEC.

GET  /info     — counters DNSSEC + trust-anchor + tls-bundle status
GET  /settings — config atual + presets disponíveis
PUT  /settings — atualiza (mode/provider/custom)
POST /apply    — gera forwarders.conf, valida, restart unbound

Capabilities:
- info/settings GET → dashboard.read
- settings PUT / apply → config.write (admin)
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends

from app.core.deps import require_capability
from app.services import dns_security_service

router = APIRouter(prefix="/api/v1/dns-security", tags=["dns-security"])


@router.get("/info")
async def get_info(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await dns_security_service.info()


@router.get("/settings")
async def get_settings(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await dns_security_service.get_settings()


@router.put("/settings")
async def update_settings(
    body: dict,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    n = await dns_security_service.update_settings(body)
    return {"updated": n}


@router.post("/apply")
async def apply(
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    return await dns_security_service.apply()


@router.get("/ratelimit/settings")
async def get_ratelimit(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await dns_security_service.get_ratelimit_settings()


@router.put("/ratelimit/settings")
async def update_ratelimit(
    body: dict,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    n = await dns_security_service.update_ratelimit_settings(body)
    return {"updated": n}


@router.get("/privacy/settings")
async def get_privacy(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await dns_security_service.get_privacy_settings()


@router.put("/privacy/settings")
async def update_privacy(
    body: dict,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    n = await dns_security_service.update_privacy_settings(body)
    return {"updated": n}
