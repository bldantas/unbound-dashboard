"""Healthcheck endpoint — usado pelo Apache reverse proxy e pelo systemd para liveness."""

from __future__ import annotations

from fastapi import APIRouter

from app.core.config import settings

router = APIRouter(prefix="/api/v1", tags=["health"])


@router.get("/healthz")
async def healthz() -> dict[str, str]:
    """Liveness probe simples — sem dependências externas (DB, Redis)."""
    return {"status": "ok", "version": settings.api_version}
