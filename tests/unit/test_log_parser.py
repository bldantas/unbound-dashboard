"""Testes unitários do LogWatcher — parser de linhas de log."""
from __future__ import annotations

import pytest

from app.workers.log_watcher import _parse_line


@pytest.mark.parametrize("line,expected_action", [
    # Linha de resolução normal (verbosity=1)
    (
        "Apr 29 10:00:00 host unbound[1234]: [1:0] info: 192.168.1.1 example.com. A IN NOERROR 0.001 0 31",
        "resolved",
    ),
    # Linha de cache hit (3º campo numérico = cached flag != 0)
    (
        "Apr 29 10:00:00 host unbound[1234]: [1:0] info: 192.168.1.1 example.com. A IN NOERROR 0.001 1 31",
        "cached",
    ),
    # Linha bloqueada (contém BLOCKED)
    (
        "Apr 29 10:00:00 host unbound[1234]: [1:0] info: 192.168.1.2 ads.evil.com. A IN BLOCKED",
        "blocked",
    ),
])
def test_parse_line_actions(line: str, expected_action: str) -> None:
    entry = _parse_line(line)
    assert entry is not None, f"Parser retornou None para: {line}"
    assert entry.action == expected_action


def test_parse_line_extracts_fields() -> None:
    line = "Apr 29 10:00:00 host unbound[1234]: [1:0] info: 10.0.0.5 google.com. AAAA IN NOERROR 0.002 0 60"
    entry = _parse_line(line)
    assert entry is not None
    assert entry.client_ip == "10.0.0.5"
    assert entry.domain == "google.com"
    assert entry.query_type == "AAAA"


def test_parse_line_ignores_non_query_lines() -> None:
    lines = [
        "Apr 29 10:00:00 host unbound[1234]: [1:0] info: start of service, unbound 1.17.0.",
        "Apr 29 10:00:00 host kernel: something happened",
        "",
        "Apr 29 10:00:00 host unbound[1234]: [1:0] notice: init module 0: validator",
    ]
    for line in lines:
        assert _parse_line(line) is None, f"Deveria retornar None para: {line!r}"
