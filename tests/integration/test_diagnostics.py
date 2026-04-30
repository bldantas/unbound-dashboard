"""Testes de integração para DiagnosticsService."""

from __future__ import annotations

from unittest.mock import AsyncMock, patch

import pytest

from app.services.diagnostics_service import DiagnosticsService


@pytest.fixture
def svc() -> DiagnosticsService:
    return DiagnosticsService()


@pytest.mark.asyncio
async def test_ping_success(svc: DiagnosticsService) -> None:
    mock_result = {
        "host": "8.8.8.8",
        "success": True,
        "output": "PING 8.8.8.8 ... 1 packets received",
    }
    with patch.object(svc, "ping", new=AsyncMock(return_value=mock_result)):
        result = await svc.ping("8.8.8.8")
    assert result["success"] is True


@pytest.mark.asyncio
async def test_dns_resolve_success(svc: DiagnosticsService) -> None:
    mock_result = {
        "domain": "example.com",
        "server": "127.0.0.1",
        "success": True,
        "output": "example.com. 3600 IN A 93.184.216.34",
    }
    with patch.object(svc, "dns_resolve", new=AsyncMock(return_value=mock_result)):
        result = await svc.dns_resolve("example.com")
    assert result["success"] is True


@pytest.mark.asyncio
async def test_run_all_returns_dict(svc: DiagnosticsService) -> None:
    with patch.object(
        svc,
        "run_all",
        new=AsyncMock(
            return_value={
                "internet": {"success": True},
                "dns_local": {"success": True},
                "ping_gateway": {"success": True},
            }
        ),
    ):
        result = await svc.run_all()
    assert "internet" in result
