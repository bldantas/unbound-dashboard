"""Network endpoints — interfaces, roteamento e NTP."""

from __future__ import annotations

from fastapi import APIRouter, Depends

from app.core.deps import require_auth
from app.services.network_service import NetworkService

router = APIRouter(prefix="/api/v2/network", tags=["network"])


def _get_service() -> NetworkService:
    return NetworkService()


@router.get("/interfaces")
async def get_interfaces(
    _: dict = Depends(require_auth),
    svc: NetworkService = Depends(_get_service),
) -> list[dict]:
    return await svc.get_interfaces()


@router.get("/io")
async def get_io(
    _: dict = Depends(require_auth),
    svc: NetworkService = Depends(_get_service),
) -> dict:
    return await svc.get_io_stats()


@router.get("/ntp")
async def get_ntp(
    _: dict = Depends(require_auth),
    svc: NetworkService = Depends(_get_service),
) -> dict:
    return await svc.get_ntp_status()


@router.get("/routes")
async def get_routes(
    _: dict = Depends(require_auth),
    svc: NetworkService = Depends(_get_service),
) -> list[str]:
    return await svc.get_routing_table()
