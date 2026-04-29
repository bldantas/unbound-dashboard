<?php

namespace App;

use PDO;
use Exception;

require_once __DIR__ . '/ShellHelper.php';

/**
 * Classe responsável por extrair métricas de serviços a nível de aplicação.
 */
class AppMetricsManager {

    private PDO $db;

    public function __construct() {
        require_once __DIR__ . '/Database.php';
        $this->db = Database::getInstance();
    }

    public function getMariaDBStats(): array {
        try {
            $stmt = $this->db->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected', 'Queries', 'Slow_queries')");
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return [
                'status' => 'online',
                'connections' => (int)($results['Threads_connected'] ?? 0),
                'queries' => (int)($results['Queries'] ?? 0),
                'slow' => (int)($results['Slow_queries'] ?? 0)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'offline',
                'connections' => 0,
                'queries' => 0,
                'slow' => 0
            ];
        }
    }

    public function getWebServerStatus(): string {
        if (PHP_OS_FAMILY === 'Windows') return 'online';

        $ret = 0;

        $output = [];
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'nginx'], $output, $ret, false);
        if (trim(implode('', $output)) === 'active') {
            return 'nginx_active';
        }

        $output = [];
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'apache2'], $output, $ret, false);
        if (trim(implode('', $output)) === 'active') {
            return 'apache2_active';
        }

        $output = [];
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'httpd'], $output, $ret, false);
        if (trim(implode('', $output)) === 'active') {
            return 'httpd_active';
        }

        return 'offline';
    }
}
