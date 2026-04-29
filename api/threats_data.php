<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';

use App\Auth;

header('Content-Type: application/json; charset=UTF-8');

Auth::check();
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Acesso negado']);
    exit;
}

$limitParam = $_GET['limit'] ?? '10';
if ($limitParam === 'todos') {
    $threatLimit = 1000;
} else {
    $allowedLimits = [10, 20, 50, 100];
    $parsedLimit = (int) $limitParam;
    $threatLimit = in_array($parsedLimit, $allowedLimits, true) ? $parsedLimit : 10;
}

$cacheKey = 'threats_data_cache_' . $threatLimit;
$cacheTsKey = $cacheKey . '_ts';
$cacheTtl = 20;
$now = time();

if (isset($_SESSION[$cacheKey], $_SESSION[$cacheTsKey]) && ($now - (int) $_SESSION[$cacheTsKey]) < $cacheTtl) {
    echo json_encode(['status' => 'success', 'data' => $_SESSION[$cacheKey]]);
    exit;
}

try {
    $db = \App\Database::getInstance();

    // Totais rápidos via daily_stats (pré-agregado, sem full scan em query_logs)
    $dailyTotals = $db->query("
        SELECT COALESCE(SUM(blocked_count), 0) AS blocked, COALESCE(SUM(total_queries), 0) AS total
        FROM daily_stats
    ")->fetch();
    $totalThreats = (int) $dailyTotals['blocked'];
    $totalQueries = (int) $dailyTotals['total'];

    // Fallback: se daily_stats ainda não foi populado, usa information_schema (aproximado, sem full scan)
    if ($totalQueries === 0) {
        $totalQueries = (int) $db->query(
            "SELECT table_rows FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'query_logs'"
        )->fetchColumn();
    }

    $totalBlacklist = (int) $db->query("SELECT COUNT(*) FROM domain_blacklist")->fetchColumn();
    $threatRatio = $totalQueries > 0 ? round(($totalThreats / $totalQueries) * 100, 2) : 0.0;

    // Ameaças recentes — rápido com índice composto (action, timestamp)
    $recentThreats = $db->query("
        SELECT l.timestamp, l.client_ip, l.domain, l.action, b.category, b.severity
        FROM (
            SELECT timestamp, client_ip, domain, action
            FROM query_logs
            WHERE action = 'blocked'
            ORDER BY timestamp DESC
            LIMIT $threatLimit
        ) l
        LEFT JOIN domain_blacklist b ON l.domain = b.domain
    ")->fetchAll();

    // Top clientes bloqueados — beneficia do índice (action, timestamp)
    $topBlockedClients = $db->query("
        SELECT client_ip, COUNT(*) as count
        FROM query_logs
        WHERE action = 'blocked'
        GROUP BY client_ip
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll();

    // Top domínios bloqueados — beneficia do índice (action, timestamp)
    $topBlockedDomains = $db->query("
        SELECT l.domain, COUNT(*) as count
        FROM query_logs l
        JOIN domain_blacklist b ON l.domain = b.domain
        WHERE l.action = 'blocked'
        GROUP BY l.domain
        ORDER BY count DESC
        LIMIT 10
    ")->fetchAll();

    $recent = [];
    foreach ($recentThreats as $t) {
        $ts = strtotime((string) ($t['timestamp'] ?? ''));
        if ($ts === false) {
            $ts = time();
        }

        $recent[] = [
            'time' => date('H:i:s', $ts),
            'date' => date('d/m/y', $ts),
            'client_ip' => (string) ($t['client_ip'] ?? ''),
            'domain' => (string) ($t['domain'] ?? ''),
            'category' => (string) ($t['category'] ?? 'Geral'),
            'severity' => (string) ($t['severity'] ?? ''),
            'action' => (string) ($t['action'] ?? 'blocked'),
        ];
    }

    $topClients = array_map(static function (array $row): array {
        return [
            'label' => (string) ($row['client_ip'] ?? '---'),
            'count' => (int) ($row['count'] ?? 0),
        ];
    }, $topBlockedClients);

    $topDomains = array_map(static function (array $row): array {
        return [
            'label' => (string) ($row['domain'] ?? '---'),
            'count' => (int) ($row['count'] ?? 0),
        ];
    }, $topBlockedDomains);

    $payload = [
        'totals' => [
            'blacklist' => $totalBlacklist,
            'threats' => $totalThreats,
            'queries' => $totalQueries,
            'ratio' => $threatRatio,
        ],
        'top' => [
            'domains' => $topDomains,
            'clients' => $topClients,
        ],
        'recent' => $recent,
    ];

    $_SESSION[$cacheKey] = $payload;
    $_SESSION[$cacheTsKey] = $now;

    echo json_encode(['status' => 'success', 'data' => $payload]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Falha ao carregar dados de ameaças']);
}
