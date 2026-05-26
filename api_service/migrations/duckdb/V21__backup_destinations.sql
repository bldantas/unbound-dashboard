-- V21: múltiplos destinos S3 pra backup off-site.
--
-- Antes (v2.68 e anteriores): tinha 1 destino só (backup_s3_* em settings).
-- Agora: N destinos em paralelo (ex: AWS S3 + Backblaze B2 + Wasabi
-- simultâneo) — se um provedor cair, os outros ainda têm o backup.
--
-- Backward compat: se a tabela está vazia, BackupUploader continua usando
-- as settings legacy. Quando admin adiciona pelo menos 1 destination, o
-- worker passa a iterar pelas N enabled.
--
-- secret_key é cifrado via cipher_service (v2.71). Coluna `secret_key`
-- sempre guarda valor cifrado quando SECRETS_MASTER_KEY está configurada.

CREATE SEQUENCE IF NOT EXISTS backup_destinations_id_seq START 1;

CREATE TABLE IF NOT EXISTS backup_destinations (
    id              INTEGER     PRIMARY KEY DEFAULT nextval('backup_destinations_id_seq'),
    label           VARCHAR(80) NOT NULL UNIQUE,    -- "AWS Primary", "B2 Backup", etc
    endpoint        VARCHAR(255),                    -- vazio = AWS default
    bucket          VARCHAR(120) NOT NULL,
    region          VARCHAR(40)  NOT NULL DEFAULT 'us-east-1',
    prefix          VARCHAR(120),                    -- subpasta opcional
    access_key      VARCHAR(255),
    secret_key      VARCHAR(1000),                   -- cifrado via cipher_service
    retention_count INTEGER     NOT NULL DEFAULT 10,
    enabled         BOOLEAN     NOT NULL DEFAULT true,
    priority        INTEGER     NOT NULL DEFAULT 100, -- 0..1000, ordem do upload (DESC)
    last_upload_at  TIMESTAMP,
    last_status     VARCHAR(20),    -- ok | error
    last_error      VARCHAR(1000),
    last_size_bytes BIGINT,
    last_key        VARCHAR(500),
    created_at      TIMESTAMP   NOT NULL DEFAULT now(),
    updated_at      TIMESTAMP   NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_backup_destinations_enabled
    ON backup_destinations (enabled, priority);
