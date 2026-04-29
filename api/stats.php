<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/StatsManager.php';

\App\Auth::check();

try {
    $statsManager = new \App\StatsManager();
    $statsManager->ensureFreshCache();
    $metrics = $statsManager->getProcessedMetrics();
    $charts = $statsManager->getDashboardChartData();

    echo json_encode(array_merge($metrics, [
        'metrics' => $metrics,
        'charts' => $charts,
    ]));
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
