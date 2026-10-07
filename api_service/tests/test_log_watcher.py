"""Testes do parser e classificador do LogWatcher (sem I/O de arquivo)."""

from __future__ import annotations

import os

import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


class _FakeMatcher:
    """Mock do BlockedMatcher — `matches(domain)` retorna True se está em `self.blocked`."""

    def __init__(self, blocked: set[str] | None = None) -> None:
        self.blocked = blocked or set()

    def matches(self, domain: str) -> bool:  # noqa: D401
        d = domain.lower().rstrip(".")
        if d in self.blocked:
            return True
        idx = d.find(".")
        while idx != -1:
            if d[idx + 1 :] in self.blocked:
                return True
            idx = d.find(".", idx + 1)
        return False


# Matcher que considera TUDO bloqueado — preserva semântica pré-v2.26.1
# pros testes legados de NXDOMAIN.
class _AllBlockedMatcher:
    def matches(self, domain: str) -> bool:  # noqa: D401
        return True


# Matcher que não bloqueia nada — testes onde NXDOMAIN é só upstream.
class _NeverBlockedMatcher:
    def matches(self, domain: str) -> bool:  # noqa: D401
        return False


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
        # Reply NXDOMAIN com matcher dizendo blocked → blocked
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

    entry = _parse_line(line, now=1234567890, matcher=_AllBlockedMatcher())
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

    assert _parse_line(line, now=1234567890, matcher=_AllBlockedMatcher()) is None


# ---------------------------------------------------------------------------
# Classificador isolado
# ---------------------------------------------------------------------------


def test_classify_status_codes() -> None:
    from app.workers.log_watcher import _classify

    m = _AllBlockedMatcher()
    assert _classify("...", None, "x.com", m) is None
    assert _classify("foo NOERROR bar", "NOERROR", "x.com", m) == "resolved"
    assert _classify("foo NXDOMAIN bar", "NXDOMAIN", "x.com", m) == "blocked"
    # NOERROR + 0.0.0.0 (RPZ block) — não depende do matcher
    assert _classify("... NOERROR ... 0.0.0.0", "NOERROR", "x.com", m) == "blocked"
    # SERVFAIL e REFUSED são ignorados (None)
    assert _classify("... SERVFAIL", "SERVFAIL", "x.com", m) is None
    assert _classify("... REFUSED", "REFUSED", "x.com", m) is None


def test_classify_nxdomain_distinguishes_blocked_from_upstream() -> None:
    """
    Regressão pro v2.26.1: NXDOMAIN só é `blocked` se o domain está nas
    local-zones do Unbound. Senão é `nxdomain_upstream` (domínio morto
    upstream — não envolveu bloqueio nosso).
    """
    from app.workers.log_watcher import _classify

    matcher = _FakeMatcher(blocked={"evil.com", "ads.tracker.net"})

    # Domain exato bloqueado → blocked
    assert _classify("foo NXDOMAIN", "NXDOMAIN", "evil.com", matcher) == "blocked"

    # Subdomain de algo bloqueado (match por sufixo) → blocked
    assert _classify("foo NXDOMAIN", "NXDOMAIN", "sub.evil.com", matcher) == "blocked"
    assert _classify("foo NXDOMAIN", "NXDOMAIN", "deep.sub.evil.com", matcher) == "blocked"

    # Domain NÃO bloqueado pelo Unbound → nxdomain_upstream
    assert (
        _classify("foo NXDOMAIN", "NXDOMAIN", "expired-adware.com", matcher) == "nxdomain_upstream"
    )
    assert (
        _classify("foo NXDOMAIN", "NXDOMAIN", "random.example.org", matcher) == "nxdomain_upstream"
    )

    # 0.0.0.0 sempre é blocked (vem de local-data nossa), mesmo sem match
    assert _classify("... 0.0.0.0 ...", "NOERROR", "qualquer.com", matcher) == "blocked"


def test_parse_line_emits_nxdomain_upstream_when_no_match() -> None:
    """Smoke do parser inteiro: NXDOMAIN não-bloqueado vira `nxdomain_upstream`."""
    from app.workers.log_watcher import _parse_line

    line = (
        "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: 10.0.0.5 "
        "morto.adware.io. A IN NXDOMAIN 0.001 0 0"
    )
    entry = _parse_line(line, now=1, matcher=_NeverBlockedMatcher())
    assert entry is not None
    assert entry[4] == "nxdomain_upstream"


def test_tail_follows_in_place_truncation(tmp_path) -> None:
    """logrotate copytruncate / `truncate -s 0` mantêm o inode: o tail precisa
    voltar ao início em vez de ficar parado além do novo EOF."""
    import threading
    import time

    from app.workers.log_watcher import LogWatcher

    log_file = tmp_path / "unbound.log"
    log_file.write_text("linha antiga sem query\n" * 1000)

    watcher = LogWatcher(log_path=str(log_file), matcher=_FakeMatcher())
    watcher._running = True
    t = threading.Thread(target=watcher._sync_tail, daemon=True)
    t.start()
    try:
        time.sleep(0.3)  # abre e posiciona no fim do conteúdo antigo
        log_file.write_text("")  # trunca no lugar (mesmo inode)
        with log_file.open("a") as fh:
            fh.write(
                "mai 04 10:56:07 unbound unbound[123:0]: [123:1] info: "
                "10.0.0.5 novo.example.com. A IN NOERROR 0 0 0\n"
            )
        deadline = time.time() + 3
        while watcher._queue.empty() and time.time() < deadline:
            time.sleep(0.05)
        assert not watcher._queue.empty(), "linha escrita após o truncate não foi lida"
        assert watcher._queue.get_nowait()[2] == "novo.example.com"
    finally:
        watcher._running = False
        t.join(timeout=2)
