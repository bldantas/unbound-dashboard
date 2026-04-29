"""
Executor seguro de subprocessos — allowlist estrita, sem shell=True.

Equivalente ao ShellHelper PHP mas:
- Não-bloqueante (asyncio.create_subprocess_exec)
- Allowlist de binários explícita (nenhum outro binário pode ser chamado)
- Timeout forçado com kill automático
"""

from __future__ import annotations

import asyncio
from typing import Sequence

# Allowlist: apenas estes binários podem ser executados pela aplicação
ALLOWED_BINARIES: frozenset[str] = frozenset({
    "/usr/sbin/unbound-control",
    "/usr/sbin/unbound-checkconf",
    "/usr/bin/systemctl",
    "/sbin/ip",
    "/usr/sbin/ip",
    "/usr/bin/ip",
    "/usr/bin/journalctl",
    "/usr/bin/dig",
    "/usr/bin/host",
    "/bin/ping",
    "/usr/bin/ping",
    "/usr/bin/curl",
    "/usr/bin/wget",
    "/bin/cat",
    "/usr/bin/cat",
})


class CommandNotAllowedError(Exception):
    pass


class CommandError(Exception):
    def __init__(self, binary: str, returncode: int, stderr: str) -> None:
        super().__init__(f"{binary} falhou com código {returncode}: {stderr}")
        self.returncode = returncode
        self.stderr = stderr


async def run(
    binary: str,
    *args: str,
    timeout: float = 10.0,
    input_data: bytes | None = None,
) -> str:
    """
    Executa um binário da allowlist de forma assíncrona e segura.
    Retorna stdout decodificado.
    Lança CommandNotAllowedError, CommandError ou TimeoutError.
    """
    if binary not in ALLOWED_BINARIES:
        raise CommandNotAllowedError(f"Binário não permitido: {binary!r}")

    proc = await asyncio.create_subprocess_exec(
        binary,
        *args,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
        stdin=asyncio.subprocess.PIPE if input_data else asyncio.subprocess.DEVNULL,
    )

    try:
        stdout, stderr = await asyncio.wait_for(
            proc.communicate(input=input_data),
            timeout=timeout,
        )
    except asyncio.TimeoutError:
        proc.kill()
        await proc.wait()
        raise TimeoutError(f"{binary} excedeu {timeout}s de timeout")

    if proc.returncode != 0:
        raise CommandError(binary, proc.returncode, stderr.decode(errors="replace").strip())

    return stdout.decode(errors="replace").strip()


async def run_ok(binary: str, *args: str, timeout: float = 10.0) -> bool:
    """
    Executa e retorna True se o código de saída for 0, False caso contrário.
    Não lança exceção em erro de execução.
    """
    try:
        await run(binary, *args, timeout=timeout)
        return True
    except (CommandError, TimeoutError):
        return False
