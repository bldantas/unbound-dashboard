"""Utilitários dos WebSockets."""

from __future__ import annotations

import asyncio
import queue
from typing import Any

_POLL_INTERVAL = 0.2


async def queue_get(q: queue.Queue, wait_seconds: float) -> Any:
    """Lê da fila dos brokers (alimentada por threads) sem ocupar thread.

    Antes cada conexão fazia `run_in_executor(None, q.get, True, 30)`, prendendo
    uma thread do executor padrão por até 30s — o mesmo executor das leituras
    do DuckDB (db_fetchall/db_fetchone). Com ~11 abas abertas o pool esgotava e
    a API inteira travava. Aqui é poll não-bloqueante no event loop.
    Levanta `queue.Empty` se nada chegar em `wait_seconds` segundos.
    """
    loop = asyncio.get_running_loop()
    deadline = loop.time() + wait_seconds
    while True:
        try:
            return q.get_nowait()
        except queue.Empty:
            if loop.time() >= deadline:
                raise
            await asyncio.sleep(_POLL_INTERVAL)
