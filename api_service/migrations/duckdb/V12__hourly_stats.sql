-- V12: agregação horária de query_logs + retenção configurável.
--
-- HOURLY_STATS é o sumário pré-agregado por hora — preenchido pelo
-- StatsAggregator (mesma ideia do daily_stats da V1, granularidade menor).
-- Usado por gráficos de "queries/h últimas 24h" sem varrer query_logs
-- bruto e por endpoints de observabilidade (latência/saúde via timeseries).
--
-- hour_start é unix epoch truncado pra hora (timestamp // 3600 * 3600).
-- UNIQUE garante UPSERT idempotente. id BIGINT porque acumula muitas linhas.

CREATE SEQUENCE IF NOT EXISTS hourly_stats_id_seq START 1;
CREATE TABLE IF NOT EXISTS hourly_stats (
    id              BIGINT      PRIMARY KEY DEFAULT nextval('hourly_stats_id_seq'),
    hour_start      INTEGER     NOT NULL UNIQUE,
    total_queries   BIGINT      NOT NULL DEFAULT 0,
    blocked_count   BIGINT      NOT NULL DEFAULT 0
);
