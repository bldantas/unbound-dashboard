"""
/api/v1/admin/secrets-store — status do cipher_service.

GET /status — info se SECRETS_MASTER_KEY está configurada, quais
serviços já usam, contagem de secrets cifrados vs legacy.
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends

from app.core.deps import require_admin
from app.repositories.duckdb.connection import db_fetchone
from app.services import cipher_service

router = APIRouter(prefix="/api/v1/admin/secrets-store", tags=["secrets-store"])


@router.get("/status")
async def status(_: Annotated[dict, Depends(require_admin)]) -> dict:
    base = cipher_service.status()

    # Conta o que está cifrado vs legacy em cada tabela
    oidc_row = await db_fetchone(
        """
        SELECT
            CASE WHEN client_secret_encrypted IS NOT NULL AND client_secret_encrypted != ''
                 THEN 1 ELSE 0 END AS enc,
            CASE WHEN client_secret IS NOT NULL AND client_secret != ''
                 THEN 1 ELSE 0 END AS leg
        FROM oidc_config WHERE id = 1
        """,
        [],
    )
    oidc_enc = int(oidc_row["enc"]) if oidc_row else 0
    oidc_leg = int(oidc_row["leg"]) if oidc_row else 0

    ha_row = await db_fetchone(
        """
        SELECT
            SUM(CASE WHEN api_token_raw_encrypted IS NOT NULL THEN 1 ELSE 0 END) AS enc,
            COUNT(*) AS total
        FROM ha_peers
        """,
        [],
    )
    ha_enc = int(ha_row.get("enc") or 0) if ha_row else 0
    ha_total = int(ha_row.get("total") or 0) if ha_row else 0

    base["secrets_inventory"] = {
        "oidc_client_secret": {
            "encrypted": oidc_enc, "legacy_plaintext": oidc_leg,
        },
        "ha_peer_raw_token": {
            "with_raw_encrypted": ha_enc, "total_peers": ha_total,
        },
    }
    return base
