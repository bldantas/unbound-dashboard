"""cipher_service: encrypt/decrypt, prefix detection, fallback sem master key."""

from __future__ import annotations

import importlib

import pytest
from cryptography.fernet import Fernet


@pytest.fixture()
def _reload_cipher(monkeypatch):
    """Limpa o cache module-level entre testes."""
    import app.services.cipher_service as mod
    mod._cipher = None
    mod._key_loaded = False
    yield mod
    mod._cipher = None
    mod._key_loaded = False


def test_is_available_false_without_key(_reload_cipher, monkeypatch):
    monkeypatch.delenv("SECRETS_MASTER_KEY", raising=False)
    assert _reload_cipher.is_available() is False


def test_is_available_true_with_key(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    assert _reload_cipher.is_available() is True


def test_encrypt_without_key_returns_plaintext(_reload_cipher, monkeypatch):
    monkeypatch.delenv("SECRETS_MASTER_KEY", raising=False)
    out = _reload_cipher.encrypt("hello")
    assert out == "hello"
    assert not _reload_cipher.is_encrypted(out)


def test_encrypt_with_key_adds_prefix(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    out = _reload_cipher.encrypt("secret-value")
    assert out.startswith("enc:v1:")
    assert _reload_cipher.is_encrypted(out)
    assert out != "secret-value"


def test_encrypt_decrypt_roundtrip(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    original = "client-secret-with-special-chars-€-日本"
    enc = _reload_cipher.encrypt(original)
    dec = _reload_cipher.decrypt(enc)
    assert dec == original


def test_decrypt_legacy_plaintext_passthrough(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    # Sem prefix → retorna como-está (compat com legacy)
    assert _reload_cipher.decrypt("plain-old-secret") == "plain-old-secret"


def test_decrypt_empty_returns_empty(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    assert _reload_cipher.decrypt("") == ""
    assert _reload_cipher.decrypt(None) == ""


def test_encrypt_empty_returns_empty(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    assert _reload_cipher.encrypt("") == ""


def test_decrypt_invalid_token_returns_empty(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    out = _reload_cipher.decrypt("enc:v1:not-a-valid-fernet-token")
    assert out == ""


def test_decrypt_without_key_when_value_encrypted(_reload_cipher, monkeypatch):
    """Cifra com chave A; tenta decifrar com chave faltando — retorna ''."""
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    enc = _reload_cipher.encrypt("secret")
    # Reseta cache + remove env
    _reload_cipher._cipher = None
    _reload_cipher._key_loaded = False
    monkeypatch.delenv("SECRETS_MASTER_KEY", raising=False)
    assert _reload_cipher.decrypt(enc) == ""


def test_status_payload(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", Fernet.generate_key().decode())
    s = _reload_cipher.status()
    assert s["available"] is True
    assert s["prefix"] == "enc:v1:"
    assert "Fernet" in s["algorithm"]


def test_invalid_master_key_handled(_reload_cipher, monkeypatch):
    monkeypatch.setenv("SECRETS_MASTER_KEY", "not-a-valid-fernet-key")
    assert _reload_cipher.is_available() is False
    # Fallback: encrypt vira passthrough
    assert _reload_cipher.encrypt("x") == "x"
