"""Testes unitários para app/infrastructure/shell.py."""

from __future__ import annotations

import pytest

from app.infrastructure.shell import (
    ALLOWED_BINARIES,
    CommandNotAllowedError,
    run,
    run_ok,
)


def test_allowed_binaries_is_frozenset() -> None:
    assert isinstance(ALLOWED_BINARIES, frozenset)


def test_run_not_allowed_binary_raises() -> None:
    with pytest.raises(CommandNotAllowedError, match="not-a-binary"):
        import asyncio
        asyncio.run(run("not-a-binary", "--help"))


@pytest.mark.asyncio
async def test_run_echo() -> None:
    """Testa com /bin/echo (deve estar na allowlist)."""
    import os
    binary = "/bin/echo"
    if binary not in ALLOWED_BINARIES and "/usr/bin/echo" not in ALLOWED_BINARIES:
        pytest.skip("/bin/echo não está na allowlist")

    actual_binary = binary if binary in ALLOWED_BINARIES else "/usr/bin/echo"
    result = await run(actual_binary, "hello")
    assert "hello" in result


@pytest.mark.asyncio
async def test_run_ok_returns_bool() -> None:
    """run_ok deve retornar True quando o processo termina com rc=0."""
    import os
    binary = "/bin/true"
    if binary not in ALLOWED_BINARIES:
        pytest.skip("/bin/true não está na allowlist")
    assert await run_ok(binary) is True


@pytest.mark.asyncio
async def test_run_ok_false_on_failure() -> None:
    binary = "/bin/false"
    if binary not in ALLOWED_BINARIES:
        pytest.skip("/bin/false não está na allowlist")
    assert await run_ok(binary) is False
