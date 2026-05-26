-- V14: whitelist do AnomalyDetector — suprime detecções legítimas conhecidas.
--
-- Cada regra combina por:
--   * kind = 'client_ip'           → match exato no client_ip
--   * kind = 'domain'              → substring match (LIKE %pattern%) no domain
--   * kind = 'client_and_domain'   → match nos dois ao mesmo tempo
--
-- detector vazio = aplica a todos os 6 checks. Senão, restringe a um
-- detector específico ('dga' | 'nxdomain' | 'new_client' | 'tunneling'
-- | 'beaconing' | 'suspicious_tld').
--
-- Sem regex: substring match é suficiente pra casos comuns e evita
-- catastrophic backtracking + overhead de re-compilar a cada tick.

CREATE SEQUENCE IF NOT EXISTS anomaly_whitelist_id_seq START 1;
CREATE TABLE IF NOT EXISTS anomaly_whitelist (
    id              BIGINT       PRIMARY KEY DEFAULT nextval('anomaly_whitelist_id_seq'),
    kind            VARCHAR(32)  NOT NULL,
    client_ip       VARCHAR(64)  NOT NULL DEFAULT '',
    domain_pattern  VARCHAR(255) NOT NULL DEFAULT '',
    detector        VARCHAR(32)  NOT NULL DEFAULT '',
    note            VARCHAR(255) NOT NULL DEFAULT '',
    created_at      INTEGER      NOT NULL
);
