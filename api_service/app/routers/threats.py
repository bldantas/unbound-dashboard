"""
Endpoint /api/v1/threats/data — espelho do api/threats_data.php v1 consumindo DuckDB.

Mantém o mesmo shape de resposta que o frontend PHP existente espera, pra
permitir cutover sem mudança no JS do dashboard. Validação de `limit` segue
política do PHP (10/20/50/100/'todos' → 1000; qualquer outra coisa cai pra 10).

Protegido por capability `blocklist.read` — admin, readonly_admin e operator.
Threats são o que a blocklist está filtrando; faz sentido o operator do NOC ver.
"""

from __future__ import annotations

from typing import Annotated

from fastapi import APIRouter, Depends, Query

from app.core.deps import require_capability
from app.services import threats_service

router = APIRouter(prefix="/api/v1/threats", tags=["threats"])

_ALLOWED_LIMITS = frozenset({10, 20, 50, 100})


def _parse_limit(raw: str) -> int:
    if raw == "todos":
        return 1000
    try:
        n = int(raw)
    except ValueError:
        return 10
    return n if n in _ALLOWED_LIMITS else 10


@router.get("/data")
async def threats_data(
    _: Annotated[dict, Depends(require_capability("blocklist.read"))],
    limit: str = Query("10", description="10|20|50|100|'todos'"),
) -> dict:
    parsed_limit = _parse_limit(limit)
    data = await threats_service.get_threats_data(limit=parsed_limit)
    return {"status": "success", "data": data}
