-- V1__initial — schema inicial DuckDB para a v1 modernizada.
--
-- Mapeamento MariaDB → DuckDB (ver docs/PLANO_MODERNIZACAO_V1.md):
--   users            → users            (preserva bcrypt, role, lockout)
--   settings         → settings         (key/value)
--   alerts           → alerts           (started_at + resolved_at + is_dismissed)
--   query_logs       → query_logs       (timestamp INTEGER unix epoch)
--   daily_stats     → daily_stats      (stat_date PRIMARY KEY)
--   domain_blacklist → blocklist_domains  (RENOMEADA)
--
-- Estratégia de escrita (ver app/repositories/duckdb/connection.py — a criar):
-- todas as escritas serializadas via ThreadPoolExecutor(max_workers=1).
-- DuckDB não tolera múltiplos writers simultâneos — leituras são concorrentes OK.

------------------------------------------------------------
-- USERS — autenticação + RBAC + lockout
------------------------------------------------------------
CREATE SEQUENCE IF NOT EXISTS users_id_seq START 1;
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER     PRIMARY KEY DEFAULT nextval('users_id_seq'),
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'admin',
    email           VARCHAR(255) UNIQUE,
    is_active       BOOLEAN      NOT NULL DEFAULT true,
    failed_logins   INTEGER      NOT NULL DEFAULT 0,
    locked_until    TIMESTAMP,
    reset_token     VARCHAR(64),
    reset_expires   TIMESTAMP,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

------------------------------------------------------------
-- SETTINGS — chave/valor
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key     VARCHAR(50)  PRIMARY KEY,
    setting_value   VARCHAR(255) NOT NULL
);

------------------------------------------------------------
-- ALERTS
------------------------------------------------------------
CREATE SEQUENCE IF NOT EXISTS alerts_id_seq START 1;
CREATE TABLE IF NOT EXISTS alerts (
    id              INTEGER     PRIMARY KEY DEFAULT nextval('alerts_id_seq'),
    type            VARCHAR(50)  NOT NULL,
    severity        VARCHAR(20)  NOT NULL DEFAULT 'warning',
    message         TEXT,
    started_at      TIMESTAMP    NOT NULL,
    resolved_at     TIMESTAMP,
    is_dismissed    BOOLEAN      NOT NULL DEFAULT false
);

------------------------------------------------------------
-- QUERY_LOGS — tabela analítica (OLAP). DuckDB cria zonemaps por coluna
-- automaticamente — sem necessidade de CREATE INDEX explícito.
------------------------------------------------------------
CREATE SEQUENCE IF NOT EXISTS query_logs_id_seq START 1;
CREATE TABLE IF NOT EXISTS query_logs (
    id              BIGINT      PRIMARY KEY DEFAULT nextval('query_logs_id_seq'),
    timestamp       INTEGER     NOT NULL,    -- unix epoch
    client_ip       VARCHAR(45) NOT NULL,
    domain          VARCHAR(255) NOT NULL,
    query_type      VARCHAR(10)  NOT NULL,
    action          VARCHAR(20)  NOT NULL
);

------------------------------------------------------------
-- DAILY_STATS — sumário pré-agregado por dia
------------------------------------------------------------
CREATE SEQUENCE IF NOT EXISTS daily_stats_id_seq START 1;
CREATE TABLE IF NOT EXISTS daily_stats (
    id              INTEGER     PRIMARY KEY DEFAULT nextval('daily_stats_id_seq'),
    stat_date       DATE         NOT NULL UNIQUE,
    total_queries   BIGINT       NOT NULL DEFAULT 0,
    cache_hits      BIGINT       NOT NULL DEFAULT 0,
    cache_misses    BIGINT       NOT NULL DEFAULT 0,
    blocked_count   BIGINT       NOT NULL DEFAULT 0
);

------------------------------------------------------------
-- BLOCKLIST_DOMAINS (renomeada de domain_blacklist)
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocklist_domains (
    domain          VARCHAR(255) PRIMARY KEY,
    category        VARCHAR(50),
    severity        VARCHAR(20)
);
