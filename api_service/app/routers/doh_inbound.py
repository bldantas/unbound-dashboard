"""
Endpoints /api/v1/doh-inbound — visibilidade e gestão do cert TLS server-side.

GET  /info     — info atual (portas, paths, cert details, expiry)
POST /gen-cert — gera novo self-signed (admin, body: {common_name, days, restart})
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Body, Depends, HTTPException, Request
from fastapi.responses import JSONResponse

from app.core.deps import require_capability
from app.services import approval_service, doh_inbound_service

router = APIRouter(prefix="/api/v1/doh-inbound", tags=["doh-inbound"])


async def _approval_handler_gen_cert(payload: dict) -> dict:
    cn = str(payload.get("common_name", "")).strip()
    days = int(payload.get("days", 365))
    restart = bool(payload.get("restart", False))
    if not cn:
        return {"ok": False, "error": "common_name vazio no payload"}
    return await doh_inbound_service.generate_self_signed(cn, days=days, restart=restart)


approval_service.register_action_handler("doh_inbound.gen_cert", _approval_handler_gen_cert)


@router.get("/info")
async def get_info(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await doh_inbound_service.info()


@router.post("/gen-cert", response_model=None)
async def gen_cert(
    user: Annotated[dict, Depends(require_capability("config.write"))],
    request: Request,
    body: dict = Body(default={}),
):
    cn = str(body.get("common_name", "")).strip()
    if not cn:
        raise HTTPException(status_code=400, detail="common_name é obrigatório")
    days = int(body.get("days", 365))
    restart = bool(body.get("restart", False))

    ip = request.client.host if request.client else None
    try:
        await approval_service.enforce_approval(
            user=user, request_ip=ip,
            action="doh_inbound.gen_cert",
            description=f"Gerar self-signed cert CN={cn}, validade {days}d" + (" + restart Unbound" if restart else ""),
            payload={"common_name": cn, "days": days, "restart": restart},
        )
    except approval_service.ApprovalRequired as exc:
        return JSONResponse(
            {"approval_pending": True, "request_id": exc.request_id,
             "message": "Aguardando aprovação de outro admin em /approvals.php"},
            status_code=202,
        )

    out = await doh_inbound_service.generate_self_signed(cn, days=days, restart=restart)
    if not out.get("ok"):
        raise HTTPException(status_code=500, detail=out)
    return out
