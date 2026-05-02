"""
JsonExporter — exporta snapshots do DuckDB como arquivos JSON locais.

Executa a cada 30 segundos e grava em ${export_path}/:
  - alerts.json       → alertas não lidos (lidos pelo AlertManager PHP)
  - stats_live.json   → métricas ao vivo 24h (lidas por health/history/threats)
  - logs_blocked.json → últimos 500 logs bloqueados (lidos por threats_data)
  - query_logs.json   → últimos 100 logs de qualquer ação (lidos por history)
  - metadata.json     → timestamp da última exportação bem-sucedida

O PHP v1 consome esses arquivos diretamente do filesystem, sem precisar que
a API v2 esteja respondendo via HTTP.
"""

from __future__ import annotations

import asyncio
import json
import time
from pathlib import Path

import structlog

from app.core.config import settings
from app.repositories.duckdb.connection import db_fetchall, db_fetchone

log = structlog.get_logger(__name__)

EXPORT_INTERVAL = 30  # segundos


class JsonExporter:
    def __init__(self) -> None:
        self._export_dir = Path(settings.export_path)
        self._running = False

    # ------------------------------------------------------------------
    # Ciclo principal
    # ------------------------------------------------------------------

    async def start(self) -> None:
        self._export_dir.mkdir(parents=True, exist_ok=True)
        self._running = True
        while self._running:
            await self._export_all()
            await asyncio.sleep(EXPORT_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    # ------------------------------------------------------------------
    # Orquestração
    # ------------------------------------------------------------------

    async def _export_all(self) -> None:
        try:
            await asyncio.gather(
                self._export_alerts(),
                self._export_stats_live(),
                self._export_logs_blocked(),
                self._export_query_logs(),
                return_exceptions=True,
            )
            self._write_atomic("metadata.json", {"exported_at": int(time.time())})
        except Exception as exc:  # noqa: BLE001
            log.error("json_exporter.error", error=str(exc))

    # ------------------------------------------------------------------
    # Exportações individuais
    # ------------------------------------------------------------------

    async def _export_alerts(self) -> None:
        rows = await db_fetchall(
            "SELECT id, type, message, severity, is_read, created_at "
            "FROM alerts WHERE is_read = false ORDER BY created_at DESC LIMIT 50"
        )
        unread = [
            {
                "id": r["id"],
                "type": r["type"],
                "message": r["message"],
                "severity": r["severity"],
                "is_read": bool(r["is_read"]),
                "created_at": str(r["created_at"]),
            }
            for r in rows
        ]
        count_row = await db_fetchone("SELECT COUNT(*) AS n FROM alerts WHERE is_read = false")
        self._write_atomic(
            "alerts.json",
            {"unread_count": int(count_row["n"]) if count_row else 0, "alerts": unread},
        )

    async def _export_stats_live(self) -> None:
        since = int(time.time()) - 86400  # 24h

        totals = await db_fetchone(
            """
            SELECT
                COUNT(*)                                        AS total,
                COUNT(*) FILTER (WHERE action = 'blocked')     AS blocked,
                COUNT(*) FILTER (WHERE action = 'resolved')    AS resolved,
                COUNT(*) FILTER (WHERE action = 'cached')      AS cache_hits
            FROM query_logs WHERE timestamp >= ?
            """,
            [since],
        )

        top_domains = await db_fetchall(
            """
            SELECT domain, COUNT(*) AS hits
            FROM query_logs
            WHERE timestamp >= ? AND action != 'blocked'
            GROUP BY domain ORDER BY hits DESC LIMIT 10
            """,
            [since],
        )

        top_clients = await db_fetchall(
            """
            SELECT client_ip, COUNT(*) AS hits
            FROM query_logs WHERE timestamp >= ?
            GROUP BY client_ip ORDER BY hits DESC LIMIT 10
            """,
            [since],
        )

        t = totals or {}
        self._write_atomic(
            "stats_live.json",
            {
                "window_hours": 24,
                "total": int(t.get("total", 0)),
                "blocked": int(t.get("blocked", 0)),
                "resolved": int(t.get("resolved", 0)),
                "cache_hits": int(t.get("cache_hits", 0)),
                "top_domains": [
                    {"domain": r["domain"], "hits": int(r["hits"])} for r in top_domains
                ],
                "top_clients": [
                    {"client_ip": r["client_ip"], "hits": int(r["hits"])} for r in top_clients
                ],
            },
        )

    async def _export_logs_blocked(self) -> None:
        rows = await db_fetchall(
            "SELECT timestamp, client_ip, domain, query_type, action "
            "FROM query_logs WHERE action = 'blocked' ORDER BY timestamp DESC LIMIT 500"
        )
        self._write_atomic(
            "logs_blocked.json",
            {
                "count": len(rows),
                "logs": [
                    {
                        "timestamp": r["timestamp"],
                        "client_ip": r["client_ip"],
                        "domain": r["domain"],
                        "query_type": r["query_type"],
                        "action": r["action"],
                    }
                    for r in rows
                ],
            },
        )

    async def _export_query_logs(self) -> None:
        rows = await db_fetchall(
            "SELECT timestamp, client_ip, domain, query_type, action "
            "FROM query_logs ORDER BY timestamp DESC LIMIT 100"
        )
        self._write_atomic(
            "query_logs.json",
            {
                "count": len(rows),
                "logs": [
                    {
                        "timestamp": r["timestamp"],
                        "client_ip": r["client_ip"],
                        "domain": r["domain"],
                        "query_type": r["query_type"],
                        "action": r["action"],
                    }
                    for r in rows
                ],
            },
        )

    # ------------------------------------------------------------------
    # Escrita atômica (tmp → rename) — evita leitura de arquivo parcial
    # ------------------------------------------------------------------

    def _write_atomic(self, filename: str, data: dict) -> None:
        target = self._export_dir / filename
        tmp = self._export_dir / (filename + ".tmp")
        tmp.write_text(json.dumps(data, default=str), encoding="utf-8")
        tmp.rename(target)
        log.debug("json_exporter.wrote", file=filename)
