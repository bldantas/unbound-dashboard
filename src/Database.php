<?php

namespace App;

require_once __DIR__ . '/Environment.php';

/**
 * Database — stub de compatibilidade.
 *
 * Esta classe existia para conectar ao MariaDB.
 * O dashboard agora usa a API v2 (FastAPI/DuckDB) via ApiClient.
 * Scripts legados (cron_alerts, aggregate_stats, log_ingester) que ainda
 * chamam Database::getInstance() receberão PDO conectado ao MariaDB
 * somente se as variáveis DB_* estiverem definidas no ambiente;
 * caso contrário, uma RuntimeException será lançada.
 */

use PDO;
use PDOException;

$_sysTz = '';
if (file_exists('/etc/timezone')) {
    $_sysTz = trim(file_get_contents('/etc/timezone'));
} elseif (is_link('/etc/localtime')) {
    $target = readlink('/etc/localtime');
    if (preg_match('/zoneinfo\/(.*)$/', $target, $m)) {
        $_sysTz = trim($m[1]);
    }
}
if (!empty($_sysTz) && in_array($_sysTz, timezone_identifiers_list())) {
    date_default_timezone_set($_sysTz);
}

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host    = Environment::get('DB_HOST', '');
            $db      = Environment::get('DB_NAME', '');
            $user    = Environment::get('DB_USER', '');
            $pass    = Environment::get('DB_PASS', '');
            $charset = Environment::get('DB_CHARSET', 'utf8mb4');

            if (empty($host) || empty($db)) {
                throw new \RuntimeException(
                    'Database::getInstance() foi chamado mas DB_HOST/DB_NAME não estão configurados. ' .
                    'O dashboard agora usa ApiClient para comunicação com o backend v2 (DuckDB).'
                );
            }

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
