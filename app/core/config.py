from pydantic import AnyUrl, SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
    )

    # DuckDB — banco único (OLTP serializado + OLAP analytics)
    db_path: str = "/var/lib/unbound-dashboard/unbound_dash.duckdb"

    # Exportação JSON periódica — lida pelo PHP v1 sem chamadas HTTP
    export_path: str = "/var/lib/unbound-dashboard/export"

    # Redis — cache TTL + pub/sub para WebSocket
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

    # CORS — lista de origens separadas por vírgula (ex: "http://localhost:5173,https://example.com")
    cors_origins: list[str] = ["http://localhost:5173"]

    # Rate limiting
    rate_limit_default: str = "200/minute"
    rate_limit_auth: str = "10/minute"


settings = Settings()
