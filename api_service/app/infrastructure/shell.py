"""
Executor seguro de subprocessos — allowlist estrita de binários.

Lições aplicadas (audit memory):
  - Nunca shell=True; argumentos passados como sequência (sem injection)
  - Allowlist em frozenset constante (simples, sem env override)
  - Timeout obrigatório; processo morto via SIGKILL se exceder
"""

from __future__ import annotations

import asyncio

# Binários permitidos. Adicionar AQUI se precisar de novo comando.
_ALLOWED: frozenset[str] = frozenset(
    {
        "/usr/sbin/unbound-control",
        "/usr/bin/journalctl",  # SSH failed logins counting
        "/usr/bin/systemctl",  # service health (is-active)
    }
)


class CommandNotAllowed(Exception):
    """Tentativa de executar binário fora da allowlist."""


class CommandFailed(Exception):
    """Comando rodou mas retornou exit code != 0."""


async def run(binary: str, *args: str, timeout_s: float = 10.0) -> str:  # noqa: ASYNC109
    """
    Roda binary com args e retorna stdout decodificado em UTF-8.
    Sempre `shell=False` (cada arg passa direto ao OS).
    `timeout_s` mantido por clareza de API; internamente usa `asyncio.timeout`.
    """
    if binary not in _ALLOWED:
        raise CommandNotAllowed(f"binário não permitido: {binary}")

    proc = await asyncio.create_subprocess_exec(
        binary,
        *args,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    try:
        async with asyncio.timeout(timeout_s):
            stdout, stderr = await proc.communicate()
    except TimeoutError:
        proc.kill()
        await proc.wait()
        raise TimeoutError(f"{binary} excedeu {timeout_s}s") from None

    if proc.returncode != 0:
        msg = stderr.decode(errors="replace").strip() or "(sem stderr)"
        raise CommandFailed(f"{binary} retornou {proc.returncode}: {msg}")

    return stdout.decode(errors="replace")
