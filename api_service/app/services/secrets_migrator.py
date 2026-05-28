"""
secrets_migrator — one-shot que cifra valores legacy plaintext no DB.

Rodado no lifespan startup, depois das migrations e da checagem da master
key. Idempotente: linhas já cifradas (prefixo `enc:v1:`) são puladas. Se a
master key não está configurada, o migrator faz no-op e loga skip.

Tabelas conhecidas:
- `oidc_config.client_secret` (legacy) → `client_secret_encrypted` (V20).
  Se legacy != '' e encrypted vazio: cifra, escreve em encrypted, zera legacy.
- `backup_destinations.secret_key` (single coluna inline). Se valor não
  começa com `enc:v1:`, cifra in-place.
- `ha_peers.api_token_raw_encrypted` (V20). Já é gravado cifrado pelo
  service desde a criação — nada a fazer aqui.

Counters retornados pra log structlog.
"""

from __future__ import annotations

import structlog

from app.repositories.duckdb.connection import db_execute, db_fetchall
from app.services import cipher_service

log = structlog.get_logger(__name__)


async def _migrate_oidc_client_secret() -> int:
    rows = await db_fetchall(
        "SELECT id, client_secret FROM oidc_config "
        "WHERE COALESCE(client_secret, '') != '' "
        "AND COALESCE(client_secret_encrypted, '') = ''"
    )
    n = 0
    for r in rows:
        legacy = r.get("client_secret") or ""
        if not legacy:
            continue
        encrypted = cipher_service.encrypt(legacy)
        if not cipher_service.is_encrypted(encrypted):
            continue
        await db_execute(
            "UPDATE oidc_config SET client_secret_encrypted = ?, client_secret = '' WHERE id = ?",
            [encrypted, r["id"]],
        )
        n += 1
    return n


async def _migrate_backup_destinations_secret_key() -> int:
    rows = await db_fetchall(
        "SELECT id, secret_key FROM backup_destinations "
        "WHERE COALESCE(secret_key, '') != '' "
        "AND secret_key NOT LIKE 'enc:v1:%'"
    )
    n = 0
    for r in rows:
        plaintext = r.get("secret_key") or ""
        if not plaintext:
            continue
        encrypted = cipher_service.encrypt(plaintext)
        if not cipher_service.is_encrypted(encrypted):
            continue
        await db_execute(
            "UPDATE backup_destinations SET secret_key = ? WHERE id = ?",
            [encrypted, r["id"]],
        )
        n += 1
    return n


async def migrate_legacy_secrets() -> dict:
    """
    Entrada única. No-op se master key ausente. Devolve dict com counters.
    Nunca raise — falha silenciosa é melhor que crashar o startup.
    """
    if not cipher_service.is_available():
        log.info("secrets_migrator.skipped_no_master_key")
        return {"oidc": 0, "backup_destinations": 0, "skipped": True}
    counters = {"oidc": 0, "backup_destinations": 0, "skipped": False}
    try:
        counters["oidc"] = await _migrate_oidc_client_secret()
    except Exception as exc:  # noqa: BLE001
        log.error("secrets_migrator.oidc_failed", error=str(exc))
    try:
        counters["backup_destinations"] = await _migrate_backup_destinations_secret_key()
    except Exception as exc:  # noqa: BLE001
        log.error("secrets_migrator.backup_destinations_failed", error=str(exc))
    total = counters["oidc"] + counters["backup_destinations"]
    if total:
        log.info("secrets_migrator.migrated", **counters)
    else:
        log.debug("secrets_migrator.nothing_to_migrate")
    return counters
