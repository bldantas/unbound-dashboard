-- =========================================================
-- Script de Configuração do Banco de Dados MariaDB/MySQL
-- Para Unbound Dashboard
-- =========================================================
-- Este script deve ser executado como root:
-- mysql -u root -p < setup_database.sql
-- =========================================================

-- Variáveis de configuração
SET @db_name = 'unbound_dash';
SET @db_user = 'unbounddb';
SET @db_pass = 'unbounddash';

-- =========================================================
-- 1. CRIAR DATABASE
-- =========================================================
CREATE DATABASE IF NOT EXISTS `unbound_dash` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =========================================================
-- 2. REMOVER USUÁRIOS ANTIGOS (se existirem)
-- =========================================================
DROP USER IF EXISTS 'unbounddb'@'localhost';
DROP USER IF EXISTS 'unbounddb'@'127.0.0.1';
DROP USER IF EXISTS 'unbounddb'@'%';

-- =========================================================
-- 3. CRIAR NOVO USUÁRIO COM AUTENTICAÇÃO NATIVA
-- =========================================================
-- Usando mysql_native_password para evitar erros SQLSTATE[HY000] [1698]
CREATE USER 'unbounddb'@'localhost' IDENTIFIED WITH mysql_native_password BY 'unbounddash';
CREATE USER 'unbounddb'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'unbounddash';
CREATE USER 'unbounddb'@'%' IDENTIFIED WITH mysql_native_password BY 'unbounddash';

-- =========================================================
-- 4. CONCEDER PRIVILÉGIOS GLOBAIS COM GRANT OPTION
-- =========================================================
-- Isso permite que o usuário crie esquemas e outros usuários
GRANT ALL PRIVILEGES ON `unbound_dash`.* TO 'unbounddb'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `unbound_dash`.* TO 'unbounddb'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `unbound_dash`.* TO 'unbounddb'@'%' WITH GRANT OPTION;

-- Privilegios globais para operações de manutenção
GRANT PROCESS ON *.* TO 'unbounddb'@'localhost';
GRANT PROCESS ON *.* TO 'unbounddb'@'127.0.0.1';
GRANT PROCESS ON *.* TO 'unbounddb'@'%';

-- =========================================================
-- 5. APLICAR ALTERAÇÕES
-- =========================================================
FLUSH PRIVILEGES;

-- =========================================================
-- 6. USAR O DATABASE E CRIAR TABELAS
-- =========================================================
USE `unbound_dash`;

-- Tabela: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: dns_queries
CREATE TABLE IF NOT EXISTS `dns_queries` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `qname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `qtype` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `answer_count` int NOT NULL DEFAULT 0,
  `answer_rcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `remote_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cached` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_qname` (`qname`),
  KEY `idx_remote_ip` (`remote_ip`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: blocklist_entries
CREATE TABLE IF NOT EXISTS `blocklist_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: alerts
CREATE TABLE IF NOT EXISTS `alerts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: system_metrics
CREATE TABLE IF NOT EXISTS `system_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metric_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_metric_name` (`metric_name`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. VERIFICAÇÃO FINAL
-- =========================================================
SELECT 'Banco de dados configurado com sucesso!' AS status;
SELECT CONCAT('Usuário: ', user) AS mysql_users FROM mysql.user WHERE user = 'unbounddb';
SHOW TABLES IN `unbound_dash`;
