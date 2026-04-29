"""
DiagnosticsService — testes de conectividade e DNS.
Usa asyncio.create_subprocess_exec via shell.py (allowlist).
"""

from __future__ import annotations

import asyncio
import time
from typing import Any

import structlog

from app.infrastructure.shell import run, CommandError, run_ok

log = structlog.get_logger(__name__)


class DiagnosticsService:
    async def run_all(self) -> dict[str, Any]:
        results = await asyncio.gather(
            self.ping("8.8.8.8"),
            self.ping("1.1.1.1"),
            self.dns_resolve("example.com"),
            self.dns_resolve("google.com"),
            self.check_internet(),
            return_exceptions=True,
        )
        labels = [
            "ping_8_8_8_8",
            "ping_1_1_1_1",
            "dns_example_com",
            "dns_google_com",
            "internet",
        ]
        return {
            label: (r if not isinstance(r, Exception) else {"ok": False, "error": str(r)})
            for label, r in zip(labels, results)
        }

    async def ping(self, host: str, count: int = 4) -> dict[str, Any]:
        binary = "/bin/ping"
        import os
        for candidate in ("/bin/ping", "/usr/bin/ping"):
            if os.path.exists(candidate):
                binary = candidate
                break

        t0 = time.monotonic()
        try:
            output = await run(binary, "-c", str(count), "-W", "2", host, timeout=15.0)
            elapsed = (time.monotonic() - t0) * 1000

            # Parse avg RTT da última linha: "rtt min/avg/max/mdev = 1.2/2.3/3.4/0.5 ms"
            avg_rtt = None
            for line in output.splitlines():
                if "rtt" in line and "avg" in line:
                    parts = line.split("=")[-1].strip().split("/")
                    if len(parts) >= 2:
                        avg_rtt = float(parts[1])

            return {
                "ok": True,
                "host": host,
                "avg_rtt_ms": avg_rtt,
                "elapsed_ms": round(elapsed, 1),
            }
        except (CommandError, TimeoutError) as e:
            return {"ok": False, "host": host, "error": str(e)}

    async def dns_resolve(self, domain: str, server: str = "127.0.0.1") -> dict[str, Any]:
        """Resolve um domínio usando dig via Unbound local."""
        import os
        binary = "/usr/bin/dig"
        if not os.path.exists(binary):
            return {"ok": False, "domain": domain, "error": "dig não encontrado"}

        t0 = time.monotonic()
        try:
            output = await run(binary, f"@{server}", domain, "+short", "+time=3", timeout=10.0)
            elapsed_ms = round((time.monotonic() - t0) * 1000, 1)
            addresses = [line.strip() for line in output.splitlines() if line.strip()]
            return {
                "ok": bool(addresses),
                "domain": domain,
                "server": server,
                "addresses": addresses,
                "elapsed_ms": elapsed_ms,
            }
        except (CommandError, TimeoutError) as e:
            return {"ok": False, "domain": domain, "server": server, "error": str(e)}

    async def check_internet(self) -> dict[str, Any]:
        """Verifica conectividade à internet resolvendo um domínio público."""
        result = await self.dns_resolve("cloudflare.com", server="1.1.1.1")
        return {"ok": result["ok"], "method": "dns_external", "detail": result}
