"""Configuração validada via pydantic-settings — substitui o .env sem schema do PHP v1."""

from __future__ import annotations

from typing import Annotated

from pydantic import AnyUrl, BeforeValidator, SecretStr, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


def _parse_csv_list(v: object) -> object:
    # Aceita string vazia, CSV e lista — pydantic-settings sozinho exige JSON ("[]")
    # para list[str] vindo de env var, o que é inconveniente no operacional.
    if isinstance(v, str):
        s = v.strip()
        if not s:
            return []
        return [item.strip() for item in s.split(",") if item.strip()]
    return v


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
    )

    # DuckDB — banco único (OLTP serializado + OLAP analytics)
    db_path: str = "/var/lib/unbound-dashboard/unbound_dash.duckdb"

    # Redis — cache TTL + pub/sub para WebSocket live-log
    redis_url: AnyUrl = AnyUrl("redis://127.0.0.1:6379/0")

    # JWT
    jwt_secret: SecretStr = SecretStr("CHANGE_ME_BEFORE_PRODUCTION")
    jwt_algorithm: str = "HS256"
    jwt_expire_minutes: int = 60

    # Unbound
    unbound_control: str = "/usr/sbin/unbound-control"
    unbound_log: str = "/var/log/unbound/unbound.log"

    # Aplicação
    log_level: str = "INFO"
    debug: bool = False
    api_version: str = "0.1.0"

    # CORS — origens permitidas (vazio = sem CORS, único origem assumido = mesmo host)
    cors_origins: Annotated[list[str], BeforeValidator(_parse_csv_list)] = []

    # Rate limiting
    rate_limit_default: str = "200/minute"
    rate_limit_auth: str = "10/minute"
    rate_limit_enabled: bool = True  # tests usam false pra evitar cross-test 429

    # GitHub token — necessário se o repo é privado. Sem isso, /api/v1/updates/check
    # vai retornar 404 quando GitHub bloquear a request anônima. Em repo público,
    # pode ficar vazio. Em prod, gere um Fine-grained PAT com escopo `Contents: Read`
    # do repo unbound-dashboard e injete via env var GITHUB_TOKEN.
    github_token: SecretStr = SecretStr("")

    @field_validator("jwt_secret")
    @classmethod
    def _reject_default_secret(cls, v: SecretStr) -> SecretStr:
        # Lição do audit em /opt: app subia silenciosamente com secret conhecido se .env não
        # estivesse configurado. Falhar no startup com mensagem clara é melhor que comprometer
        # silenciosamente todos os tokens emitidos.
        if "CHANGE_ME" in v.get_secret_value():
            raise ValueError(
                "JWT_SECRET ainda está no valor placeholder ('CHANGE_ME...'). "
                "Defina um secret real via variável de ambiente JWT_SECRET "
                "(ex: openssl rand -hex 32) antes de subir a aplicação."
            )
        return v


settings = Settings()
