"""dns_security_service: builders dos blocks que vão pro forwarders.conf.

Testa funções puras (sem DB): _build_hardening_block, _build_performance_block,
_build_privacy_block, _build_ratelimit_block.
"""

from __future__ import annotations

import os

import pytest


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


def test_build_hardening_block_empty_when_all_false():
    from app.services.dns_security_service import HARDENING_KEYS, _build_hardening_block

    flags = {k: False for k in HARDENING_KEYS}
    assert _build_hardening_block(flags) == ""


def test_build_hardening_block_emits_each_directive():
    from app.services.dns_security_service import HARDENING_KEYS, _build_hardening_block

    flags = {k: True for k in HARDENING_KEYS}
    out = _build_hardening_block(flags)
    assert "server:" in out
    assert "hide-identity: yes" in out
    assert "hide-version: yes" in out
    assert "aggressive-nsec: yes" in out
    assert "use-caps-for-id: yes" in out
    assert "harden-glue: yes" in out
    assert "harden-dnssec-stripped: yes" in out
    assert "harden-below-nxdomain: yes" in out
    assert "harden-referral-path: yes" in out
    assert "harden-algo-downgrade: yes" in out
    assert "deny-any: yes" in out
    assert "client-subnet-always-forward: no" in out
    assert "tls-system-cert: yes" in out


def test_build_hardening_block_partial():
    from app.services.dns_security_service import HARDENING_KEYS, _build_hardening_block

    flags = {k: False for k in HARDENING_KEYS}
    flags["dns_hide_identity"] = True
    flags["dns_deny_any"] = True
    out = _build_hardening_block(flags)
    assert "hide-identity: yes" in out
    assert "deny-any: yes" in out
    assert "hide-version" not in out


def test_build_privacy_block_off():
    from app.services.dns_security_service import _build_privacy_block

    assert _build_privacy_block("no") == ""


def test_build_privacy_block_yes_emits_relaxed():
    from app.services.dns_security_service import _build_privacy_block

    out = _build_privacy_block("yes")
    assert "qname-minimisation: yes" in out
    assert "qname-minimisation-strict: no" in out


def test_build_privacy_block_strict_emits_strict():
    from app.services.dns_security_service import _build_privacy_block

    out = _build_privacy_block("strict")
    assert "qname-minimisation: yes" in out
    assert "qname-minimisation-strict: yes" in out


def test_build_ratelimit_block_off():
    from app.services.dns_security_service import _build_ratelimit_block

    out = _build_ratelimit_block(
        ip_enabled=False, ip_qps=0, ip_factor=10,
        dom_enabled=False, dom_qps=0, dom_factor=10,
    )
    assert out == ""


def test_build_ratelimit_block_ip_only():
    from app.services.dns_security_service import _build_ratelimit_block

    out = _build_ratelimit_block(
        ip_enabled=True, ip_qps=100, ip_factor=10,
        dom_enabled=False, dom_qps=0, dom_factor=10,
    )
    # Match exato de linhas — "ratelimit:" e "ratelimit-factor:" são prefixadas
    # com 4 espaços; "ip-ratelimit:" também tem essa terminação como suffix,
    # então comparamos linha-a-linha.
    lines = [ln.strip() for ln in out.splitlines()]
    assert "ip-ratelimit: 100" in lines
    assert "ip-ratelimit-factor: 10" in lines
    # domain ratelimit não emitido (suas linhas começariam com "ratelimit:" /
    # "ratelimit-factor:" — verificamos que nenhuma linha começa assim)
    assert not any(ln.startswith("ratelimit:") for ln in lines)
    assert not any(ln.startswith("ratelimit-factor:") for ln in lines)


def test_build_performance_block_empty_when_defaults():
    """Defaults → bloco vazio (preserva controle do optimization.conf)."""
    from app.services.dns_security_service import (
        PERFORMANCE_BOOL_KEYS,
        PERFORMANCE_INT_KEYS,
        PERFORMANCE_DEFAULTS,
        _build_performance_block,
    )

    bools = {k: False for k in PERFORMANCE_BOOL_KEYS}
    ints = {k: int(PERFORMANCE_DEFAULTS[k]) for k in PERFORMANCE_INT_KEYS}
    assert _build_performance_block(bools, ints) == ""


def test_build_performance_block_with_prefetch():
    from app.services.dns_security_service import (
        PERFORMANCE_BOOL_KEYS, PERFORMANCE_INT_KEYS, PERFORMANCE_DEFAULTS,
        _build_performance_block,
    )

    bools = {k: False for k in PERFORMANCE_BOOL_KEYS}
    ints = {k: int(PERFORMANCE_DEFAULTS[k]) for k in PERFORMANCE_INT_KEYS}
    bools["unbound_perf_prefetch"] = True
    bools["unbound_perf_prefetch_key"] = True

    out = _build_performance_block(bools, ints)
    assert "server:" in out
    assert "prefetch: yes" in out
    assert "prefetch-key: yes" in out


def test_build_performance_block_serve_expired_with_ttl():
    from app.services.dns_security_service import (
        PERFORMANCE_BOOL_KEYS, PERFORMANCE_INT_KEYS, PERFORMANCE_DEFAULTS,
        _build_performance_block,
    )

    bools = {k: False for k in PERFORMANCE_BOOL_KEYS}
    ints = {k: int(PERFORMANCE_DEFAULTS[k]) for k in PERFORMANCE_INT_KEYS}
    bools["unbound_perf_serve_expired"] = True
    ints["unbound_perf_serve_expired_ttl"] = 3600
    ints["unbound_perf_serve_expired_client_timeout"] = 1000

    out = _build_performance_block(bools, ints)
    assert "serve-expired: yes" in out
    assert "serve-expired-ttl: 3600" in out
    assert "serve-expired-client-timeout: 1000" in out


def test_build_performance_block_cache_size_emit_only_if_different():
    """Default 50m/100m — só emite se != default."""
    from app.services.dns_security_service import (
        PERFORMANCE_BOOL_KEYS, PERFORMANCE_INT_KEYS, PERFORMANCE_DEFAULTS,
        _build_performance_block,
    )

    bools = {k: False for k in PERFORMANCE_BOOL_KEYS}
    ints = {k: int(PERFORMANCE_DEFAULTS[k]) for k in PERFORMANCE_INT_KEYS}
    # Mantém defaults nos sizes → não emite
    out = _build_performance_block(bools, ints)
    assert out == ""

    # Muda só rrset → emite só ele
    ints["unbound_perf_rrset_cache_size_mb"] = 256
    out = _build_performance_block(bools, ints)
    assert "rrset-cache-size: 256m" in out
    assert "msg-cache-size" not in out
