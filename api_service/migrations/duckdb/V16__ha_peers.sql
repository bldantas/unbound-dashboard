-- V16: HA peers — registro de hosts pares pra observabilidade de cluster
-- e manual failover assist.
--
-- Difere da V7 `managed_hosts` (que é o lado master poll-agentes). Aqui
-- modelamos *pares* da mesma role: 2+ instâncias Unbound rodando o
-- mesmo papel, e o dashboard mostra status agregado.
--
-- `role`: 'primary' (atende tráfego ativo) | 'secondary' (backup).
-- Cluster pode ter 1 primary e N secondaries; failover manual promove
-- secondary → primary (não toca em IPs/DNS automaticamente — só registra).

CREATE SEQUENCE IF NOT EXISTS ha_peers_id_seq START 1;

CREATE TABLE IF NOT EXISTS ha_peers (
    id                  INTEGER     PRIMARY KEY DEFAULT nextval('ha_peers_id_seq'),
    label               VARCHAR(80) NOT NULL UNIQUE,    -- ex: "SRV02-UNBOUND"
    api_url             VARCHAR(255) NOT NULL,           -- ex: "https://srv02.local"
    api_token_hash      VARCHAR(80),                     -- bcrypt do X-Api-Token desse peer
    role                VARCHAR(20) NOT NULL DEFAULT 'secondary', -- primary | secondary
    priority            INTEGER     NOT NULL DEFAULT 100, -- 0..1000, ordena pra failover assist
    enabled             BOOLEAN     NOT NULL DEFAULT true,
    last_check_at       TIMESTAMP,
    last_check_status   VARCHAR(20),                     -- ok | down | unauthorized | timeout
    last_check_latency_ms INTEGER,
    last_check_payload  JSON,                            -- snapshot do /health pra exibir versão, uptime
    created_at          TIMESTAMP   NOT NULL DEFAULT now(),
    updated_at          TIMESTAMP   NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_ha_peers_enabled ON ha_peers (enabled, role);
CREATE INDEX IF NOT EXISTS idx_ha_peers_last_check ON ha_peers (last_check_at);
