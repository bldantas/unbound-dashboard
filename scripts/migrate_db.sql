-- ============================================================
-- Unbound Dashboard — Migração de Banco de Dados
-- Executado pelo update.sh em cada atualização.
-- Todos os statements são idempotentes (seguros para re-execução).
-- ============================================================

-- ----------------------------------------------------------
-- v1.0.2: Índice em alerts
-- ----------------------------------------------------------
SET @exist := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'alerts'
      AND index_name = 'idx_alerts_resolved_at'
);
SET @sql := IF(@exist = 0,
    'CREATE INDEX idx_alerts_resolved_at ON alerts (resolved_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'alerts'
      AND index_name = 'idx_alerts_started_at'
);
SET @sql := IF(@exist = 0,
    'CREATE INDEX idx_alerts_started_at ON alerts (started_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------
-- v1.0.3: Remover índice duplicado em query_logs
-- ----------------------------------------------------------
SET @exist := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'query_logs'
      AND index_name = 'idx_query_logs_domain'
);
SET @sql := IF(@exist > 0,
    'DROP INDEX idx_query_logs_domain ON query_logs',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------
-- v1.0.3: Índice composto (action, timestamp) em query_logs
-- ----------------------------------------------------------
SET @exist := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'query_logs'
      AND index_name = 'idx_action_ts'
);
SET @sql := IF(@exist = 0,
    'CREATE INDEX idx_action_ts ON query_logs (action, timestamp)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------
-- v1.0.3: Coluna blocked_count em daily_stats
-- ----------------------------------------------------------
SET @exist := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'daily_stats'
      AND column_name = 'blocked_count'
);
SET @sql := IF(@exist = 0,
    'ALTER TABLE daily_stats ADD COLUMN blocked_count BIGINT DEFAULT 0 AFTER cache_misses',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------
-- v1.0.3: Backfill blocked_count nos dias já existentes
-- (só atualiza linhas onde blocked_count ainda é 0 e existem logs)
-- ----------------------------------------------------------
UPDATE daily_stats ds
JOIN (
    SELECT DATE(FROM_UNIXTIME(timestamp)) AS d,
           SUM(CASE WHEN action = 'blocked' THEN 1 ELSE 0 END) AS bc,
           COUNT(*) AS tq
    FROM query_logs
    GROUP BY DATE(FROM_UNIXTIME(timestamp))
) agg ON ds.stat_date = agg.d
SET ds.blocked_count = agg.bc,
    ds.total_queries = GREATEST(ds.total_queries, agg.tq)
WHERE ds.blocked_count = 0;

-- Para dias ainda não presentes em daily_stats, inserir
INSERT INTO daily_stats (stat_date, total_queries, cache_hits, cache_misses, blocked_count)
SELECT
    DATE(FROM_UNIXTIME(timestamp)) AS stat_date,
    COUNT(*) AS total_queries,
    0, 0,
    SUM(CASE WHEN action = 'blocked' THEN 1 ELSE 0 END) AS blocked_count
FROM query_logs
GROUP BY DATE(FROM_UNIXTIME(timestamp))
ON DUPLICATE KEY UPDATE
    blocked_count = IF(blocked_count = 0, VALUES(blocked_count), blocked_count);
