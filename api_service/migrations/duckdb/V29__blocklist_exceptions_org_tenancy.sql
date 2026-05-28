-- V29: multi-tenant em blocklist_exceptions com PK composto.
--
-- Problema: V28 skipou blocklist_exceptions porque `domain` era PK simples.
-- Adicionar `org_id` nullable + UNIQUE(domain, org_id) não funciona bem no
-- DuckDB porque múltiplos `(foo.com, NULL)` violariam a semântica lógica
-- de "uma única exceção global", e enforcement via app é frágil.
--
-- Solução: usar `org_id INTEGER NOT NULL DEFAULT 0` (0 = global, IDs reais
-- de organizations começam em 1 na sequence). PK composto (domain, org_id)
-- direto, sem NULL.
--
-- Trade-off vs padrão das outras tabelas tenant (hosts/alerts/audit/
-- policies usam `org_id IS NULL` pra global): aqui blocklist_exceptions é
-- a única que usa `org_id = 0`. Documentado e isolado nesta tabela; os
-- filtros do repo escondem essa diferença da API/UI.
--
-- Efeito no Unbound: `blocklist_sources_repo.domains_to_block()` continua
-- gerando UM blocklist file global (filtra `org_id = 0`). Exceções
-- org-scoped existem no DB e UI/API as listam, mas só passam a aparecer
-- no zonefile do Unbound quando split-horizon de blocklist for
-- implementado (alimentando blocklist por view via client_policies).
-- Esse é um trabalho futuro — esta migração só destrava o schema.

-- DuckDB não permite ALTER PRIMARY KEY; precisamos recriar a tabela.

CREATE TABLE IF NOT EXISTS blocklist_exceptions_new (
    domain          VARCHAR(255) NOT NULL,
    org_id          INTEGER      NOT NULL DEFAULT 0,
    reason          TEXT,
    created_by      VARCHAR(50),
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    PRIMARY KEY (domain, org_id)
);

-- Migra dados existentes — todos viram global (org_id = 0)
INSERT INTO blocklist_exceptions_new (domain, org_id, reason, created_by, created_at)
SELECT domain, 0, reason, created_by, created_at FROM blocklist_exceptions;

DROP TABLE blocklist_exceptions;
ALTER TABLE blocklist_exceptions_new RENAME TO blocklist_exceptions;

CREATE INDEX IF NOT EXISTS idx_blocklist_exceptions_org ON blocklist_exceptions (org_id);
CREATE INDEX IF NOT EXISTS idx_blocklist_exceptions_domain ON blocklist_exceptions (domain);
