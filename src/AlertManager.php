<?php

namespace App;

require_once __DIR__ . '/ApiClient.php';

/**
 * AlertManager — espelho legado da classe que era PDO/MariaDB.
 * Migrado pra ApiClient/FastAPI 2026-05-04.
 *
 * Todos os métodos system-level (`checkAndReport`) foram cobertos pelo worker
 * Python `app/workers/alert_checker.py`. Este AlertManager permanece como
 * thin facade pra alerts.php (read/resolve) e sidebar (count).
 */
class AlertManager
{
    /**
     * @deprecated checks system-level rodam no worker Python alert_checker.py.
     * Mantido como no-op pra compat com scripts/cron_alerts.php (cron já desativado).
     */
    public function checkAndReport(): void
    {
        // No-op. Python worker faz CPU/RAM/disk/network/SSH/webserver/no_queries.
    }

    private function jwt(): string
    {
        return $_SESSION['api_jwt'] ?? '';
    }

    public function resolveAlertById(int $id): void
    {
        $jwt = $this->jwt();
        if ($jwt === '') return;
        ApiClient::post("/api/v1/alerts/{$id}/resolve", $jwt);
    }

    public function clearResolvedAlerts(): void
    {
        $jwt = $this->jwt();
        if ($jwt === '') return;
        ApiClient::post('/api/v1/alerts/clear-resolved', $jwt);
    }

    public function getActiveCount(): int
    {
        $jwt = $this->jwt();
        if ($jwt === '') return 0;
        $resp = ApiClient::get('/api/v1/alerts/list', $jwt);
        if (!$resp['ok'] || !is_array($resp['data'])) return 0;
        return (int) ($resp['data']['active_count'] ?? 0);
    }

    public function getHistory(): array
    {
        $jwt = $this->jwt();
        if ($jwt === '') return [];
        $resp = ApiClient::get('/api/v1/alerts/list', $jwt);
        if (!$resp['ok'] || !is_array($resp['data'])) return [];
        return $resp['data']['alerts'] ?? [];
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return $seconds . ' seg';
        if ($seconds < 3600) return floor($seconds / 60) . ' min';
        if ($seconds < 86400) return floor($seconds / 3600) . 'h ' . (floor($seconds / 60) % 60) . 'm';
        return floor($seconds / 86400) . 'd ' . (floor($seconds / 3600) % 24) . 'h';
    }
}
