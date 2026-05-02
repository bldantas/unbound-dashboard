<?php

namespace App;

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/LocalDataAdapter.php';
require_once __DIR__ . '/ServerMonitor.php';
require_once __DIR__ . '/UnboundManager.php';
require_once __DIR__ . '/SecurityMonitor.php';
require_once __DIR__ . '/AppMetricsManager.php';

use Exception;

class AlertManager {
    private ApiClient $api;
    private LocalDataAdapter $local;
    private ServerMonitor $monitor;
    private UnboundManager $unbound;
    private SecurityMonitor $security;
    private AppMetricsManager $appStats;

    const THRESHOLD_CPU = 4.0;
    const THRESHOLD_MEM = 90.0;
    const THRESHOLD_SWAP = 50.0;
    const THRESHOLD_DISK = 90.0;
    const THRESHOLD_ERRORS = 100;
    const THRESHOLD_FAILED_LOGINS = 50;

    public function __construct() {
        $this->api      = new ApiClient();
        $this->local    = new LocalDataAdapter();
        $this->monitor  = new ServerMonitor();
        $this->unbound  = new UnboundManager();
        $this->security = new SecurityMonitor();
        $this->appStats = new AppMetricsManager();
    }

    public function checkAndReport(): void {
        // 1. CPU
        $cpuLoad = $this->monitor->getCpuUsage();
        if ($cpuLoad > self::THRESHOLD_CPU) {
            $this->addAlert('cpu', "Sobrecarga de CPU: Load Average $cpuLoad", 'warning');
        } else {
            $this->resolveAlert('cpu');
        }

        // 2. Memória e Swap
        $mem = $this->monitor->getDetailedMemory();
        if ($mem['percent'] > self::THRESHOLD_MEM) {
            $this->addAlert('memory', "Falta de RAM: {$mem['percent']}% em uso", 'critical');
        } else {
            $this->resolveAlert('memory');
        }

        if ($mem['swap_percent'] > self::THRESHOLD_SWAP) {
            $this->addAlert('swap', "Uso excessivo de Swap: {$mem['swap_percent']}%", 'warning');
        } else {
            $this->resolveAlert('swap');
        }

        // 3. Disco
        $disk = $this->monitor->getDiskUsage();
        if ($disk['percent'] > self::THRESHOLD_DISK) {
            $this->addAlert('disk', "Armazenamento Crítico: {$disk['percent']}% cheio", 'critical');
        } else {
            $this->resolveAlert('disk');
        }

        // 4. Rede
        $net = $this->monitor->getNetworkStats();
        if ($net['errors'] > self::THRESHOLD_ERRORS || $net['drops'] > self::THRESHOLD_ERRORS) {
            $this->addAlert('network', "Instabilidade na Rede: {$net['errors']} erros e {$net['drops']} drops", 'warning');
        }

        // 5. Segurança
        $failedLogins = $this->security->getFailedLogins();
        if ($failedLogins > self::THRESHOLD_FAILED_LOGINS) {
            $this->addAlert('security', "Alto nível de falhas SSH hoje: $failedLogins tentativas.", 'critical');
        } else {
            $this->resolveAlert('security');
        }

        // 6. API v2 / DuckDB
        try {
            $this->api->healthz();
            $this->resolveAlert('database');
        } catch (Exception $e) {
            $this->addAlert('database', 'API v2 / DuckDB inacessível!', 'critical');
        }

        // 7. WebServer
        $webStatus = $this->appStats->getWebServerStatus();
        if ($webStatus === 'offline') {
            $this->addAlert('webserver', 'O Servidor Web não foi detectado!', 'critical');
        } else {
            $this->resolveAlert('webserver');
        }

        // 8. Unbound
        if (!$this->unbound->isServiceRunning()) {
            $this->addAlert('service', 'O serviço Unbound DNS está OFFLINE!', 'critical');
        } else {
            $this->resolveAlert('service');
        }
    }

    private function addAlert(string $type, string $message, string $severity): void {
        try {
            // Leitura de deduplicação: usa export local se disponível, senão ApiClient
            if ($this->local->isAvailable()) {
                $localAlerts = $this->local->getAlerts()['alerts'];
                foreach ($localAlerts as $a) {
                    if (($a['type'] ?? '') === $type && !($a['is_read'] ?? true)) {
                        return;
                    }
                }
            } else {
                $alerts = $this->api->getAlerts(true);
                foreach ($alerts as $a) {
                    if (($a['type'] ?? '') === $type && !($a['is_read'] ?? true)) {
                        return;
                    }
                }
            }
            $this->api->createAlert($type, $message, $severity);
        } catch (Exception $e) {
            // não bloqueia execução
        }
    }

    private function resolveAlert(string $type): void {
        try {
            // Leitura: usa export local se disponível, senão ApiClient
            if ($this->local->isAvailable()) {
                $localAlerts = $this->local->getAlerts()['alerts'];
                foreach ($localAlerts as $a) {
                    if (($a['type'] ?? '') === $type && !($a['is_read'] ?? true)) {
                        $this->api->markAlertRead((int)$a['id']);
                    }
                }
            } else {
                $alerts = $this->api->getAlerts(true);
                foreach ($alerts as $a) {
                    if (($a['type'] ?? '') === $type && !($a['is_read'] ?? true)) {
                        $this->api->markAlertRead((int)$a['id']);
                    }
                }
            }
        } catch (Exception $e) {
            // ignora
        }
    }

    public function resolveAlertById(int $id): void {
        try {
            $this->api->markAlertRead($id);
        } catch (Exception $e) {}
    }

    public function clearResolvedAlerts(): void {
        try {
            $alerts = $this->api->getAlerts();
            foreach ($alerts as $a) {
                if ($a['is_read'] ?? false) {
                    $this->api->deleteAlert((int)$a['id']);
                }
            }
        } catch (Exception $e) {}
    }

    public function getActiveCount(): int {
        // Preferência: export local (sem HTTP); fallback para ApiClient
        if ($this->local->isAvailable()) {
            return $this->local->getAlerts()['unread_count'];
        }
        try {
            return $this->api->getAlertsCount();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getHistory(): array {
        try {
            return $this->api->getAlerts();
        } catch (Exception $e) {
            // Fallback: retorna os não-lidos do export local
            if ($this->local->isAvailable()) {
                return $this->local->getAlerts()['alerts'];
            }
            return [];
        }
    }

    public static function formatDuration(int $seconds): string {
        if ($seconds < 60) return $seconds . ' seg';
        if ($seconds < 3600) return floor($seconds / 60) . ' min';
        if ($seconds < 86400) return floor($seconds / 3600) . 'h ' . (floor($seconds / 60) % 60) . 'm';
        return floor($seconds / 86400) . 'd ' . (floor($seconds / 3600) % 24) . 'h';
    }
}
