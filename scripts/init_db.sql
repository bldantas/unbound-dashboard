-- ============================================
-- Unbound Dashboard - Schema Completo
-- Versão: 1.0.0
-- ============================================

CREATE DATABASE IF NOT EXISTS unbound_dash
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unbound_dash;

-- Tabela de usuários (com login seguro, lockout e recovery)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    failed_logins INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL UNIQUE,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de consultas DNS (comprimido para performance)
CREATE TABLE IF NOT EXISTS query_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    timestamp INT UNSIGNED NOT NULL,
    client_ip VARCHAR(45) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    query_type VARCHAR(10) NOT NULL,
    action VARCHAR(20) NOT NULL,
    INDEX idx_timestamp (timestamp),
    INDEX idx_domain (domain),
    INDEX idx_action_ts (action, timestamp)
) ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estatísticas diárias agregadas
CREATE TABLE IF NOT EXISTS daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL UNIQUE,
    total_queries BIGINT DEFAULT 0,
    cache_hits BIGINT DEFAULT 0,
    cache_misses BIGINT DEFAULT 0,
    blocked_count BIGINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações chave-valor do sistema
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alertas de monitoramento
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) DEFAULT 'warning',
    message TEXT DEFAULT NULL,
    started_at DATETIME NOT NULL,
    resolved_at DATETIME DEFAULT NULL,
    is_dismissed TINYINT(1) DEFAULT 0,
    INDEX idx_alerts_resolved_at (resolved_at),
    INDEX idx_alerts_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blacklist de domínios (bloqueio DNS)
CREATE TABLE IF NOT EXISTS domain_blacklist (
    domain VARCHAR(255) NOT NULL PRIMARY KEY,
    category VARCHAR(50) DEFAULT NULL,
    severity VARCHAR(20) DEFAULT NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações padrão
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('log_retention_days', '7');
