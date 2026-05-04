"""
Runner de migrations DuckDB.

Detecta arquivos `migrations/duckdb/V<N>__<nome>.sql`, aplica em ordem,
registra cada aplicação em `schema_migrations` (com checksum sha256
do arquivo). Idempotente — re-rodar não re-aplica nada.

Uso programático (no lifespan do FastAPI):
    from app.db import run_migrations
    run_migrations()

Uso CLI:
    JWT_SECRET=... DB_PATH=/var/lib/unbound-dashboard/unbound_dash.duckdb \
        .venv/bin/python -m app.db.migrate
"""

from __future__ import annotations

import hashlib
import re
from pathlib import Path

import duckdb
import structlog

from app.core.config import settings

log = structlog.get_logger(__name__)

_VERSION_RE = re.compile(r"^V(\d+)__(.+)\.sql$")
_MIGRATIONS_DIR = Path(__file__).resolve().parent.parent.parent / "migrations" / "duckdb"


def _ensure_schema_migrations(conn: duckdb.DuckDBPyConnection) -> None:
    """
    Cria a tabela `schema_migrations` se não existir. Se já existir com schema
    legado (de versões experimentais anteriores que usavam (version, filename)
    em vez de (version, name, checksum)), faz ALTER TABLE pra adicionar as
    colunas faltantes e popular com valores tolerantes — sem perder histórico
    de migrations já aplicadas.
    """
    conn.execute(
        """
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version    INTEGER     PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            checksum   VARCHAR(64)  NOT NULL,
            applied_at TIMESTAMP    NOT NULL DEFAULT NOW()
        )
        """
    )

    # Detecta colunas presentes (DuckDB só suporta `IF NOT EXISTS` em
    # `CREATE TABLE`, não em `ADD COLUMN` em todas as versões — fazemos a
    # checagem manual via information_schema).
    cols = {
        row[0]
        for row in conn.execute(
            "SELECT column_name FROM information_schema.columns "
            "WHERE table_schema = 'main' AND table_name = 'schema_migrations'"
        ).fetchall()
    }

    # checksum: legado pode não ter. Adiciona como nullable e popula com '' (string vazia).
    # Em entradas com checksum vazio, o runner pula a validação de drift (já está aplicada).
    if "checksum" not in cols:
        conn.execute("ALTER TABLE schema_migrations ADD COLUMN checksum VARCHAR(64)")
        conn.execute("UPDATE schema_migrations SET checksum = '' WHERE checksum IS NULL")

    # name: legado pode usar `filename` (com .sql). Migra valor sem extensão.
    if "name" not in cols:
        conn.execute("ALTER TABLE schema_migrations ADD COLUMN name VARCHAR(255)")
        if "filename" in cols:
            conn.execute(
                "UPDATE schema_migrations SET name = regexp_replace(filename, '\\.sql$', '') "
                "WHERE name IS NULL"
            )
        else:
            conn.execute("UPDATE schema_migrations SET name = '' WHERE name IS NULL")

    # applied_at: legado pode não ter. Default NOW() preserva ordering relativa.
    if "applied_at" not in cols:
        conn.execute("ALTER TABLE schema_migrations ADD COLUMN applied_at TIMESTAMP DEFAULT NOW()")
        conn.execute("UPDATE schema_migrations SET applied_at = NOW() WHERE applied_at IS NULL")


def _discover_migrations() -> list[tuple[int, str, Path]]:
    """Retorna [(version, name, path), ...] ordenado por versão."""
    if not _MIGRATIONS_DIR.exists():
        return []
    entries: list[tuple[int, str, Path]] = []
    for path in _MIGRATIONS_DIR.iterdir():
        match = _VERSION_RE.match(path.name)
        if not match:
            continue
        version = int(match.group(1))
        name = match.group(2)
        entries.append((version, name, path))
    return sorted(entries, key=lambda e: e[0])


def _checksum(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def run_migrations(db_path: str | None = None) -> list[int]:
    """
    Aplica migrations pendentes. Retorna a lista de versões aplicadas nesta
    chamada (vazia se nada novo).

    Falha imediatamente se um arquivo já aplicado tem checksum diferente do
    registrado — proteção contra alteração retroativa de migration.
    """
    target = db_path or settings.db_path
    Path(target).parent.mkdir(parents=True, exist_ok=True)
    applied: list[int] = []

    with duckdb.connect(target) as conn:
        _ensure_schema_migrations(conn)
        existing = {
            row[0]: row[1]
            for row in conn.execute("SELECT version, checksum FROM schema_migrations").fetchall()
        }
        for version, name, path in _discover_migrations():
            checksum = _checksum(path)
            if version in existing:
                existing_checksum = existing[version] or ""
                # Schema legado (de versão experimental anterior) pode ter
                # checksum vazio — nesse caso a migration já está aplicada e
                # pulamos a validação de drift sem falhar.
                if existing_checksum and existing_checksum != checksum:
                    raise RuntimeError(
                        f"Migration V{version}__{name}.sql foi alterada após aplicada "
                        f"(checksum esperado={existing_checksum}, atual={checksum}). "
                        "Crie uma migration nova em vez de editar a existente."
                    )
                continue
            log.info("migration.applying", version=version, name=name)
            sql = path.read_text(encoding="utf-8")
            conn.execute("BEGIN")
            try:
                conn.execute(sql)
                conn.execute(
                    "INSERT INTO schema_migrations (version, name, checksum) VALUES (?, ?, ?)",
                    [version, name, checksum],
                )
                conn.execute("COMMIT")
            except Exception:
                conn.execute("ROLLBACK")
                log.error("migration.failed", version=version, name=name)
                raise
            applied.append(version)
            log.info("migration.applied", version=version, name=name, checksum=checksum[:12])

    if not applied:
        log.info("migrations.up_to_date", db_path=target)
    else:
        log.info("migrations.done", applied=applied, db_path=target)
    return applied


if __name__ == "__main__":
    run_migrations()
