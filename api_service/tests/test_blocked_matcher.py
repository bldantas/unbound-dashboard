"""Testes do BlockedMatcher — parse do blocked_domains.conf + match por sufixo."""

from __future__ import annotations

import os
import time

import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


def _write_conf(path, body: str) -> None:
    path.write_text(body, encoding="utf-8")


def test_matches_exact_domain(tmp_path) -> None:
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, '''
server:
    local-zone: "evil.com" always_nxdomain
    local-zone: "ads.tracker.net" always_nxdomain
''')
    m = BlockedMatcher(conf_path=conf)
    assert m.matches("evil.com") is True
    assert m.matches("ads.tracker.net") is True
    assert m.matches("safe.com") is False


def test_matches_suffix(tmp_path) -> None:
    """`local-zone: \"evil.com\"` bloqueia também `sub.evil.com`."""
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, 'local-zone: "evil.com" always_nxdomain\n')
    m = BlockedMatcher(conf_path=conf)

    assert m.matches("evil.com") is True
    assert m.matches("a.evil.com") is True
    assert m.matches("deep.sub.evil.com") is True
    # "notevil.com" não deve casar (sufixo precisa ser componente inteiro)
    assert m.matches("notevil.com") is False


def test_case_insensitive(tmp_path) -> None:
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, 'local-zone: "EVIL.COM" always_nxdomain\n')
    m = BlockedMatcher(conf_path=conf)
    assert m.matches("Evil.com") is True
    assert m.matches("EVIL.com") is True


def test_handles_trailing_dot(tmp_path) -> None:
    """`local-zone: \"evil.com.\"` (com ponto) deve ser tratado igual."""
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, 'local-zone: "evil.com." always_nxdomain\n')
    m = BlockedMatcher(conf_path=conf)
    assert m.matches("evil.com") is True
    assert m.matches("evil.com.") is True


def test_empty_or_missing_conf(tmp_path) -> None:
    from app.services.blocked_matcher import BlockedMatcher

    missing = tmp_path / "doesnt_exist.conf"
    m = BlockedMatcher(conf_path=missing)
    # Não explode — só retorna False sempre
    assert m.matches("anything.com") is False
    assert m.size() == 0


def test_ignores_non_local_zone_lines(tmp_path) -> None:
    """O parser só pega `local-zone`. Comentários, `local-data`, etc. ignora."""
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, '''
# Comentário
server:
    local-zone: "real-block.com" always_nxdomain
    local-data: "noise.com IN A 1.2.3.4"
    forward-zone:
        name: "."
        forward-addr: 1.1.1.1
''')
    m = BlockedMatcher(conf_path=conf)
    assert m.matches("real-block.com") is True
    assert m.matches("noise.com") is False


def test_reload_when_file_changes(tmp_path) -> None:
    """Quando mtime muda, reload acontece. TTL 0 → checa sempre."""
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, 'local-zone: "evil.com" always_nxdomain\n')
    m = BlockedMatcher(conf_path=conf, cache_ttl=0)

    assert m.matches("evil.com") is True
    assert m.matches("new-block.com") is False

    # Mexe o arquivo. mtime muda → reload no próximo `matches()`.
    time.sleep(0.01)
    _write_conf(conf, 'local-zone: "new-block.com" always_nxdomain\n')
    os_utime_force = (time.time(), time.time())
    os.utime(conf, os_utime_force)

    assert m.matches("new-block.com") is True
    assert m.matches("evil.com") is False  # sumiu do arquivo


def test_cache_ttl_avoids_reload_during_window(tmp_path) -> None:
    """Com TTL alto e mtime parado, o reload não acontece em chamadas seguidas."""
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, 'local-zone: "evil.com" always_nxdomain\n')
    m = BlockedMatcher(conf_path=conf, cache_ttl=300)

    # Primeira chamada força reload (cache vazio)
    assert m.matches("evil.com") is True
    loaded_at = m._loaded_at  # noqa: SLF001

    # Múltiplas chamadas seguidas: loaded_at não muda dentro do TTL
    for _ in range(5):
        m.matches("foo.com")
    assert m._loaded_at == loaded_at  # noqa: SLF001


def test_force_reload(tmp_path) -> None:
    from app.services.blocked_matcher import BlockedMatcher

    conf = tmp_path / "blocked.conf"
    _write_conf(conf, 'local-zone: "evil.com" always_nxdomain\n')
    m = BlockedMatcher(conf_path=conf, cache_ttl=999_999)  # cache ~"infinito"

    assert m.size() == 1
    _write_conf(conf, '''
local-zone: "a.com" always_nxdomain
local-zone: "b.com" always_nxdomain
''')
    # Sem force_reload, com TTL alto, ele NÃO recarregaria automaticamente.
    # mas o _maybe_reload checa mtime tb — então pode ou não recarregar.
    # Aqui forçamos pra ser determinístico.
    new_size = m.force_reload()
    assert new_size == 2
