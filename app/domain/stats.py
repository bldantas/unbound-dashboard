from __future__ import annotations

from dataclasses import dataclass
from datetime import date, datetime
from typing import Optional


@dataclass
class QueryLog:
    """Registro de consulta DNS (tabela OLAP query_logs)."""
    timestamp: int           # unix epoch
    client_ip: str
    domain: str
    query_type: str          # A, AAAA, CNAME, MX, …
    action: str              # resolved | blocked | cached


@dataclass
class DailyStat:
    """Estatística diária agregada."""
    date: date
    total: int
    blocked: int
    resolved: int
    cache_hits: int

    @property
    def block_rate(self) -> float:
        if self.total == 0:
            return 0.0
        return round(self.blocked / self.total * 100, 2)


@dataclass
class LiveStats:
    """Snapshot de stats em tempo real (últimas N horas)."""
    window_hours: int
    total: int
    blocked: int
    resolved: int
    cache_hits: int
    top_domains: list[dict]
    top_clients: list[dict]

    @property
    def block_rate(self) -> float:
        if self.total == 0:
            return 0.0
        return round(self.blocked / self.total * 100, 2)
