"""
LogWatcher — ingere logs do Unbound (via /var/log/syslog) em DuckDB.

Substitui `scripts/log_ingester.php` da v1, mas durante a transição roda em
PARALELO (dual-write). PHP continua escrevendo em MariaDB; este worker escreve
em DuckDB. Quando paridade for confirmada, log_ingester.php é desativado.

Funcionamento:
  1. Tail-follow `/var/log/syslog` em thread (open + seek end + readline loop).
   Detecta rotação por inode (logrotate substitui o arquivo).
  2. Parseia linhas Unbound `info: ... DOMAIN. QTYPE IN [STATUS ...]`.
  3. Classifica em 'blocked' (NXDOMAIN ou contém "0.0.0.0") ou 'resolved'
     (NOERROR). Linhas de query (sem status) e SERVFAIL/REFUSED são ignoradas
     — mesma política do PHP atual.
  4. Bufferiza em asyncio.Queue (até 10K).
  5. Flush periódico (5s) em batch via `db_append("query_logs", df)` — pandas
     DataFrame + INSERT FROM SELECT (sequence id default preservado).

Permissões: o serviço roda como `www-data`, que precisa estar no grupo `adm`
para ler `/var/log/syslog` (executar `usermod -aG adm www-data` no host).
"""

from __future__ import annotations

import asyncio
import re
import time
from pathlib import Path

import pandas as pd
import structlog

from app.core.metrics import queries_ingested, worker_errors, worker_queue_size
from app.repositories.duckdb.connection import db_append
from app.services.blocked_matcher import BlockedMatcher, get_default_matcher

log = structlog.get_logger(__name__)

FLUSH_INTERVAL = 5  # segundos entre flushes para DuckDB
BUFFER_MAXSIZE = 10_000

# Match: info: IP DOMAIN. QTYPE IN [STATUS [elapsed cached ttl]]
# Anchored em "info:" — funciona com prefixo syslog ou journalctl.
_QUERY_RE = re.compile(
    r"info:\s+"
    r"(?P<ip>[\da-fA-F:.]+)\s+"
    r"(?P<domain>\S+?)\.\s+"
    r"(?P<qtype>[A-Z0-9]+)\s+"
    r"IN"
    r"(?:\s+(?P<status>[A-Z]+))?"
)

# Tipo da entry no buffer: tupla na ordem das colunas do INSERT (id é DEFAULT).
LogEntry = tuple[int, str, str, str, str]
# (timestamp, client_ip, domain, query_type, action)


def _classify(line: str, status: str | None, domain: str, matcher: BlockedMatcher) -> str | None:
    """
    Retorna `blocked` | `nxdomain_upstream` | `resolved` | None.

    Distinção crítica (v2.26.1+): NXDOMAIN só é `blocked` se o domain (ou
    sufixo) está nas local-zones do Unbound. NXDOMAIN sem match é
    `nxdomain_upstream` — domínio que o upstream disse que não existe,
    sem envolver bloqueio nosso. Antes tudo virava `blocked`.

    `0.0.0.0` na linha também é `blocked` (vem de local-data nossa).
    """
    if not status:
        return None  # query sem reply — ignorar (igual ao PHP)
    if "0.0.0.0" in line:
        return "blocked"
    if status == "NXDOMAIN":
        return "blocked" if matcher.matches(domain) else "nxdomain_upstream"
    if status == "NOERROR":
        return "resolved"
    return None  # SERVFAIL, REFUSED, etc. — ignorar


def _parse_line(line: str, now: int, matcher: BlockedMatcher) -> LogEntry | None:
    if "info:" not in line:
        return None
    m = _QUERY_RE.search(line)
    if not m:
        return None
    domain = m.group("domain").lower()
    action = _classify(line, m.group("status"), domain, matcher)
    if action is None:
        return None
    return (now, m.group("ip"), domain, m.group("qtype"), action)


class LogWatcher:
    def __init__(
        self,
        log_path: str | None = None,
        matcher: BlockedMatcher | None = None,
    ) -> None:
        self._path = Path(log_path or "/var/log/syslog")
        self._queue: asyncio.Queue[LogEntry] = asyncio.Queue(maxsize=BUFFER_MAXSIZE)
        self._running = False
        self._matcher = matcher or get_default_matcher()

    async def start(self) -> None:
        self._running = True
        await asyncio.gather(self._tail_loop(), self._flush_loop())

    async def stop(self) -> None:
        self._running = False

    # ------------------------------------------------------------------ #
    # Internos                                                             #
    # ------------------------------------------------------------------ #

    async def _tail_loop(self) -> None:
        """Roda o tail bloqueante em thread pra não travar o event loop."""
        await asyncio.get_running_loop().run_in_executor(None, self._sync_tail)

    def _sync_tail(self) -> None:
        """tail -F: segue o arquivo, reabre em rotação detectada via inode."""
        fh = None
        last_inode: int | None = None
        try:
            while self._running:
                try:
                    stat = self._path.stat()
                except FileNotFoundError:
                    log.warning("log_watcher.file_missing", path=str(self._path))
                    time.sleep(1)
                    continue

                if fh is None or last_inode != stat.st_ino:
                    if fh:
                        fh.close()
                    fh = self._path.open("r", encoding="utf-8", errors="replace")
                    fh.seek(0, 2)  # vai para o final (igual tail -F)
                    last_inode = stat.st_ino
                    log.info("log_watcher.opened", path=str(self._path), inode=last_inode)

                line = fh.readline()
                if not line:
                    time.sleep(0.1)
                    continue

                if "unbound" not in line:
                    continue
                entry = _parse_line(line, int(time.time()), self._matcher)
                if entry is None:
                    continue
                try:
                    self._queue.put_nowait(entry)
                    queries_ingested.labels(action=entry[4]).inc()
                    worker_queue_size.set(self._queue.qsize())
                except asyncio.QueueFull:
                    log.warning("log_watcher.queue_full")
                    worker_errors.labels(worker="log_watcher").inc()
        finally:
            if fh:
                fh.close()

    async def _flush_loop(self) -> None:
        while self._running:
            await asyncio.sleep(FLUSH_INTERVAL)
            await self._flush_once()
        # Drena fila ao encerrar pra não perder buffer
        await self._flush_once()

    async def _flush_once(self) -> None:
        batch: list[LogEntry] = []
        while not self._queue.empty():
            try:
                batch.append(self._queue.get_nowait())
            except asyncio.QueueEmpty:
                break
        if not batch:
            return
        df = pd.DataFrame(
            batch,
            columns=["timestamp", "client_ip", "domain", "query_type", "action"],
        )
        try:
            await db_append("query_logs", df)
            log.info("log_watcher.flushed", count=len(batch))
            worker_queue_size.set(self._queue.qsize())
        except Exception as exc:  # noqa: BLE001
            log.error("log_watcher.flush_failed", error=str(exc), count=len(batch))
            worker_errors.labels(worker="log_watcher").inc()
