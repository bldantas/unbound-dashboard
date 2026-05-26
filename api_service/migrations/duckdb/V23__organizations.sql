-- V23: organizations — multi-tenant minimalista (infrastructure-only).
--
-- Esta release entrega só a **infraestrutura**. NÃO particiona dados
-- existentes (audit, hosts, blocklists etc continuam globais). A coluna
-- `org_id` em users é nullable — NULL = "system org" (admin global).
--
-- Próximas iterações podem:
-- 1. Adicionar `org_id` em tabelas com tenant data (hosts, alerts, etc)
-- 2. Filtrar listings por org_id quando role != admin global
-- 3. RBAC per-org (admin de uma org gerencia só os usuários dela)
--
-- Por ora: CRUD de orgs + atribuir users a orgs.

CREATE SEQUENCE IF NOT EXISTS organizations_id_seq START 1;

CREATE TABLE IF NOT EXISTS organizations (
    id              INTEGER     PRIMARY KEY DEFAULT nextval('organizations_id_seq'),
    name            VARCHAR(120) NOT NULL UNIQUE,
    slug            VARCHAR(80)  NOT NULL UNIQUE,    -- url-safe; immutable após criar
    description     VARCHAR(500),
    is_active       BOOLEAN     NOT NULL DEFAULT true,
    created_at      TIMESTAMP   NOT NULL DEFAULT now(),
    updated_at      TIMESTAMP   NOT NULL DEFAULT now()
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS org_id INTEGER;
-- NULL = global/system user; FK lógico (DuckDB não enforça FK constraint
-- em ALTER existing tables com dados, então deixa lógico só)

CREATE INDEX IF NOT EXISTS idx_users_org_id ON users (org_id);
