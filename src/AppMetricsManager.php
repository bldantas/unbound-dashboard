<?php

namespace App;

use Exception;

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/LocalDataAdapter.php';
require_once __DIR__ . '/ShellHelper.php';

/**
 * Classe responsável por extrair métricas de serviços a nível de aplicação.
 */
class AppMetricsManager {

    private ApiClient $api;
    private LocalDataAdapter $local;

    public function __construct() {
        $this->api   = new ApiClient();
        $this->local = new LocalDataAdapter();
    }

    /**
     * Retorna métricas do backend v2 (DuckDB/FastAPI).
     * Usa o export local para verificar vitalidade sem chamada HTTP quando possível.
     * Mantém a mesma assinatura para compatibilidade com código existente.
     */
    public function getMariaDBStats(): array {
        // Se o export estiver fresco, a v2 está viva (worker escreveu recentemente)
        if ($this->local->isFresh()) {
            $stats = $this->local->getLiveStats();
            return [
                'status'      => 'online',
                'connections' => 0,
                'queries'     => (int)($stats['total'] ?? 0),
                'slow'        => 0,
            ];
        }

        // Fallback: verificar via HTTP
        try {
            $health = $this->api->getHealth();
            return [
                'status'      => ($health['status'] ?? 'error') === 'ok' ? 'online' : 'offline',
                'connections' => (int)($health['active_connections'] ?? 0),
                'queries'     => (int)($health['total_queries'] ?? 0),
                'slow'        => 0,
            ];
        } catch (Exception $e) {
            return [
                'status'      => 'offline',
                'connections' => 0,
                'queries'     => 0,
                'slow'        => 0,
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
