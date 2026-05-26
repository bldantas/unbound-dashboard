"""
Service layer da feature Ameaças — equivalente ao api/threats_data.php v1.

Combina 5 queries DuckDB em paralelo e formata o payload no shape exato
que o frontend PHP atual espera (chaves: totals/top/recent + status wrapper).
"""

from __future__ import annotations

import asyncio
from datetime import datetime

from app.repositories.duckdb import threats_repo


async def get_threats_data(
    limit: int = 10,
    *,
    client_ip: str | None = None,
    domain: str | None = None,
) -> dict:
    daily, blacklist_n, recent, top_clients, top_doms = await asyncio.gather(
        threats_repo.daily_totals(),
        threats_repo.blocklist_count(),
        threats_repo.recent_blocked(limit, client_ip=client_ip, domain=domain),
        threats_repo.top_blocked_clients(10),
        threats_repo.top_blocked_domains_with_blacklist(10),
    )

    threats = daily["blocked"]
    queries = daily["total"]
    ratio = round((threats / queries) * 100, 2) if queries > 0 else 0.0

    # Formatação de timestamp seguindo PHP: TZ do servidor (America/Sao_Paulo
    # configurado em /etc/timezone). datetime.fromtimestamp sem tz usa local.
    formatted_recent = []
    for r in recent:
        ts = int(r["timestamp"]) if r.get("timestamp") else 0
        dt = datetime.fromtimestamp(ts) if ts > 0 else datetime.now()
        formatted_recent.append(
            {
                "time": dt.strftime("%H:%M:%S"),
                "date": dt.strftime("%d/%m/%y"),
                "client_ip": str(r.get("client_ip") or ""),
                "domain": str(r.get("domain") or ""),
                "category": str(r.get("category") or "Geral"),
                "severity": str(r.get("severity") or ""),
                "action": str(r.get("action") or "blocked"),
            }
        )

    return {
        "totals": {
            "blacklist": blacklist_n,
            "threats": threats,
            "queries": queries,
            "ratio": ratio,
        },
        "top": {
            "domains": [{"label": str(d["domain"]), "count": int(d["hits"])} for d in top_doms],
            "clients": [
                {"label": str(c["client_ip"]), "count": int(c["hits"])} for c in top_clients
            ],
        },
        "recent": formatted_recent,
    }
