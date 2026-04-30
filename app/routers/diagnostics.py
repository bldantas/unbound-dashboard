"""Diagnostics endpoint — testes de conectividade sob demanda."""

from __future__ import annotations

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from app.core.deps import require_auth
from app.services.diagnostics_service import DiagnosticsService

router = APIRouter(prefix="/api/v2/diagnostics", tags=["diagnostics"])


def _get_service() -> DiagnosticsService:
    return DiagnosticsService()


class PingBody(BaseModel):
    host: str
    count: int = 4


class DnsBody(BaseModel):
    domain: str
    server: str = "127.0.0.1"


@router.post("/run")
async def run_all(
    _: dict = Depends(require_auth),
    svc: DiagnosticsService = Depends(_get_service),
) -> dict:
    """Executa bateria completa de testes (ping + DNS + internet)."""
    return await svc.run_all()


@router.post("/ping")
async def ping(
    body: PingBody,
    _: dict = Depends(require_auth),
    svc: DiagnosticsService = Depends(_get_service),
) -> dict:
    return await svc.ping(body.host, count=body.count)


@router.post("/dns")
async def dns_resolve(
    body: DnsBody,
    _: dict = Depends(require_auth),
    svc: DiagnosticsService = Depends(_get_service),
) -> dict:
    return await svc.dns_resolve(body.domain, server=body.server)
