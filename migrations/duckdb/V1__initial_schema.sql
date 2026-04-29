-- Unbound Dashboard v2 — Schema DuckDB único
-- Todas as tabelas (OLTP + OLAP) no mesmo arquivo .duckdb
-- Execução: python -m app.db.migrate  (ou tools/migrate-up.sh)

-- ============================================================
-- TABELAS OLTP (escritas serializadas via ThreadPoolExecutor)
-- ============================================================

CREATE SEQUENCE IF NOT EXISTS users_id_seq START 1;
CREATE TABLE IF NOT EXISTS users (
    id                INTEGER      NOT NULL DEFAULT nextval('users_id_seq'),
    username          VARCHAR(64)  NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    role              VARCHAR(10)  NOT NULL DEFAULT 'viewer',
    email             VARCHAR(255),
    is_active         BOOLEAN      NOT NULL DEFAULT true,
    failed_logins     INTEGER      NOT NULL DEFAULT 0,
    locked_until      TIMESTAMP,
    created_at        TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMP    NOT NULL DEFAULT NOW(),
    PRIMARY KEY (id),
    UNIQUE (username)
);

CREATE TABLE IF NOT EXISTS settings (
    key   VARCHAR(128) NOT NULL PRIMARY KEY,
    value TEXT         NOT NULL
);

CREATE SEQUENCE IF NOT EXISTS alerts_id_seq START 1;
CREATE TABLE IF NOT EXISTS alerts (
    id         INTEGER      NOT NULL DEFAULT nextval('alerts_id_seq'),
    type       VARCHAR(50)  NOT NULL,
    message    TEXT         NOT NULL,
    severity   VARCHAR(10)  NOT NULL DEFAULT 'info',
    is_read    BOOLEAN      NOT NULL DEFAULT false,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW(),
    PRIMARY KEY (id)
);

-- ============================================================
-- TABELAS OLAP (analytics DNS — leitura vetorizada colunar)
-- ============================================================

CREATE TABLE IF NOT EXISTS query_logs (
    timestamp  INTEGER      NOT NULL,  -- unix epoch (INT é suficiente até 2038; migrar para BIGINT depois)
    client_ip  VARCHAR(45)  NOT NULL,
    domain     VARCHAR(255) NOT NULL,
    query_type VARCHAR(10)  NOT NULL,
    action     VARCHAR(20)  NOT NULL   -- 'resolved' | 'blocked' | 'cached'
);
-- DuckDB cria zonemaps automáticos por coluna — não precisa de CREATE INDEX explícito.
-- Para uso intenso de range queries em timestamp, considerar particionamento por data.

CREATE TABLE IF NOT EXISTS daily_stats (
    date       DATE    NOT NULL PRIMARY KEY,
    total      BIGINT  NOT NULL DEFAULT 0,
    blocked    BIGINT  NOT NULL DEFAULT 0,
    resolved   BIGINT  NOT NULL DEFAULT 0,
    cache_hits BIGINT  NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS blocklist_hits (
    timestamp  INTEGER      NOT NULL,
    domain     VARCHAR(255) NOT NULL,
    list_name  VARCHAR(128) NOT NULL
);
