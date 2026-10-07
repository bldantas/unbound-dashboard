"""
Endpoints para alimentar `api/export.php` v1 — fornecem os dados crus
(query_logs, stats agregadas, settings, blocklist), PHP formata CSV/JSON
e streama pro browser.
"""

from __future__ import annotations

import re
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Query, status

from app.core.deps import require_capability, require_global_admin, resolve_viewer_org_id
from app.repositories.duckdb import (
    settings_repo,
    threats_repo,  # reuse onde possível
)
from app.repositories.duckdb.connection import db_fetchall

router = APIRouter(prefix="/api/v1/exports", tags=["exports"])


@router.get("/query-logs")
async def export_query_logs(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
    since: int = Query(0, ge=0, description="Unix epoch — só retorna rows >= since (0 = todos)"),
) -> list[dict]:
    """Retorna query_logs ordenados DESC. Usado pro CSV de logs DNS."""
    rows = await db_fetchall(
        """
        SELECT timestamp, client_ip, domain, query_type, action
        FROM query_logs
        WHERE timestamp >= ?
        ORDER BY timestamp DESC
        """,
        [since],
    )
    return [
        {
            "timestamp": int(r["timestamp"]),
            "client_ip": str(r["client_ip"]),
            "domain": str(r["domain"]),
            "query_type": str(r["query_type"]),
            "action": str(r["action"]),
        }
        for r in rows
    ]


@router.get("/stats-report")
async def export_stats_report(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> dict:
    """
    Sumário pra o JSON de stats: daily_history (90d) + top_domains_24h +
    top_clients_24h. NÃO inclui current_metrics (PHP lê data/latest_stats.json).
    """
    daily = await db_fetchall(
        """
        SELECT stat_date, total_queries, cache_hits, cache_misses
        FROM daily_stats
        ORDER BY stat_date DESC
        LIMIT 90
        """
    )
    top_domains = await db_fetchall(
        """
        SELECT domain, COUNT(*) AS total, action
        FROM query_logs
        WHERE timestamp > (epoch(now()) - 86400)::INTEGER
        GROUP BY domain, action
        ORDER BY total DESC
        LIMIT 50
        """
    )
    top_clients = await db_fetchall(
        """
        SELECT client_ip, COUNT(*) AS total
        FROM query_logs
        WHERE timestamp > (epoch(now()) - 86400)::INTEGER
        GROUP BY client_ip
        ORDER BY total DESC
        LIMIT 20
        """
    )
    return {
        "daily_history": [
            {
                "stat_date": str(r["stat_date"]),
                "total_queries": int(r["total_queries"] or 0),
                "cache_hits": int(r["cache_hits"] or 0),
                "cache_misses": int(r["cache_misses"] or 0),
            }
            for r in daily
        ],
        "top_domains_24h": [
            {"domain": str(r["domain"]), "total": int(r["total"]), "action": str(r["action"])}
            for r in top_domains
        ],
        "top_clients_24h": [
            {"client_ip": str(r["client_ip"]), "total": int(r["total"])} for r in top_clients
        ],
    }


# Settings com credenciais (SMTP, S3, tokens de peers HA, segredos OIDC).
_SENSITIVE_SETTING_RE = re.compile(r"password|secret|token|private|access_key", re.IGNORECASE)
_MASK = "********"


def _is_internal_setting(key: str) -> bool:
    """Estado interno (ex.: `_oidc_state_<nonce>` do fluxo OIDC) — nunca sai
    no export nem entra pelo bulk."""
    return key.startswith("_")


@router.get("/settings")
async def export_settings(
    payload: Annotated[dict, Depends(require_capability("config.read_sensitive"))],
) -> list[dict]:
    """Settings globais. Admin global (e API token sem escopo) recebe os
    valores reais — o PHP precisa deles (Mailer, backup de config). Os demais
    com `config.read_sensitive` (readonly_admin, admin de org) recebem as
    credenciais mascaradas."""
    full = payload.get("role") == "admin" and await resolve_viewer_org_id(payload) is None
    if payload.get("auth_kind") == "api_token" and payload.get("api_token_capabilities"):
        full = False
    rows = await settings_repo.list_all()
    out = []
    for r in rows:
        key = str(r["setting_key"])
        if _is_internal_setting(key):
            continue
        value = str(r["setting_value"])
        if not full and value and _SENSITIVE_SETTING_RE.search(key):
            value = _MASK
        out.append({"setting_key": key, "setting_value": value})
    return out


@router.post("/settings/bulk", status_code=200)
async def import_settings_bulk(
    _: Annotated[dict, Depends(require_global_admin)],
    entries: list[dict],
) -> dict:
    """Bulk upsert de settings — usado pelo restore de config backup.

    Settings são globais (SMTP, destino de backup S3...): só admin global.
    Chaves internas são recusadas, e um valor mascarado (vindo de um export
    feito por não-admin) não sobrescreve o valor real."""
    bad = [
        str(e.get("setting_key", ""))
        for e in entries
        if _is_internal_setting(str(e.get("setting_key", "")))
    ]
    if bad:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Chaves internas não podem ser importadas: {', '.join(bad[:5])}",
        )
    entries = [e for e in entries if str(e.get("setting_value", "")) != _MASK]
    count = await settings_repo.bulk_upsert(entries)
    return {"upserted": count}


@router.get("/blocklist")
async def export_blocklist(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
) -> list[dict]:
    """Lista todos blocklist_domains pra export CSV."""
    # threats_repo não tem list_all; usa db_fetchall direto
    rows = await db_fetchall(
        "SELECT domain, category, severity FROM blocklist_domains ORDER BY category, domain"
    )
    return [
        {
            "domain": str(r["domain"]),
            "category": str(r["category"] or ""),
            "severity": str(r["severity"] or ""),
        }
        for r in rows
    ]


# Mantém threats_repo importado pra evitar warning de import não usado
_ = threats_repo
