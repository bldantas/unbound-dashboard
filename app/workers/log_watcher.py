"""
LogWatcher — monitora o log do Unbound em tempo real.

Funcionamento:
1. Abre o arquivo de log e faz seek para o final (tail -f).
2. A cada linha lida, parseia e coloca no buffer (asyncio.Queue).
3. Um flush periódico (a cada FLUSH_INTERVAL segundos) drena o buffer
   e insere em lote no DuckDB via StatsRepository.
4. Publica cada linha no canal Redis "live:logs" para o WebSocket endpoint.

Formato de log do Unbound (syslog):
  Apr 29 10:00:00 host unbound[1234]: [1234:0] info: 192.168.1.1 example.com. A IN
  Apr 29 10:00:00 host unbound[1234]: [1234:0] info: 192.168.1.1 example.com. A IN NOERROR 0.001234 0 31
  Apr 29 10:00:01 host unbound[1234]: [1234:0] info: 192.168.1.2 ads.example.com. A IN BLOCKED
"""

from __future__ import annotations

import asyncio
import json
import re
import time
from pathlib import Path
from typing import Optional

import structlog

from app.core.config import settings
from app.domain.stats import QueryLog
from app.metrics import (
    bulk_insert_duration,
    queries_blocked,
    queries_cached,
    queries_ingested,
    queries_resolved,
    worker_errors,
    worker_queue_size,
)
from app.repositories.duckdb.stats_repo import StatsRepository
from app.repositories.redis.connection import get_redis

log = structlog.get_logger(__name__)

# Unbound verbosity=1 ou 2 linha de query:
# [thread:reqid] info: CLIENT_IP DOMAIN. QTYPE IN [NOERROR|NXDOMAIN|...] elapsed cached blocked
_QUERY_RE = re.compile(
    r"\[\d+:\d+\] (?:info|debug): "
    r"(?P<ip>[\d\.\:a-fA-F]+) "
    r"(?P<domain>[\w\.\-]+)\. "
    r"(?P<qtype>A|AAAA|CNAME|MX|PTR|TXT|NS|SOA|SRV|ANY) IN"
    r"(?:\s+(?P<status>\S+))?"
    r"(?:\s+(?P<elapsed>\S+))?"
    r"(?:\s+(?P<cached>\d+))?"
    r"(?:\s+(?P<ttl>\d+))?"
)

FLUSH_INTERVAL = 5   # segundos entre flushes para DuckDB
BUFFER_MAXSIZE = 10_000


class LogWatcher:
    def __init__(
        self,
        log_path: str | None = None,
        repo: StatsRepository | None = None,
    ) -> None:
        self._path = Path(log_path or settings.unbound_log)
        self._repo = repo or StatsRepository()
        self._queue: asyncio.Queue[QueryLog] = asyncio.Queue(maxsize=BUFFER_MAXSIZE)
        self._running = False

    async def start(self) -> None:
        self._running = True
        await asyncio.gather(
            self._tail_loop(),
            self._flush_loop(),
        )

    async def stop(self) -> None:
        self._running = False

    # ------------------------------------------------------------------ #
    # Internos                                                             #
    # ------------------------------------------------------------------ #

    async def _tail_loop(self) -> None:
        """Lê novas linhas do arquivo continuamente (simula tail -f)."""
        loop = asyncio.get_event_loop()
        await loop.run_in_executor(None, self._sync_tail)

    def _sync_tail(self) -> None:
        """Bloqueia a thread do executor fazendo seek no final do arquivo."""
        try:
            with open(self._path, "r", encoding="utf-8", errors="replace") as fh:
                fh.seek(0, 2)  # vai para o final
                while self._running:
                    line = fh.readline()
                    if not line:
                        time.sleep(0.1)
                        continue
                    entry = _parse_line(line)
                    if entry is None:
                        continue
                    queries_ingested.inc()
                    if entry.action == "blocked":
                        queries_blocked.inc()
                    elif entry.action == "cached":
                        queries_cached.inc()
                    else:
                        queries_resolved.inc()
                    try:
                        self._queue.put_nowait(entry)
                        worker_queue_size.set(self._queue.qsize())
                    except asyncio.QueueFull:
                        log.warning("log_watcher.queue_full — linha descartada")
        except FileNotFoundError:
            log.error("log_watcher.file_not_found", path=str(self._path))
        except Exception as exc:  # noqa: BLE001
            log.error("log_watcher.tail_error", error=str(exc))
            worker_errors.labels(worker="log_watcher").inc()

    async def _flush_loop(self) -> None:
        """Drena a fila e insere no DuckDB + publica no Redis."""
        redis = await get_redis()
        while self._running:
            await asyncio.sleep(FLUSH_INTERVAL)
            await self._flush_once(redis)

        # Flush final ao encerrar
        await self._flush_once(redis)

    async def _flush_once(self, redis) -> None:
        batch: list[QueryLog] = []
        while not self._queue.empty():
            try:
                batch.append(self._queue.get_nowait())
            except asyncio.QueueEmpty:
                break

        if not batch:
            return

        try:
            with bulk_insert_duration.time():
                await self._repo.bulk_insert_logs(batch)
            log.debug("log_watcher.flushed", count=len(batch))
            worker_queue_size.set(self._queue.qsize())
        except Exception as exc:  # noqa: BLE001
            log.error("log_watcher.flush_error", error=str(exc))
            worker_errors.labels(worker="log_watcher").inc()

        # Publica as últimas N linhas no Redis para WebSocket consumers
        try:
            pipe = redis.pipeline()
            for entry in batch[-100:]:  # limita para não sobrecarregar
                pipe.publish(
                    "live:logs",
                    json.dumps({
                        "ts": entry.timestamp,
                        "ip": entry.client_ip,
                        "domain": entry.domain,
                        "type": entry.query_type,
                        "action": entry.action,
                    }),
                )
            await pipe.execute()
        except Exception as exc:  # noqa: BLE001
            log.warning("log_watcher.redis_publish_error", error=str(exc))


# ------------------------------------------------------------------ #
# Parser de linha de log                                              #
# ------------------------------------------------------------------ #

def _parse_line(line: str) -> Optional[QueryLog]:
    """Parseia uma linha de log do Unbound e retorna QueryLog ou None."""
    m = _QUERY_RE.search(line)
    if not m:
        return None

    status = (m.group("status") or "").upper()
    cached_flag = m.group("cached")

    if "BLOCKED" in line.upper() or status == "BLOCKED":
        action = "blocked"
    elif cached_flag and cached_flag != "0":
        action = "cached"
    else:
        action = "resolved"

    domain = m.group("domain").rstrip(".")

    return QueryLog(
        timestamp=int(time.time()),
        client_ip=m.group("ip"),
        domain=domain,
        query_type=m.group("qtype"),
        action=action,
    )
