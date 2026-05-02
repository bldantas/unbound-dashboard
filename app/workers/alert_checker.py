"""
AlertChecker — avalia regras de alerta periodicamente.

Verifica condições como taxa de bloqueio anômala, queda de resolução,
excesso de falhas de login, etc., e cria alertas via AlertRepository.
"""

from __future__ import annotations

import asyncio
from datetime import datetime, timezone

import structlog

from app.domain.alert import AlertCreate, Severity
from app.repositories.duckdb.alert_repo import AlertRepository
from app.repositories.duckdb.connection import db_execute, db_fetchone

log = structlog.get_logger(__name__)

CHECK_INTERVAL = 300  # 5 minutos
QUERY_STALL_WINDOW = 600  # 10 minutos
NO_QUERIES_COOLDOWN_HOURS = 6


class AlertChecker:
    def __init__(self, repo: AlertRepository | None = None) -> None:
        self._repo = repo or AlertRepository()
        self._running = False

    async def start(self) -> None:
        self._running = True
        while self._running:
            await self._run_checks()
            await asyncio.sleep(CHECK_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _run_checks(self) -> None:
        await asyncio.gather(
            self._check_block_rate(),
            self._check_query_volume_drop(),
            return_exceptions=True,
        )

    async def _check_block_rate(self) -> None:
        """Alerta se taxa de bloqueio nas últimas 1h > 80%."""
        since = int(datetime.now(timezone.utc).timestamp()) - 3600
        row = await db_fetchone(
            """
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE action = 'blocked') AS blocked
            FROM query_logs WHERE timestamp >= ?
            """,
            [since],
        )
        if not row or int(row["total"]) < 100:
            return  # volume insuficiente para avaliar

        block_rate = int(row["blocked"]) / int(row["total"])
        if block_rate > 0.80:
            await self._repo.create(
                AlertCreate(
                    type="high_block_rate",
                    message=f"Taxa de bloqueio nas últimas 1h: {block_rate:.0%}",
                    severity=Severity.WARNING,
                )
            )
            log.warning("alert.high_block_rate", rate=f"{block_rate:.0%}")

    async def _check_query_volume_drop(self) -> None:
        """Alerta se o volume de queries caiu para zero nas últimas 10 minutos."""
        since = int(datetime.now(timezone.utc).timestamp()) - QUERY_STALL_WINDOW
        row = await db_fetchone(
            "SELECT COUNT(*) AS total FROM query_logs WHERE timestamp >= ?",
            [since],
        )
        if not row:
            return

        if int(row["total"]) > 0:
            # Auto-resolve alertas de ausência de query quando o tráfego volta.
            await db_execute(
                "UPDATE alerts SET is_read = true WHERE type = 'no_queries' AND is_read = false"
            )
            return

        # Deduplica: só cria alerta se não há um alerta 'no_queries' não lido já ativo
        existing = await db_fetchone(
            "SELECT id FROM alerts WHERE type = 'no_queries' AND is_read = false LIMIT 1",
        )
        if existing:
            return

        # Evita recriar em loop após reconhecimento manual enquanto a condição persiste.
        recent = await db_fetchone(
            "SELECT id FROM alerts WHERE type = 'no_queries' "
            f"AND created_at >= NOW() - INTERVAL {NO_QUERIES_COOLDOWN_HOURS} HOUR LIMIT 1",
        )
        if recent:
            return

        await self._repo.create(
            AlertCreate(
                type="no_queries",
                message="Nenhuma query DNS registrada nos últimos 10 minutos.",
                severity=Severity.CRITICAL,
            )
        )
        log.error("alert.no_queries")
