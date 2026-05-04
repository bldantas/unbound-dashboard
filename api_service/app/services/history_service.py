"""Service de histórico — combina top_domains + recent_queries em paralelo."""

from __future__ import annotations

import asyncio

from app.repositories.duckdb import history_repo


async def get_summary(limit: int = 10, top_n: int = 10) -> dict:
    top, recent = await asyncio.gather(
        history_repo.top_domains_24h(top_n),
        history_repo.recent_queries(limit),
    )
    # Normaliza tipos pra bater com o que PDO retornava (ints já como int, etc)
    return {
        "top_domains_24h": [{"domain": str(r["domain"]), "count": int(r["count"])} for r in top],
        "recent_queries": [
            {
                "timestamp": int(r["timestamp"]),
                "client_ip": str(r["client_ip"]),
                "domain": str(r["domain"]),
                "query_type": str(r["query_type"]),
                "action": str(r["action"]),
                "category": str(r["category"]) if r.get("category") else None,
            }
            for r in recent
        ],
    }
