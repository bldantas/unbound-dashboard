-- V17: OIDC SSO config (single-row).
--
-- Permite integração com Google Workspace, Microsoft Entra ID, Keycloak,
-- Authentik, etc. via OpenID Connect (Authorization Code flow simples).
--
-- Comportamento de matching:
-- 1. Após callback do IdP, extrai email do id_token.
-- 2. Procura user existente por email — se achar, faz login direto.
-- 3. Se NÃO achar:
--    - auto_create_users=true → cria com `default_role`
--    - auto_create_users=false → erro 403 (admin precisa cadastrar manualmente)
--
-- `allowed_email_domains` (CSV) restringe IdPs corporativos a domínios
-- específicos (ex: "empresa.com,contractor.com"). Vazio = qualquer
-- domínio (não recomendado).
--
-- `client_secret` em texto plano por enquanto — TODO mover pra secrets store
-- cifrado (similar ao api_tokens). Aviso explícito na UI.

CREATE TABLE IF NOT EXISTS oidc_config (
    id                      INTEGER     PRIMARY KEY DEFAULT 1,
    enabled                 BOOLEAN     NOT NULL DEFAULT false,
    issuer_url              VARCHAR(255),
    client_id               VARCHAR(255),
    client_secret           VARCHAR(255),
    scopes                  VARCHAR(255) NOT NULL DEFAULT 'openid email profile',
    allowed_email_domains   VARCHAR(500),     -- CSV; vazio = qualquer
    auto_create_users       BOOLEAN     NOT NULL DEFAULT false,
    default_role            VARCHAR(20)  NOT NULL DEFAULT 'viewer',  -- viewer | operator | readonly_admin | admin
    updated_at              TIMESTAMP   NOT NULL DEFAULT now(),
    CHECK (id = 1)  -- single row
);

INSERT INTO oidc_config (id) VALUES (1) ON CONFLICT DO NOTHING;
