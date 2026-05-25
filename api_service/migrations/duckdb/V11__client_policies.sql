-- V11: políticas DNS por cliente (split-horizon).
--
-- Cada policy define um grupo lógico (ex: "kids", "iot", "office") com:
--   - 1..N ranges de IPs/CIDR (quais clientes caem na policy)
--   - 0..N domínios extra pra bloquear (`local-zone always_nxdomain` na view)
--   - 0..N domínios pra permitir explicitamente (`local-zone transparent` na view)
--
-- Modelo de herança: policies SEMPRE herdam o global. O `view: view-first: yes`
-- do Unbound faz o look-up cair pro `server:` quando a view não tem regra,
-- então os clientes de uma policy bloqueiam (globals ativos + extras da policy)
-- MENOS (allowlist global + allows da policy). Pra "override total" precisaria
-- outra estrutura — deliberadamente fora do MVP (decisão Bruno 2026-05-25).
--
-- Time-based blocking (janelas horárias) também fora do MVP.

------------------------------------------------------------
-- CLIENT_POLICIES — grupos
------------------------------------------------------------
CREATE SEQUENCE IF NOT EXISTS client_policies_id_seq START 1;
CREATE TABLE IF NOT EXISTS client_policies (
    id          INTEGER     PRIMARY KEY DEFAULT nextval('client_policies_id_seq'),
    slug        VARCHAR(50) NOT NULL UNIQUE,           -- vai virar nome da view no Unbound
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    enabled     BOOLEAN     NOT NULL DEFAULT true,
    sort_order  INTEGER     NOT NULL DEFAULT 100,
    created_at  TIMESTAMP   NOT NULL DEFAULT NOW()
);

------------------------------------------------------------
-- CLIENT_POLICY_RANGES — quais clientes caem na policy (CIDR ou IP único)
------------------------------------------------------------
CREATE SEQUENCE IF NOT EXISTS client_policy_ranges_id_seq START 1;
CREATE TABLE IF NOT EXISTS client_policy_ranges (
    id        INTEGER     PRIMARY KEY DEFAULT nextval('client_policy_ranges_id_seq'),
    policy_id INTEGER     NOT NULL,                    -- FK lógica → client_policies.id
    cidr      VARCHAR(50) NOT NULL,                    -- ex: "192.168.1.0/24" ou "10.0.0.5"
    label     VARCHAR(100),                            -- ex: "Wi-Fi crianças"
    UNIQUE (policy_id, cidr)
);

CREATE INDEX IF NOT EXISTS idx_client_policy_ranges_policy_id ON client_policy_ranges (policy_id);

------------------------------------------------------------
-- CLIENT_POLICY_BLOCKS — bloqueios extras manuais (na view)
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS client_policy_blocks (
    policy_id INTEGER      NOT NULL,
    domain    VARCHAR(255) NOT NULL,
    added_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    PRIMARY KEY (policy_id, domain)
);

CREATE INDEX IF NOT EXISTS idx_client_policy_blocks_policy_id ON client_policy_blocks (policy_id);

------------------------------------------------------------
-- CLIENT_POLICY_ALLOWS — allowlist específica da view (transparent)
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS client_policy_allows (
    policy_id INTEGER      NOT NULL,
    domain    VARCHAR(255) NOT NULL,
    added_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    PRIMARY KEY (policy_id, domain)
);

CREATE INDEX IF NOT EXISTS idx_client_policy_allows_policy_id ON client_policy_allows (policy_id);
