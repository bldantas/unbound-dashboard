"""
NetworkService — informações de rede e NTP.
Usa psutil + shell.py para operações que exigem ip/systemctl.
"""

from __future__ import annotations

from typing import Any

from app.infrastructure import system as sys_info
from app.infrastructure.shell import run, CommandError


class NetworkService:
    async def get_interfaces(self) -> list[dict[str, Any]]:
        return await sys_info.network_interfaces()

    async def get_io_stats(self) -> dict[str, Any]:
        return await sys_info.network_io()

    async def get_ntp_status(self) -> dict[str, Any]:
        """Verifica status do NTP via timedatectl (systemd)."""
        import os
        binary = "/usr/bin/timedatectl"
        if not os.path.exists(binary):
            return {"available": False}

        try:
            output = await run(binary, "show", "--property=NTPSynchronized,NTP,TimeZone")
            result: dict[str, Any] = {"available": True}
            for line in output.splitlines():
                if "=" in line:
                    k, _, v = line.partition("=")
                    result[k.lower()] = v
            return result
        except (CommandError, TimeoutError):
            return {"available": False}

    async def get_routing_table(self) -> list[str]:
        """Retorna tabela de roteamento via `ip route show`."""
        import os
        for binary in ("/sbin/ip", "/usr/sbin/ip", "/usr/bin/ip"):
            if os.path.exists(binary):
                try:
                    output = await run(binary, "route", "show")
                    return [l.strip() for l in output.splitlines() if l.strip()]
                except CommandError:
                    return []
        return []
