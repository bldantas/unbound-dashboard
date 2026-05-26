"""
Endpoints /api/v1/doh-inbound — visibilidade e gestão do cert TLS server-side.

GET  /info     — info atual (portas, paths, cert details, expiry)
POST /gen-cert — gera novo self-signed (admin, body: {common_name, days, restart})
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Body, Depends, HTTPException

from app.core.deps import require_capability
from app.services import doh_inbound_service

router = APIRouter(prefix="/api/v1/doh-inbound", tags=["doh-inbound"])


@router.get("/info")
async def get_info(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await doh_inbound_service.info()


@router.post("/gen-cert")
async def gen_cert(
    _: Annotated[dict, Depends(require_capability("config.write"))],
    body: dict = Body(default={}),
) -> dict:
    cn = str(body.get("common_name", "")).strip()
    if not cn:
        raise HTTPException(status_code=400, detail="common_name é obrigatório")
    days = int(body.get("days", 365))
    restart = bool(body.get("restart", False))
    out = await doh_inbound_service.generate_self_signed(cn, days=days, restart=restart)
    if not out.get("ok"):
        raise HTTPException(status_code=500, detail=out)
    return out
