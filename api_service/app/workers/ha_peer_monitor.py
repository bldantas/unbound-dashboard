"""
HAPeerMonitor — polla `/api/v1/health` dos peers HA a cada 30s.

Atualiza `last_check_*` em `ha_peers`. Healthcheck é anônimo (sem
X-Api-Token disponível — token raw existe só no momento da criação).
Se peer exige auth no /health, marca `unauthorized` no status.

Limitação conhecida (TODO futuro): pra healthcheck autenticado, precisa
secrets table que guarda token raw cifrado, ou ENV var por peer.
"""

from __future__ import annotations

import asyncio

import structlog

from app.core.metrics import worker_errors
from app.services import ha_service

log = structlog.get_logger(__name__)

CHECK_INTERVAL = 30


class HAPeerMonitor:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        # Aguarda 60s pra DB estar pronto e migrations aplicadas
        await asyncio.sleep(60)
        while self._running:
            try:
                results = await ha_service.check_all_enabled()
                log.debug("ha_peer_monitor.checked", count=len(results))
            except Exception as exc:  # noqa: BLE001
                log.error("ha_peer_monitor.cycle_failed", error=str(exc))
                worker_errors.labels(worker="ha_peer_monitor").inc()
            await asyncio.sleep(CHECK_INTERVAL)

    async def stop(self) -> None:
        self._running = False
