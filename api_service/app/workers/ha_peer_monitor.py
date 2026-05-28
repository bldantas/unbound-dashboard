"""
HAPeerMonitor — polla `/api/v1/health` dos peers HA a cada 30s.

Atualiza `last_check_*` em `ha_peers`. Healthcheck é autenticado quando
o peer foi criado com `keep_raw=true` (token raw cifrado guardado em
`api_token_raw_encrypted` via cipher_service). O `ha_service.check_peer`
decifra e envia `X-Api-Token` + `Authorization: Bearer` em cada probe.

Peers criados sem `keep_raw` continuam com probe anônimo — se o `/health`
do peer exigir auth, o status fica como `unauthorized`. Pra ativar auth
em um peer existente, recriar com a flag (token raw só é exposto 1x na
criação por design).
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
