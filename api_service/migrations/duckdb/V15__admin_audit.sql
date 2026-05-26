-- V15: admin audit — trilha geral de ações administrativas.
--
-- Diferente do `update_audit` (V5), que só cobre updates/restores
-- aplicados pela UI de sistema, esta tabela registra QUALQUER ação
-- relevante de admin: login, logout, edits de blocklist, alterações
-- de config (hardening/performance/dns-security/etc), CRUD de hosts,
-- geração de cert, push-config, etc.
--
-- `category` é uma classificação grosseira pra retention diferenciado e
-- filtros: 'auth' | 'config' | 'blocklist' | 'user' | 'host' | 'cert' |
-- 'data_export' | 'other'. Mantém aberto pra crescer.
--
-- `details` é JSON livre pra contexto adicional (ex: keys alteradas em
-- bulk-upsert, IP do cert gerado, etc).

CREATE SEQUENCE IF NOT EXISTS admin_audit_id_seq START 1;

CREATE TABLE IF NOT EXISTS admin_audit (
    id              BIGINT      PRIMARY KEY DEFAULT nextval('admin_audit_id_seq'),
    created_at      TIMESTAMP   NOT NULL DEFAULT now(),
    actor_id        INTEGER,
    actor_username  VARCHAR(64),
    actor_ip        VARCHAR(64),
    action          VARCHAR(80) NOT NULL,    -- ex: 'login.success', 'dns_security.apply', 'host.delete'
    category        VARCHAR(32) NOT NULL,    -- 'auth' | 'config' | etc
    target_type     VARCHAR(40),             -- 'host' | 'blocklist' | 'user' | NULL
    target_id       VARCHAR(80),             -- id ou slug do alvo
    details         JSON
);

CREATE INDEX IF NOT EXISTS idx_admin_audit_created
    ON admin_audit (created_at);
CREATE INDEX IF NOT EXISTS idx_admin_audit_category
    ON admin_audit (category, created_at);
CREATE INDEX IF NOT EXISTS idx_admin_audit_actor
    ON admin_audit (actor_id, created_at);
