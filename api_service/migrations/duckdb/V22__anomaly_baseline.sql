-- V22: baseline aprendido pra detector de desvio (ML simples).
--
-- BaselineLearner roda 1x/dia. Olha hourly_stats das últimas N semanas
-- e calcula, **por hora-do-dia × dia-da-semana**, média + stddev de:
-- - total_queries (volume)
-- - blocked_count (proporção)
--
-- Detector baseline_deviation então compara hora atual com baseline
-- da mesma (hour_of_day, day_of_week) e alerta se está fora de N
-- desvios padrão (configurável). Captura tendências sazonais que
-- detector estatístico naïve perderia (ex: "8h de segunda" vs
-- "8h de domingo" são distribuições diferentes).
--
-- 7 × 24 = 168 buckets totais. Cada bucket precisa de pelo menos
-- `min_samples` amostras pra ser usado (skip senão).

CREATE TABLE IF NOT EXISTS anomaly_baseline (
    hour_of_day     INTEGER NOT NULL,  -- 0..23
    day_of_week     INTEGER NOT NULL,  -- 0=Sun..6=Sat (DuckDB DAYOFWEEK)
    sample_count    INTEGER NOT NULL DEFAULT 0,
    avg_queries     DOUBLE  NOT NULL DEFAULT 0,
    stddev_queries  DOUBLE  NOT NULL DEFAULT 0,
    avg_blocked     DOUBLE  NOT NULL DEFAULT 0,
    stddev_blocked  DOUBLE  NOT NULL DEFAULT 0,
    learned_at      TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY (hour_of_day, day_of_week)
);
