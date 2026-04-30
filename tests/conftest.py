"""Configuração global de testes — fixtures compartilhadas."""
from __future__ import annotations

import pytest
import duckdb
from pathlib import Path

from app.db import run_migrations


@pytest.fixture(scope="function")
def duck_db(tmp_path: Path) -> duckdb.DuckDBPyConnection:
    """DuckDB temporário com schema aplicado — isolado por teste.
    A conexão é fechada após o yield para não bloquear conexões posteriores.
    """
    db_path = str(tmp_path / "test.duckdb")
    run_migrations(db_path)
    conn = duckdb.connect(db_path)
    yield conn
    conn.close()
