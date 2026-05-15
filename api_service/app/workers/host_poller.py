"""
HostPoller — worker que polleia agents gerenciados periodicamente.

Cada N segundos, chama `managed_hosts.poll_all()` que faz HTTP GET no
`/api/v1/host/status` de cada agent e atualiza o estado no DuckDB.

UI consome `managed_hosts.list_all()` que retorna o último estado
persistido — não precisa esperar HTTP em cada page-load.

Intervalo: 60s (default). Trade-off entre frescor do dado e carga.
Pra 3-10 hosts isso é leve (10 reqs/min no pior caso).
"""

from __future__ import annotations

import asyncio

import structlog

from app.services import managed_hosts

log = structlog.get_logger(__name__)

POLL_INTERVAL_SECONDS = 60
INITIAL_DELAY_SECONDS = 15


class HostPoller:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.sleep(INITIAL_DELAY_SECONDS)

        while self._running:
            try:
                results = await managed_hosts.poll_all()
                ok_count = sum(1 for r in results if r["status"] == "ok")
                if results:
                    log.info(
                        "host_poller.tick",
                        total=len(results),
                        ok=ok_count,
                        failed=len(results) - ok_count,
                    )
            except Exception as exc:  # noqa: BLE001
                log.warning("host_poller.unexpected_error", error=str(exc))

            # Sleep interruptível em chunks de 10s
            slept = 0
            while self._running and slept < POLL_INTERVAL_SECONDS:
                await asyncio.sleep(10)
                slept += 10

    async def stop(self) -> None:
        self._running = False
