"""
/api/v1/notifications — feed unificado de eventos (alerts + anomalies).

Retorna o que merece atenção do operador, ordenado por recência:
- Alertas não resolvidos (qualquer severity)
- Anomalias recentes (type LIKE 'anomaly_%' — armazenadas em alerts)
- Bloco de release nova (se update_checker detectou)

Marcar como lido é client-side: o frontend guarda o maior `id` visto em
localStorage e calcula `unread_count` localmente. Manter server stateless
evita migration por user_seen e simplifica.
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability

router = APIRouter(prefix="/api/v1/notifications", tags=["notifications"])


@router.get("/feed")
async def feed(
    _: Annotated[dict, Depends(require_capability("dashboard.read"))],
    limit: int = Query(30, ge=1, le=200),
) -> dict:
    """
    Lista as últimas N notificações. Inclui:
    - Todas alerts active (resolved_at IS NULL), ordenadas por started_at desc.
    - Cap em `limit`.
    """
    from app.repositories.duckdb.connection import db_fetchall

    rows = await db_fetchall(
        """
        SELECT id, type, severity, message, started_at, resolved_at, is_dismissed
        FROM alerts
        WHERE resolved_at IS NULL AND is_dismissed = false
        ORDER BY started_at DESC
        LIMIT ?
        """,
        [int(limit)],
    )
    items = []
    for r in rows:
        t = str(r["type"] or "")
        category = "anomaly" if t.startswith("anomaly_") else "alert"
        # URL de destino default por categoria
        url = "/anomalies.php" if category == "anomaly" else "/alerts.php"
        items.append(
            {
                "id": int(r["id"]),
                "category": category,
                "type": t,
                "severity": str(r["severity"] or "info"),
                "message": str(r.get("message") or ""),
                "started_at": r["started_at"].isoformat() if r.get("started_at") else None,
                "url": url,
            }
        )
    return {"items": items, "count": len(items)}
