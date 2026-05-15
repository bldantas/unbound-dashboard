-- V7: hosts gerenciados pelo master no multi-host setup.
--
-- Cada linha = um agent que o master polleia. master poderia também
-- ter uma entrada aqui pra si mesmo (label "this") pra unificar a
-- view, mas isso é decisão do UI — schema permite.
--
-- api_token é plaintext (mesmo nível do SMTP password). Pra hardening
-- futuro: criptografar com chave derivada do JWT_SECRET.

CREATE SEQUENCE IF NOT EXISTS managed_hosts_id_seq START 1;

CREATE TABLE IF NOT EXISTS managed_hosts (
    id            INTEGER     PRIMARY KEY DEFAULT nextval('managed_hosts_id_seq'),
    label         VARCHAR(100) NOT NULL,        -- nome humano (ex: "Recursor-SP1")
    base_url      VARCHAR(255) NOT NULL UNIQUE, -- ex: https://dns1.exemplo.com
    api_token     VARCHAR(255) NOT NULL,        -- raw token do api_tokens do agent
    notes         TEXT,                         -- descrição opcional
    added_by      INTEGER,                      -- user_id que adicionou
    added_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    last_polled_at TIMESTAMP,                   -- última tentativa
    last_status_at TIMESTAMP,                   -- último poll bem-sucedido
    last_status   VARCHAR(20),                  -- ok | unreachable | auth_failed | error
    last_status_payload TEXT,                   -- JSON snapshot da última /host/status OK
    last_error    TEXT                          -- mensagem de erro do último poll falho
);
