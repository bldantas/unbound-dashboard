<?php

namespace App;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ServerMonitor.php';
require_once __DIR__ . '/UnboundManager.php';
require_once __DIR__ . '/SecurityMonitor.php';
require_once __DIR__ . '/AppMetricsManager.php';

use PDO;
use Exception;

class AlertManager {
    private PDO $db;
    private ServerMonitor $monitor;
    private UnboundManager $unbound;
    private SecurityMonitor $security;
    private AppMetricsManager $appStats;

    const THRESHOLD_CPU = 4.0;     // load avg 1 min
    const THRESHOLD_MEM = 90.0;    // 90% em uso
    const THRESHOLD_SWAP = 50.0;   // 50% de swap 
    const THRESHOLD_DISK = 90.0;   // 90% em uso
    const THRESHOLD_ERRORS = 100;  // 100 erros/drops de rede acumulados
    const THRESHOLD_FAILED_LOGINS = 50; // Quantidade de falhas ssh no journal
    const THRESHOLD_DB_CONNS = 200;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->monitor = new ServerMonitor();
        $this->unbound = new UnboundManager();
        $this->security = new SecurityMonitor();
        $this->appStats = new AppMetricsManager();
    }

    public function checkAndReport(): void {
        // 1. Verificar CPU (agora usando load average 1)
        $cpuLoad = $this->monitor->getCpuUsage();
        if ($cpuLoad > self::THRESHOLD_CPU) {
            $this->addAlert('cpu', "Sobrecarga de CPU: Load Average $cpuLoad", 'warning');
        } else {
            $this->resolveAlert('cpu');
        }

        // 2. Verificar Memória e Swap
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

        // 3. Verificar Disco
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

        // 6. DB e WebServer
        $dbStats = $this->appStats->getMariaDBStats();
        if ($dbStats['status'] === 'offline') {
            $this->addAlert('database', 'Serviço MariaDB Offline ou Inacessível!', 'critical');
        } elseif ($dbStats['connections'] > self::THRESHOLD_DB_CONNS) {
            $this->addAlert('database', "Sobrecarga no BD: {$dbStats['connections']} threads ativas", 'warning');
        } else {
            $this->resolveAlert('database');
        }

        $webStatus = $this->appStats->getWebServerStatus();
        if ($webStatus === 'offline') {
            $this->addAlert('webserver', 'O Servidor Web não foi detectado!', 'critical');
        } else {
            $this->resolveAlert('webserver');
        }

        // 7. Unbound
        if (!$this->unbound->isServiceRunning()) {
            $this->addAlert('service', 'O serviço Unbound DNS está OFFLINE!', 'critical');
        } else {
            $this->resolveAlert('service');
        }
    }

    private function addAlert(string $type, string $message, string $severity): void {
        $stmt = $this->db->prepare("SELECT id FROM alerts WHERE type = ? AND resolved_at IS NULL");
        $stmt->execute([$type]);
        if ($stmt->fetch()) return;

        $stmt = $this->db->prepare("INSERT INTO alerts (type, message, severity, started_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$type, $message, $severity]);
    }

    private function resolveAlert(string $type): void {
        $stmt = $this->db->prepare("UPDATE alerts SET resolved_at = NOW() WHERE type = ? AND resolved_at IS NULL");
        $stmt->execute([$type]);
    }

    public function resolveAlertById(int $id): void {
        $stmt = $this->db->prepare("UPDATE alerts SET resolved_at = NOW() WHERE id = ? AND resolved_at IS NULL");
        $stmt->execute([$id]);
    }

    public function clearResolvedAlerts(): void {
        $this->db->query("DELETE FROM alerts WHERE resolved_at IS NOT NULL");
    }

    public function getActiveCount(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM alerts WHERE resolved_at IS NULL");
        return (int)$stmt->fetchColumn();
    }

    public function getHistory(): array {
        $stmt = $this->db->query("SELECT *, 
            TIMESTAMPDIFF(SECOND, started_at, IFNULL(resolved_at, NOW())) as duration_secs 
            FROM alerts ORDER BY started_at DESC LIMIT 100");
        return $stmt->fetchAll();
    }

    public static function formatDuration(int $seconds): string {
        if ($seconds < 60) return $seconds . " seg";
        if ($seconds < 3600) return floor($seconds / 60) . " min";
        if ($seconds < 86400) return floor($seconds / 3600) . "h " . (floor($seconds / 60) % 60) . "m";
        return floor($seconds / 86400) . "d " . (floor($seconds / 3600) % 24) . "h";
    }
}
