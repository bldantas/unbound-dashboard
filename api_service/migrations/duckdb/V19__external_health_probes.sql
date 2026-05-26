-- V19: external health probes — monitor de SLA externo.
--
-- Script standalone (tools/external_healthcheck.py) rodando em outro
-- host faz queries DNS contra este Unbound e POSTa resultado aqui via
-- /api/v1/external-health/report. Permite ver "de fora" se o DNS está
-- respondendo, sem depender de monitor interno (que pode estar
-- comprometido junto com o servidor).

CREATE SEQUENCE IF NOT EXISTS external_health_probes_id_seq START 1;

CREATE TABLE IF NOT EXISTS external_health_probes (
    id                   BIGINT      PRIMARY KEY DEFAULT nextval('external_health_probes_id_seq'),
    probed_at            TIMESTAMP   NOT NULL DEFAULT now(),
    probe_source         VARCHAR(80),     -- ex: "monitor-aws-us-east-1", "monitor-azure-eu-west"
    target_host          VARCHAR(120),    -- ex: "dns.example.com"
    query_name           VARCHAR(255),    -- ex: "google.com"
    success              BOOLEAN     NOT NULL,
    latency_ms           INTEGER,
    response_correct     BOOLEAN,         -- valida que resposta != NXDOMAIN/SERVFAIL
    error                VARCHAR(500)
);

CREATE INDEX IF NOT EXISTS idx_eh_probed_at ON external_health_probes (probed_at);
CREATE INDEX IF NOT EXISTS idx_eh_source_time
    ON external_health_probes (probe_source, probed_at);
