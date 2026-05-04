"""
UnboundCollector — substitui scripts/aggregate_stats.php (cron PHP).

Tick: 60s. A cada ciclo:
  1. Coleta metrics via unbound-control (single ou multicore conforme settings)
  2. Computa payload latest_stats (delegando ao unbound_stats_service)
  3. Escreve atomicamente data/latest_stats.json (consumido pelo StatsManager PHP)
  4. Atualiza src/data/time_series.json (rolling window 60 samples + deltas)

Atomicidade: escreve em .tmp e renomeia. Previne leitura parcial pelo PHP.
"""

from __future__ import annotations

import asyncio
import json
import os
import time
from datetime import datetime
from pathlib import Path

import structlog

from app.core.metrics import worker_errors
from app.infrastructure import unbound
from app.repositories.duckdb import settings_repo
from app.services import unbound_stats_service

log = structlog.get_logger(__name__)

COLLECT_INTERVAL = 60
MAX_TIME_SERIES_SAMPLES = 60

LATEST_STATS_PATH = Path("/var/www/html/unbound-dashboard/data/latest_stats.json")
TIME_SERIES_PATH = Path("/var/www/html/unbound-dashboard/src/data/time_series.json")


def _atomic_write_json(path: Path, data: dict) -> None:
    """Escreve JSON atomicamente: tmp → rename."""
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(json.dumps(data, indent=2 if path.name == "latest_stats.json" else None))
    os.replace(tmp, path)


def _counter_delta(current: int, previous: int) -> int:
    """Lida com reset do counter (current < previous): trata como current."""
    if current < previous:
        return max(0, current)
    return max(0, current - previous)


def _build_time_series_sample(stats: dict[str, float], previous: dict | None) -> dict:
    """Monta um sample do time_series com delta vs sample anterior."""
    now = int(time.time())
    sample: dict = {
        "timestamp": now,
        "label": datetime.fromtimestamp(now).strftime("%H:%M"),
        "total_queries": int(stats.get("total.num.queries", 0)),
        "cache_hits": int(stats.get("total.num.cachehits", 0)),
        "cache_miss": int(stats.get("total.num.cachemiss", 0)),
        "latency_avg": stats.get("total.recursion.time.avg", 0) * 1000,
        "latency_median": stats.get("total.recursion.time.median", 0) * 1000,
        "secure": int(stats.get("num.answer.secure", 0)),
        "bogus": int(stats.get("num.answer.bogus", 0)),
        "queries_tcp": int(stats.get("num.query.tcp", 0)),
        "queries_ip6": int(stats.get("num.query.ipv6", 0)),
        "types": {},
        "types_diff": {},
    }
    for key, value in stats.items():
        if key.startswith("num.query.type."):
            qtype = key[len("num.query.type.") :]
            sample["types"][qtype] = int(value)

    if previous and sample["timestamp"] > int(previous.get("timestamp", 0)):
        time_diff = max(1, sample["timestamp"] - int(previous["timestamp"]))
        delta_total = _counter_delta(sample["total_queries"], int(previous.get("total_queries", 0)))
        sample["queries_per_sec"] = round(delta_total / time_diff, 2)
        sample["hits_diff"] = _counter_delta(
            sample["cache_hits"], int(previous.get("cache_hits", 0))
        )
        sample["miss_diff"] = _counter_delta(
            sample["cache_miss"], int(previous.get("cache_miss", 0))
        )
        sample["secure_diff"] = _counter_delta(sample["secure"], int(previous.get("secure", 0)))
        sample["bogus_diff"] = _counter_delta(sample["bogus"], int(previous.get("bogus", 0)))
        sample["tcp_diff"] = _counter_delta(
            sample["queries_tcp"], int(previous.get("queries_tcp", 0))
        )
        sample["ip6_diff"] = _counter_delta(
            sample["queries_ip6"], int(previous.get("queries_ip6", 0))
        )
        prev_types = previous.get("types", {})
        for qtype, value in sample["types"].items():
            sample["types_diff"][qtype] = _counter_delta(value, int(prev_types.get(qtype, 0)))
    else:
        for key in (
            "queries_per_sec",
            "hits_diff",
            "miss_diff",
            "secure_diff",
            "bogus_diff",
            "tcp_diff",
            "ip6_diff",
        ):
            sample[key] = 0
        for qtype in sample["types"]:
            sample["types_diff"][qtype] = 0

    return sample


def _load_time_series() -> dict:
    if not TIME_SERIES_PATH.exists():
        return {"samples": []}
    try:
        data = json.loads(TIME_SERIES_PATH.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) and "samples" in data else {"samples": []}
    except (OSError, json.JSONDecodeError):
        return {"samples": []}


class UnboundCollector:
    def __init__(self) -> None:
        self._running = False

    async def start(self) -> None:
        self._running = True
        while self._running:
            try:
                await self._collect_once()
            except Exception as exc:  # noqa: BLE001
                log.error("unbound_collector.cycle_failed", error=str(exc))
                worker_errors.labels(worker="unbound_collector").inc()
            await asyncio.sleep(COLLECT_INTERVAL)

    async def stop(self) -> None:
        self._running = False

    async def _collect_once(self) -> None:
        # Coleta raw stats (igual ao service mas precisamos do raw pro time_series)
        multicore_enabled = await settings_repo.get_bool("source_balance_enabled", False)
        instances = (
            await settings_repo.get_int("source_balance_instances", 4) if multicore_enabled else 1
        )
        try:
            raw_stats = await unbound.stats_aggregated(instances)
        except Exception as exc:  # noqa: BLE001
            log.warning("unbound_collector.unbound_unreachable", error=str(exc))
            return

        if not raw_stats:
            return

        # Atualiza latest_stats.json — força refresh do cache do service
        payload = await unbound_stats_service.get_stats(force_refresh=True)
        _atomic_write_json(LATEST_STATS_PATH, payload)

        # Atualiza time_series.json — rolling window
        ts_data = _load_time_series()
        previous = ts_data["samples"][-1] if ts_data["samples"] else None
        sample = _build_time_series_sample(raw_stats, previous)
        ts_data["samples"].append(sample)
        if len(ts_data["samples"]) > MAX_TIME_SERIES_SAMPLES:
            ts_data["samples"] = ts_data["samples"][-MAX_TIME_SERIES_SAMPLES:]
        _atomic_write_json(TIME_SERIES_PATH, ts_data)

        log.info(
            "unbound_collector.collected",
            qps=payload["qps"],
            hit_ratio=payload["hit_ratio"],
            samples=len(ts_data["samples"]),
        )
