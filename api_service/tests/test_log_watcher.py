"""Testes do parser e classificador do LogWatcher (sem I/O de arquivo)."""

from __future__ import annotations

import os

import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


# ---------------------------------------------------------------------------
# Parser
# ---------------------------------------------------------------------------


@pytest.mark.parametrize(
    "line,expected",
    [
        # Reply NOERROR não-cached (resolved)
        (
            "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 143.0.221.227 example.com. A IN NOERROR 0.001234 0 31",
            ("example.com", "A", "resolved"),
        ),
        # Reply NOERROR cached (também classificado como resolved no v1)
        (
            "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 10.0.0.5 cached.example.com. AAAA IN NOERROR 0.000000 1 60",
            ("cached.example.com", "AAAA", "resolved"),
        ),
        # Reply NXDOMAIN (blocked)
        (
            "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 10.0.0.5 ads.example.com. A IN NXDOMAIN 0.001 0 0",
            ("ads.example.com", "A", "blocked"),
        ),
        # Reply NOERROR mas com 0.0.0.0 na resposta (RPZ block — blocked)
        (
            "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 10.0.0.5 evil.example.com. A IN NOERROR 0.001 0 0 0.0.0.0",
            ("evil.example.com", "A", "blocked"),
        ),
        # Domain com case maiúsculo — deve normalizar
        (
            "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 10.0.0.5 Example.COM. A IN NOERROR 0 0 0",
            ("example.com", "A", "resolved"),
        ),
        # IPv6 client
        (
            "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 2001:db8::1 site.test. A IN NOERROR 0 0 0",
            ("site.test", "A", "resolved"),
        ),
    ],
)
def test_parse_line_classifies_correctly(line, expected) -> None:
    from app.workers.log_watcher import _parse_line

    entry = _parse_line(line, now=1234567890)
    assert entry is not None
    _, _, domain, qtype, action = entry
    assert (domain, qtype, action) == expected


@pytest.mark.parametrize(
    "line",
    [
        # Query sem reply (sem status) — ignorar
        "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 10.0.0.5 example.com. A IN",
        # SERVFAIL — política atual (PHP) ignora
        "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 10.0.0.5 broken.com. A IN SERVFAIL 0 0 0",
        # Linha não relacionada a Unbound
        "mai 04 10:56:07 host kernel: some unrelated message",
        # Linha de stats Unbound (sem padrão de query)
        "mai 04 10:56:07 unbound unbound[123:0]: info: server stats for thread 0",
    ],
)
def test_parse_line_skips_irrelevant(line) -> None:
    from app.workers.log_watcher import _parse_line

    assert _parse_line(line, now=1234567890) is None


# ---------------------------------------------------------------------------
# Classificador isolado
# ---------------------------------------------------------------------------


def test_classify_status_codes() -> None:
    from app.workers.log_watcher import _classify

    assert _classify("...", None) is None
    assert _classify("foo NOERROR bar", "NOERROR") == "resolved"
    assert _classify("foo NXDOMAIN bar", "NXDOMAIN") == "blocked"
    # NOERROR + 0.0.0.0 (RPZ block)
    assert _classify("... NOERROR ... 0.0.0.0", "NOERROR") == "blocked"
    # SERVFAIL e REFUSED são ignorados (None)
    assert _classify("... SERVFAIL", "SERVFAIL") is None
    assert _classify("... REFUSED", "REFUSED") is None
