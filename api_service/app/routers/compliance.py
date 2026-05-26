"""
/api/v1/compliance — endpoints LGPD/GDPR.

LGPD Report: dump das queries DNS feitas por um IP cliente em janela X.
Útil pra atender solicitação de "quais dados meus você tem?" (Art. 18 LGPD).

Tudo é registrado no admin_audit (category=data_export) — acesso a
dados pessoais sempre fica rastreado.
"""

from __future__ import annotations

from ipaddress import ip_address
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Query, Request
from fastapi.responses import PlainTextResponse, Response

from app.core.deps import require_capability
from app.services import admin_audit_service, pdf_report_service

router = APIRouter(prefix="/api/v1/compliance", tags=["compliance"])


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


def _validate_ip(ip: str) -> str:
    try:
        return str(ip_address(ip))
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=f"client_ip inválido: {exc}") from exc


@router.get("/lgpd-report")
async def lgpd_report(
    request: Request,
    user: Annotated[dict, Depends(require_capability("users.read"))],
    client_ip: str = Query(..., min_length=2, max_length=64),
    hours: int = Query(24, ge=1, le=720),
    limit: int = Query(5000, ge=1, le=50000),
) -> dict:
    """Dump JSON das queries do client_ip nas últimas N horas."""
    ip = _validate_ip(client_ip)
    report = await admin_audit_service.lgpd_report(ip, hours=hours, limit=limit)

    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="compliance.lgpd_report",
        category="data_export",
        target_type="ip",
        target_id=ip,
        details={"hours": hours, "found": report["total"], "truncated": report["truncated"]},
    )
    return report


@router.get("/lgpd-report.csv")
async def lgpd_report_csv(
    request: Request,
    user: Annotated[dict, Depends(require_capability("users.read"))],
    client_ip: str = Query(..., min_length=2, max_length=64),
    hours: int = Query(24, ge=1, le=720),
    limit: int = Query(5000, ge=1, le=50000),
) -> PlainTextResponse:
    """Mesmo que /lgpd-report mas retorna CSV download."""
    ip = _validate_ip(client_ip)
    report = await admin_audit_service.lgpd_report(ip, hours=hours, limit=limit)
    csv_str = admin_audit_service.lgpd_report_csv(report)

    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="compliance.lgpd_report_csv",
        category="data_export",
        target_type="ip",
        target_id=ip,
        details={"hours": hours, "found": report["total"]},
    )

    fname = f"lgpd_report_{ip.replace(':', '_')}_{hours}h.csv"
    return PlainTextResponse(
        csv_str,
        media_type="text/csv; charset=utf-8",
        headers={"Content-Disposition": f'attachment; filename="{fname}"'},
    )


@router.get("/lgpd-report.pdf")
async def lgpd_report_pdf(
    request: Request,
    user: Annotated[dict, Depends(require_capability("users.read"))],
    client_ip: str = Query(..., min_length=2, max_length=64),
    hours: int = Query(24, ge=1, le=720),
    limit: int = Query(5000, ge=1, le=50000),
) -> Response:
    """LGPD report como PDF A4 (reportlab). Pra grandes volumes, prefira CSV."""
    ip = _validate_ip(client_ip)
    report = await admin_audit_service.lgpd_report(ip, hours=hours, limit=limit)
    pdf_bytes = pdf_report_service.lgpd_report_pdf(report)

    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=request.client.host if request.client else None,
        action="compliance.lgpd_report_pdf",
        category="data_export",
        target_type="ip",
        target_id=ip,
        details={"hours": hours, "found": report["total"]},
    )

    fname = f"lgpd_report_{ip.replace(':', '_')}_{hours}h.pdf"
    return Response(
        content=pdf_bytes,
        media_type="application/pdf",
        headers={"Content-Disposition": f'attachment; filename="{fname}"'},
    )
