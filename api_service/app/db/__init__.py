"""Camada de inicialização e migrations do DuckDB."""

from app.db.migrate import run_migrations

__all__ = ["run_migrations"]
