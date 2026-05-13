"""Testa o módulo TOTP (geração, URI, verificação)."""

from __future__ import annotations

import pyotp

from app.services import totp_service


def test_generate_secret_is_base32_32_chars():
    s = totp_service.generate_secret()
    assert len(s) == 32
    # Base32 alphabet = A-Z + 2-7
    assert all(c in "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567" for c in s)


def test_provisioning_uri_format():
    uri = totp_service.provisioning_uri("JBSWY3DPEHPK3PXP", "bob")
    assert uri.startswith("otpauth://totp/")
    assert "bob" in uri
    assert "secret=JBSWY3DPEHPK3PXP" in uri
    assert "issuer=" in uri


def test_verify_valid_code():
    secret = totp_service.generate_secret()
    code = pyotp.TOTP(secret).now()
    assert totp_service.verify(secret, code) is True


def test_verify_invalid_code():
    secret = totp_service.generate_secret()
    assert totp_service.verify(secret, "000000") is False


def test_verify_rejects_non_numeric():
    secret = totp_service.generate_secret()
    assert totp_service.verify(secret, "abcdef") is False
    assert totp_service.verify(secret, "12345") is False  # 5 dígitos
    assert totp_service.verify(secret, "1234567") is False  # 7 dígitos


def test_verify_strips_spaces():
    """Apps autenticadores às vezes mostram '123 456' — aceitar isso."""
    secret = totp_service.generate_secret()
    code = pyotp.TOTP(secret).now()
    spaced = code[:3] + " " + code[3:]
    assert totp_service.verify(secret, spaced) is True


def test_verify_empty_inputs():
    assert totp_service.verify("", "123456") is False
    assert totp_service.verify("JBSWY3DPEHPK3PXP", "") is False
