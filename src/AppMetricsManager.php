<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

/**
 * Métricas de serviços a nível de aplicação.
 *
 * MariaDB foi removido em 2026-05-04; getMariaDBStats() retorna sempre 'offline'
 * pra preservar compat com o frontend de alerts.php. Substituir esse widget por
 * status do api_service/DuckDB é tarefa futura.
 */
class AppMetricsManager {

    public function getMariaDBStats(): array {
        return [
            'status' => 'offline',
            'connections' => 0,
            'queries' => 0,
            'slow' => 0,
        ];
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
