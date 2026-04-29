"""
Runner de migrations DuckDB.
Aplica os scripts SQL de migrations/duckdb/ em ordem, usando uma tabela
de controle para rastrear quais já foram executados.

Uso:
    python -m app.db.migrate
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

import duckdb

from app.core.config import settings

MIGRATIONS_DIR = Path(__file__).parent.parent.parent / "migrations" / "duckdb"
_VERSION_RE = re.compile(r"V(\d+)__.*\.sql")


def _get_conn(path: str) -> duckdb.DuckDBPyConnection:
    p = Path(path)
    p.parent.mkdir(parents=True, exist_ok=True)
    return duckdb.connect(str(p))


def run_migrations(db_path: str | None = None) -> None:
    path = db_path or settings.db_path
    conn = _get_conn(path)

    # Tabela de controle de versão
    conn.execute("""
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version    INTEGER   NOT NULL PRIMARY KEY,
            filename   VARCHAR   NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT NOW()
        )
    """)

    applied = {
        row[0] for row in conn.execute("SELECT version FROM schema_migrations").fetchall()
    }

    scripts = sorted(
        (f for f in MIGRATIONS_DIR.glob("V*.sql") if _VERSION_RE.match(f.name)),
        key=lambda f: int(_VERSION_RE.match(f.name).group(1)),  # type: ignore[union-attr]
    )

    applied_count = 0
    for script in scripts:
        m = _VERSION_RE.match(script.name)
        if not m:
            continue
        version = int(m.group(1))
        if version in applied:
            continue

        print(f"  → aplicando {script.name}...", end=" ")
        sql = script.read_text(encoding="utf-8")
        conn.execute(sql)
        conn.execute(
            "INSERT INTO schema_migrations (version, filename) VALUES (?, ?)",
            [version, script.name],
        )
        print("ok")
        applied_count += 1

    if applied_count == 0:
        print("  schema já atualizado, nada a fazer.")
    else:
        print(f"  {applied_count} migration(s) aplicada(s).")

    conn.close()


if __name__ == "__main__":
    print(f"DuckDB migrations → {settings.db_path}")
    run_migrations()
    sys.exit(0)
