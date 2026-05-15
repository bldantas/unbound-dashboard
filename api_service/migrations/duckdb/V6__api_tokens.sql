-- V6: API tokens pra autenticação master → agent no multi-host.
--
-- Diferente de JWT (user-session): API tokens são long-lived, gerados na
-- UI, vinculados a uma label (ex: "master-orchestrator"). Master usa
-- esses tokens em todas chamadas pra ler/controlar o agent.
--
-- Storage: hash SHA256 do token (igual session tokens). O token bruto
-- é mostrado UMA VEZ na UI ao gerar — admin copia + cola no master.
-- Se perder, revoga e gera novo.
--
-- Permissões: por enquanto cada token tem "controle total" (todas
-- capabilities). Granularidade futura pode adicionar coluna `capabilities`.

CREATE SEQUENCE IF NOT EXISTS api_tokens_id_seq START 1;

CREATE TABLE IF NOT EXISTS api_tokens (
    id            INTEGER     PRIMARY KEY DEFAULT nextval('api_tokens_id_seq'),
    label         VARCHAR(100) NOT NULL,
    token_hash    VARCHAR(64)  NOT NULL UNIQUE,
    created_by    INTEGER,                  -- user_id que criou (pra audit)
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    last_used_at  TIMESTAMP,
    last_used_ip  VARCHAR(64),
    revoked_at    TIMESTAMP                 -- NULL = ativo
);
