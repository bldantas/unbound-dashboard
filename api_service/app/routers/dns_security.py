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

from fastapi import APIRouter, Depends, Request

from fastapi.responses import JSONResponse

from app.core.deps import require_capability
from app.services import admin_audit_service, approval_service, dns_security_service

router = APIRouter(prefix="/api/v1/dns-security", tags=["dns-security"])


# Handler registrado pro dispatcher de approvals — executa o apply real
# após aprovação, sem precisar do request HTTP original.
async def _approval_handler_dns_apply(payload: dict) -> dict:
    return await dns_security_service.apply()


approval_service.register_action_handler("dns_security.apply", _approval_handler_dns_apply)


def _coerce_int(v) -> int | None:
    try:
        return int(v) if v is not None else None
    except (TypeError, ValueError):
        return None


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


@router.post("/apply", response_model=None)
async def apply(
    request: Request,
    user: Annotated[dict, Depends(require_capability("config.write"))],
):
    ip = request.client.host if request.client else None
    # Workflow approval — se action está em workflow_approval_actions, registra
    # request e responde 202 sem executar
    try:
        await approval_service.enforce_approval(
            user=user, request_ip=ip,
            action="dns_security.apply",
            description="Aplicar config DNS (forwarders.conf) + restart Unbound",
            payload={},
        )
    except approval_service.ApprovalRequired as exc:
        return JSONResponse(
            {"approval_pending": True, "request_id": exc.request_id,
             "message": "Aguardando aprovação de outro admin em /approvals.php"},
            status_code=202,
        )

    result = await dns_security_service.apply()
    await admin_audit_service.log(
        actor_id=user.get("user_id") or _coerce_int(user.get("sub")),
        actor_username=user.get("username"),
        actor_ip=ip,
        action="dns_security.apply",
        category="config",
        details={"ok": result.get("ok"), "mode": result.get("mode"), "stage": result.get("stage")},
    )
    return result


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


@router.get("/hardening/settings")
async def get_hardening(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await dns_security_service.get_hardening_settings()


@router.put("/hardening/settings")
async def update_hardening(
    body: dict,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    n = await dns_security_service.update_hardening_settings(body)
    return {"updated": n}


@router.get("/performance/settings")
async def get_performance(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    return await dns_security_service.get_performance_settings()


@router.put("/performance/settings")
async def update_performance(
    body: dict,
    _: Annotated[dict, Depends(require_capability("config.write"))],
) -> dict:
    n = await dns_security_service.update_performance_settings(body)
    return {"updated": n}


@router.get("/performance/metrics")
async def performance_metrics(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
) -> dict:
    """Snapshot rico de métricas pra página /performance.php.

    Combina o dashboard summary (já em cache 60s) com counters extras
    relevantes pra tuning: prefetch counter, requestlist avg/max, cache
    memory, hit ratio, P50/P95/P99 (do histograma).
    """
    from app.services import unbound_stats_service
    s = await unbound_stats_service.get_stats()
    return {
        "online": s.get("online", False),
        "qps": s.get("qps", 0),
        "hit_ratio": s.get("hit_ratio", 0),
        "latency_avg": s.get("latency_avg", 0),
        "latency_recursion": s.get("latency_recursion", 0),
        "latency_median": s.get("latency_median", 0),
        "latency_p50": s.get("latency_p50", 0),
        "latency_p95": s.get("latency_p95", 0),
        "latency_p99": s.get("latency_p99", 0),
        "req_list_avg": s.get("req_list_avg", 0),
        "req_list_max": s.get("req_list_max", 0),
        "prefetch": s.get("prefetch", 0),
        "rrset_mem": s.get("rrset_mem", "0 B"),
        "msg_mem": s.get("msg_mem", "0 B"),
        "total_queries": s.get("total_queries", 0),
        "cache_hits": s.get("cache_hits", 0),
        "cache_miss": s.get("cache_miss", 0),
        "uptime": s.get("uptime", 0),
        "uptime_human": s.get("uptime_human", "0m"),
    }
