"""Repository de alerts."""

from __future__ import annotations

from app.repositories.duckdb.connection import db_execute, db_fetchall, db_fetchone


async def list_history(limit: int = 100, viewer_org_id: int | None = None) -> list[dict]:
    """
    Lista alerts ordenados por started_at DESC + duration em segundos.
    Espelho de AlertManager::getHistory() (PHP).

    `viewer_org_id`: ver doc em `list_filtered`. None = vê tudo.
    """
    if viewer_org_id is None:
        return await db_fetchall(
            """
            SELECT id, type, severity, message, started_at, resolved_at, is_dismissed, org_id,
                   EXTRACT(EPOCH FROM (COALESCE(resolved_at, NOW()) - started_at))::BIGINT
                       AS duration_secs
            FROM alerts
            ORDER BY started_at DESC
            LIMIT ?
            """,
            [limit],
        )
    return await db_fetchall(
        """
        SELECT id, type, severity, message, started_at, resolved_at, is_dismissed, org_id,
               EXTRACT(EPOCH FROM (COALESCE(resolved_at, NOW()) - started_at))::BIGINT
                   AS duration_secs
        FROM alerts
        WHERE org_id IS NULL OR org_id = ?
        ORDER BY started_at DESC
        LIMIT ?
        """,
        [int(viewer_org_id), limit],
    )


async def count_active() -> int:
    row = await db_fetchone("SELECT COUNT(*) AS n FROM alerts WHERE resolved_at IS NULL")
    return int(row["n"]) if row else 0


async def resolve_by_id(alert_id: int) -> bool:
    """Marca alerta específico como resolvido (se ainda ativo). Retorna se mudou."""
    row = await db_fetchone(
        "SELECT id, type FROM alerts WHERE id = ? AND resolved_at IS NULL",
        [alert_id],
    )
    if not row:
        return False
    await db_execute(
        "UPDATE alerts SET resolved_at = NOW() WHERE id = ? AND resolved_at IS NULL",
        [alert_id],
    )
    from app.services import alerts_broker
    alerts_broker.publish({"event": "resolved", "id": int(row["id"]), "type": str(row["type"])})
    return True


async def clear_resolved() -> int:
    """DELETE alertas já resolvidos. Retorna quantos foram apagados."""
    row = await db_fetchone("SELECT COUNT(*) AS n FROM alerts WHERE resolved_at IS NOT NULL")
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute("DELETE FROM alerts WHERE resolved_at IS NOT NULL", [])
    return n


async def dismiss_by_id(alert_id: int) -> bool:
    """Marca alerta como dismissed (some do bell sem resolver). Retorna se mudou."""
    row = await db_fetchone(
        "SELECT id, type FROM alerts WHERE id = ? AND is_dismissed = false",
        [alert_id],
    )
    if not row:
        return False
    await db_execute(
        "UPDATE alerts SET is_dismissed = true WHERE id = ?",
        [alert_id],
    )
    from app.services import alerts_broker
    alerts_broker.publish({"event": "dismissed", "id": int(row["id"]), "type": str(row["type"])})
    return True


async def dismiss_all_active() -> int:
    """Dismiss em massa de todos alerts não-dismissed (resolvidos ou não)."""
    row = await db_fetchone("SELECT COUNT(*) AS n FROM alerts WHERE is_dismissed = false")
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute("UPDATE alerts SET is_dismissed = true WHERE is_dismissed = false", [])
        from app.services import alerts_broker
        alerts_broker.publish({"event": "dismissed_all", "count": n})
    return n


async def list_filtered(
    *,
    severity: str | None = None,
    type_prefix: str | None = None,
    resolved: bool | None = None,
    dismissed: bool | None = None,
    limit: int = 100,
    offset: int = 0,
    viewer_org_id: int | None = None,
) -> dict:
    """Feed completo com filtros opcionais — usado pela página /notifications.php.

    Retorna `{items, total}` pra paginação.

    `viewer_org_id`:
    - None → admin global, vê todos os alertas.
    - int  → vê alertas globais (org_id IS NULL) + da própria org.
    """
    where = ["1=1"]
    params: list = []
    if severity:
        where.append("severity = ?")
        params.append(severity)
    if type_prefix:
        where.append("type LIKE ?")
        params.append(f"{type_prefix}%")
    if resolved is True:
        where.append("resolved_at IS NOT NULL")
    elif resolved is False:
        where.append("resolved_at IS NULL")
    if dismissed is True:
        where.append("is_dismissed = true")
    elif dismissed is False:
        where.append("is_dismissed = false")
    if viewer_org_id is not None:
        where.append("(org_id IS NULL OR org_id = ?)")
        params.append(int(viewer_org_id))
    where_sql = " AND ".join(where)

    total_row = await db_fetchone(f"SELECT COUNT(*) AS n FROM alerts WHERE {where_sql}", params)
    total = int(total_row["n"]) if total_row else 0

    rows = await db_fetchall(
        f"""
        SELECT id, type, severity, message, started_at, resolved_at, is_dismissed, org_id,
               EXTRACT(EPOCH FROM (COALESCE(resolved_at, NOW()) - started_at))::BIGINT AS duration_secs
        FROM alerts
        WHERE {where_sql}
        ORDER BY started_at DESC
        LIMIT ? OFFSET ?
        """,
        params + [int(limit), int(offset)],
    )
    return {"items": rows, "total": total}


async def prune_old(days: int) -> int:
    """DELETE alerts que estão (resolved OR dismissed) E started_at antigo > N dias.

    Mantém alerts ativos não-dismissed pra sempre. Usado pelo
    NotificationPruner worker.
    """
    row = await db_fetchone(
        """
        SELECT COUNT(*) AS n FROM alerts
        WHERE started_at < NOW() - (INTERVAL '1 day' * ?)
          AND (resolved_at IS NOT NULL OR is_dismissed = true)
        """,
        [int(days)],
    )
    n = int(row["n"]) if row else 0
    if n > 0:
        await db_execute(
            """
            DELETE FROM alerts
            WHERE started_at < NOW() - (INTERVAL '1 day' * ?)
              AND (resolved_at IS NOT NULL OR is_dismissed = true)
            """,
            [int(days)],
        )
    return n
