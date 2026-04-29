<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ServerMonitor.php';
require_once __DIR__ . '/../src/SecurityMonitor.php';
require_once __DIR__ . '/../src/AppMetricsManager.php';

use App\Auth;

header('Content-Type: application/json; charset=UTF-8');

Auth::check();
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Acesso negado']);
    exit;
}

try {
    $cacheTtl = 15;
    $cacheKey = 'alerts_metrics_cache';
    $cacheTsKey = 'alerts_metrics_cache_ts';

    $now = time();
    $cacheTs = (int) ($_SESSION[$cacheTsKey] ?? 0);
    if ($cacheTs > 0 && ($now - $cacheTs) < $cacheTtl && isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
        echo json_encode(['status' => 'success', 'data' => $_SESSION[$cacheKey]]);
        exit;
    }

    $monitor = new \App\ServerMonitor();
    $security = new \App\SecurityMonitor();
    $appMetrics = new \App\AppMetricsManager();

    $payload = [
        'cpu' => $monitor->getDetailedCpu(),
        'memory' => $monitor->getDetailedMemory(),
        'disk' => $monitor->getDiskUsage(),
        'network' => $monitor->getNetworkStats(),
        'db' => $appMetrics->getMariaDBStats(),
        'web_status' => $appMetrics->getWebServerStatus(),
        'failed_logins' => $security->getFailedLogins(),
        'open_ports' => $security->getListeningPortsCount(),
    ];

    $_SESSION[$cacheKey] = $payload;
    $_SESSION[$cacheTsKey] = $now;

    echo json_encode(['status' => 'success', 'data' => $payload]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Falha ao carregar métricas de alerta']);
}
