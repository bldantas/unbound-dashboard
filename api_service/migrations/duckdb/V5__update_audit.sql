-- V5: audit log de updates aplicados via UI.
--
-- Cada update/restore gera 2 writes:
--   1. INSERT no início (kind=update|restore, status=running)
--   2. UPDATE ao monitor terminar (status, finished_at)
--
-- Aba "Auditoria" mostra timeline completa: quem, quando, qual versão,
-- qual resultado. Trail forense pra investigar incidentes ("quem
-- aplicou aquele update às 02h?").

CREATE SEQUENCE IF NOT EXISTS update_audit_id_seq START 1;

CREATE TABLE IF NOT EXISTS update_audit (
    id              INTEGER     PRIMARY KEY DEFAULT nextval('update_audit_id_seq'),
    job_id          VARCHAR(32) NOT NULL UNIQUE,
    kind            VARCHAR(20) NOT NULL,  -- 'update' | 'restore'
    user_id         INTEGER,
    username        VARCHAR(50),
    ip              VARCHAR(64),
    from_version    VARCHAR(20),
    to_version      VARCHAR(20),
    backup_timestamp VARCHAR(20),          -- só restore (NULL pra update)
    acknowledge_breaking BOOLEAN DEFAULT false,
    status          VARCHAR(20) NOT NULL,  -- 'running'|'succeeded'|'failed'|'rolled_back'|'rollback_failed'
    started_at      INTEGER     NOT NULL,
    finished_at     INTEGER
);
