"""
Configuração global de tests — variáveis de ambiente DEVEM ser setadas ANTES
do import de qualquer módulo do app. Por isso aqui no top-level do conftest,
não em fixture (settings é instanciado at module-import time).
"""

from __future__ import annotations

import os

os.environ.setdefault("JWT_SECRET", "test-only-not-for-prod-deadbeef-with-padding")
os.environ.setdefault("DB_PATH", "/tmp/test_default.duckdb")
os.environ.setdefault("RATE_LIMIT_ENABLED", "false")  # evita 429 falsos entre testes
