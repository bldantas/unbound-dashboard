"""doh_inbound_service: cert info parsing + self-signed generation."""

from __future__ import annotations

import os
from datetime import datetime, timedelta, timezone
from pathlib import Path

import pytest
from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import NameOID


@pytest.fixture(scope="session", autouse=True)
def _set_env() -> None:
    os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef")


def _make_cert(cn: str, valid_days: int = 365, san_list: list[str] | None = None) -> bytes:
    """Gera cert PEM válido pra usar nos tests sem tocar no FS real."""
    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    subject = issuer = x509.Name([
        x509.NameAttribute(NameOID.COMMON_NAME, cn),
    ])
    now = datetime.now(timezone.utc)
    builder = (
        x509.CertificateBuilder()
        .subject_name(subject)
        .issuer_name(issuer)
        .public_key(key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now)
        .not_valid_after(now + timedelta(days=valid_days))
    )
    if san_list:
        builder = builder.add_extension(
            x509.SubjectAlternativeName([x509.DNSName(s) for s in san_list]),
            critical=False,
        )
    cert = builder.sign(key, hashes.SHA256())
    return cert.public_bytes(serialization.Encoding.PEM)


def test_read_cert_info_absent(tmp_path):
    from app.services.doh_inbound_service import _read_cert_info

    info = _read_cert_info(tmp_path / "nonexistent.crt")
    assert info == {"present": False, "path": str(tmp_path / "nonexistent.crt")}


def test_read_cert_info_self_signed_valid(tmp_path):
    from app.services.doh_inbound_service import _read_cert_info

    crt = tmp_path / "dashboard.crt"
    crt.write_bytes(_make_cert("dns.test.example", valid_days=365, san_list=["dns.test.example", "alt.test"]))

    info = _read_cert_info(crt)
    assert info["present"] is True
    assert "CN=dns.test.example" in info["subject"]
    assert info["self_signed"] is True
    assert info["expired"] is False
    assert info["expiring_soon"] is False
    assert info["days_left"] > 360
    assert set(info["san"]) == {"dns.test.example", "alt.test"}
    assert info["fingerprint_sha256"].count(":") == 31  # 32 bytes = 31 colons


def test_read_cert_info_expiring_soon(tmp_path):
    from app.services.doh_inbound_service import _read_cert_info

    crt = tmp_path / "dashboard.crt"
    crt.write_bytes(_make_cert("dns.test.example", valid_days=15))

    info = _read_cert_info(crt)
    assert info["expired"] is False
    assert info["expiring_soon"] is True
    assert 0 <= info["days_left"] <= 15


def test_read_cert_info_parse_error_on_garbage(tmp_path):
    from app.services.doh_inbound_service import _read_cert_info

    crt = tmp_path / "dashboard.crt"
    crt.write_bytes(b"NOT A REAL PEM")

    info = _read_cert_info(crt)
    assert info["present"] is True
    assert "parse_error" in info


def test_parse_general_conf_extracts_ports_and_paths(tmp_path, monkeypatch):
    from app.services import doh_inbound_service as mod

    conf = tmp_path / "general.conf"
    conf.write_text(
        '    verbosity: 1\n'
        '    tls-port: 1853\n'
        '    https-port: 9443\n'
        '    tls-service-pem: "/custom/path/cert.pem"\n'
        '    tls-service-key: "/custom/path/key.pem"\n'
        '    # comentário deve ser ignorado\n'
    )
    monkeypatch.setattr(mod, "GENERAL_CONF", conf)
    out = mod._parse_general_conf()
    assert out["tls_port"] == "1853"
    assert out["https_port"] == "9443"
    assert out["tls_cert_path"] == "/custom/path/cert.pem"
    assert out["tls_key_path"] == "/custom/path/key.pem"


def test_parse_general_conf_falls_back_to_defaults_when_missing(tmp_path, monkeypatch):
    from app.services import doh_inbound_service as mod

    monkeypatch.setattr(mod, "GENERAL_CONF", tmp_path / "absent.conf")
    out = mod._parse_general_conf()
    assert out["tls_port"] == "853"  # default
    assert out["https_port"] == "8443"  # default


async def test_generate_self_signed_rejects_bad_cn():
    from app.services import doh_inbound_service

    out = await doh_inbound_service.generate_self_signed("")
    assert out["ok"] is False

    out = await doh_inbound_service.generate_self_signed("../etc/passwd")
    assert out["ok"] is False
