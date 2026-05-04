"""
Coletas de métricas de sistema — substitui ServerMonitor.php + SecurityMonitor.php +
AppMetricsManager.php (consumidos por scripts/cron_alerts.php).

Tudo em uma camada por enquanto. Se crescer, segregar em módulos.
"""

from __future__ import annotations

import os
import re
import shutil

import psutil

from app.infrastructure import shell

# ---------------------------------------------------------------------------
# CPU / Memória / Swap / Disco / Rede — via psutil (pure Python, sem subprocess)
# ---------------------------------------------------------------------------


def cpu_load1() -> float:
    """Load average de 1 minuto (Linux). 0.0 se indisponível."""
    try:
        return float(os.getloadavg()[0])
    except OSError:
        return 0.0


def memory_percent() -> float:
    return float(psutil.virtual_memory().percent)


def swap_percent() -> float:
    return float(psutil.swap_memory().percent)


def disk_percent_root() -> float:
    """Uso % da partição raiz `/`. Match com PHP `df` em '/'."""
    usage = shutil.disk_usage("/")
    return float(usage.used) / float(usage.total) * 100.0


def network_errors_drops() -> dict[str, int]:
    """Soma de errin/errout/dropin/dropout em todas as interfaces (exceto loopback)."""
    counters = psutil.net_io_counters(pernic=True) or {}
    errors = 0
    drops = 0
    for nic, c in counters.items():
        if nic == "lo":
            continue
        errors += c.errin + c.errout
        drops += c.dropin + c.dropout
    return {"errors": errors, "drops": drops}


# ---------------------------------------------------------------------------
# SSH failed logins (journalctl)
# ---------------------------------------------------------------------------


_FAILED_PASSWORD_LINE = re.compile(r"Failed password", re.IGNORECASE)


async def ssh_failed_logins_today() -> int:
    """
    Conta linhas com 'Failed password' no journal de ssh desde 00:00 do dia atual.
    Requer www-data com permissão de leitura no journal (grupo `systemd-journal`
    ou `adm` em algumas distros) — same as PHP (que precisa sudo NOPASSWD).
    Retorna 0 em qualquer falha.
    """
    try:
        output = await shell.run(
            "/usr/bin/journalctl",
            "-u",
            "ssh",
            "--since",
            "today",
            "--no-pager",
            timeout_s=10.0,
        )
    except (shell.CommandFailed, shell.CommandNotAllowed, TimeoutError):
        return 0
    return sum(1 for line in output.splitlines() if _FAILED_PASSWORD_LINE.search(line))


# ---------------------------------------------------------------------------
# Service health (systemctl is-active)
# ---------------------------------------------------------------------------


async def is_service_active(unit: str) -> bool:
    """`systemctl is-active <unit>` retorna 'active' (exit 0) ou outro estado (exit !=0)."""
    try:
        output = await shell.run("/usr/bin/systemctl", "is-active", unit, timeout_s=5.0)
        return output.strip() == "active"
    except shell.CommandFailed:
        # exit !=0 quando inativo — comportamento esperado, não é erro
        return False
    except (shell.CommandNotAllowed, TimeoutError):
        return False
