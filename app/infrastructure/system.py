"""
Wrapper psutil — métricas de sistema (CPU, RAM, Disco, Rede, Processos).
Todas as chamadas são síncronas (psutil não tem async), executadas em thread pool.
"""

from __future__ import annotations

import asyncio
from typing import Any

import psutil


async def cpu_percent(interval: float = 1.0) -> float:
    """Percentual de uso de CPU (bloqueante por `interval` segundos — em executor)."""
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(None, psutil.cpu_percent, interval)


async def memory_info() -> dict[str, Any]:
    loop = asyncio.get_event_loop()
    mem = await loop.run_in_executor(None, psutil.virtual_memory)
    return {
        "total_mb": round(mem.total / 1024 / 1024, 1),
        "used_mb": round(mem.used / 1024 / 1024, 1),
        "available_mb": round(mem.available / 1024 / 1024, 1),
        "percent": mem.percent,
    }


async def disk_info(path: str = "/") -> dict[str, Any]:
    loop = asyncio.get_event_loop()
    disk = await loop.run_in_executor(None, psutil.disk_usage, path)
    return {
        "total_gb": round(disk.total / 1024 ** 3, 2),
        "used_gb": round(disk.used / 1024 ** 3, 2),
        "free_gb": round(disk.free / 1024 ** 3, 2),
        "percent": disk.percent,
    }


async def network_io() -> dict[str, Any]:
    loop = asyncio.get_event_loop()
    net = await loop.run_in_executor(None, psutil.net_io_counters)
    return {
        "bytes_sent": net.bytes_sent,
        "bytes_recv": net.bytes_recv,
        "packets_sent": net.packets_sent,
        "packets_recv": net.packets_recv,
        "errin": net.errin,
        "errout": net.errout,
        "dropin": net.dropin,
        "dropout": net.dropout,
    }


async def network_interfaces() -> list[dict[str, Any]]:
    loop = asyncio.get_event_loop()
    addrs = await loop.run_in_executor(None, psutil.net_if_addrs)
    stats = await loop.run_in_executor(None, psutil.net_if_stats)
    result = []
    for iface, addr_list in addrs.items():
        st = stats.get(iface)
        ipv4 = [a.address for a in addr_list if a.family.name == "AF_INET"]
        ipv6 = [a.address for a in addr_list if a.family.name == "AF_INET6"]
        result.append({
            "name": iface,
            "ipv4": ipv4,
            "ipv6": ipv6,
            "is_up": st.isup if st else False,
            "speed_mbps": st.speed if st else 0,
            "mtu": st.mtu if st else 0,
        })
    return result


async def load_average() -> dict[str, float]:
    loop = asyncio.get_event_loop()
    la = await loop.run_in_executor(None, psutil.getloadavg)
    return {"1min": la[0], "5min": la[1], "15min": la[2]}


async def boot_time() -> float:
    loop = asyncio.get_event_loop()
    return await loop.run_in_executor(None, psutil.boot_time)


async def process_running(name: str) -> bool:
    """Verifica se existe algum processo com o nome dado."""
    loop = asyncio.get_event_loop()

    def _check():
        return any(
            name in proc.name()
            for proc in psutil.process_iter(["name"])
        )

    return await loop.run_in_executor(None, _check)


async def full_snapshot() -> dict[str, Any]:
    """Snapshot completo do sistema — usado pelo endpoint /health."""
    cpu, mem, disk, net_io, net_ifaces, la = await asyncio.gather(
        cpu_percent(interval=0.1),
        memory_info(),
        disk_info("/"),
        network_io(),
        network_interfaces(),
        load_average(),
    )
    return {
        "cpu_percent": cpu,
        "memory": mem,
        "disk": disk,
        "network_io": net_io,
        "network_interfaces": net_ifaces,
        "load_average": la,
    }
