<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

/**
 * Métricas de serviços a nível de aplicação consumidas pelo painel `alerts.php`
 * via `api/alerts_metrics.php`.
 *
 * Após o tear-down do MariaDB (2026-05-04), o widget "Banco de Dados" foi
 * substituído por status do `unbound-dashboard-api` (FastAPI) + tamanho do
 * arquivo DuckDB + status do Redis. `getMariaDBStats()` é mantido como stub
 * fixo apenas pra callers legacy.
 */
class AppMetricsManager
{
    private const DUCKDB_PATH = '/var/lib/unbound-dashboard/unbound_dash.duckdb';
    private const HEALTHZ_URL = 'http://127.0.0.1:8001/api/v1/healthz';

    public function getMariaDBStats(): array
    {
        return [
            'status' => 'offline',
            'connections' => 0,
            'queries' => 0,
            'slow' => 0,
        ];
    }

    /**
     * Status do unbound-dashboard-api.service (FastAPI) + smoke /healthz.
     * Retorna {status: 'active'|'inactive'|..., healthz: bool, version: ?str}
     */
    public function getApiServiceStatus(): array
    {
        $out = [];
        $ret = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'unbound-dashboard-api.service'], $out, $ret, false);
        $status = trim($out[0] ?? 'unknown');

        $healthz = false;
        $version = null;
        $ch = curl_init(self::HEALTHZ_URL);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 2,
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && is_string($body)) {
                $healthz = true;
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['version'])) {
                    $version = (string) $decoded['version'];
                }
            }
        }

        return [
            'status' => $status,
            'healthz' => $healthz,
            'version' => $version,
        ];
    }

    /**
     * Estado do arquivo DuckDB. Retorna {exists, size_bytes, size_human, mtime}.
     */
    public function getDuckDBStatus(): array
    {
        if (!is_readable(self::DUCKDB_PATH)) {
            return [
                'exists' => false,
                'size_bytes' => 0,
                'size_human' => '--',
                'mtime' => null,
            ];
        }
        $bytes = (int) @filesize(self::DUCKDB_PATH);
        $mtime = (int) @filemtime(self::DUCKDB_PATH);

        return [
            'exists' => true,
            'size_bytes' => $bytes,
            'size_human' => $this->humanSize($bytes),
            'mtime' => $mtime,
        ];
    }

    /**
     * Status do redis-server.service via systemctl is-active.
     */
    public function getRedisStatus(): array
    {
        $out = [];
        $ret = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'redis-server.service'], $out, $ret, false);
        return ['status' => trim($out[0] ?? 'unknown')];
    }

    public function getWebServerStatus(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'online';
        }
        $ret = 0;

        foreach ([
            ['nginx', 'nginx_active'],
            ['apache2', 'apache2_active'],
            ['httpd', 'httpd_active'],
        ] as [$svc, $label]) {
            $out = [];
            \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', $svc], $out, $ret, false);
            if (trim(implode('', $out)) === 'active') {
                return $label;
            }
        }
        return 'offline';
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return sprintf('%.1f %s', $bytes / (1024 ** $i), $units[$i]);
    }
}
