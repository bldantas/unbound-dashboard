<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/LocalDataAdapter.php';
require_once __DIR__ . '/../src/Environment.php';

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

$cacheKey = 'threats_data_v2_' . $threatLimit;
$cacheTsKey = $cacheKey . '_ts';
$cacheTtl = 20;
$now = time();

if (isset($_SESSION[$cacheKey], $_SESSION[$cacheTsKey]) && ($now - (int) $_SESSION[$cacheTsKey]) < $cacheTtl) {
    echo json_encode(['status' => 'success', 'data' => $_SESSION[$cacheKey]]);
    exit;
}

try {
    $local = new \App\LocalDataAdapter();

    // Contagem da blocklist a partir de blocklist_counts.json
    $blJson  = @file_get_contents(__DIR__ . '/../data/blocklist_counts.json');
    $blData  = $blJson ? @json_decode($blJson, true) : [];
    $totalBlacklist = ((int)($blData['adware']   ?? 0))
                    + ((int)($blData['phishing'] ?? 0))
                    + ((int)($blData['judicial'] ?? 0));

    // Fonte de dados: export local (sem HTTP) com fallback para ApiClient
    if ($local->isAvailable()) {
        $blockedLogs  = $local->getBlockedLogs(max($threatLimit, 500));
        $liveStats    = $local->getLiveStats();
        $totalBlocked = count($blockedLogs);
        $totalQueries = (int) ($liveStats['total'] ?? 0);
    } else {
        $api          = new \App\ApiClient();
        $fetchLimit   = max($threatLimit, 500);
        $blockedLogs  = $api->getQueryLogs($fetchLimit, 'blocked');
        $liveStats    = $api->getLiveStats(168);
        $totalBlocked = count($blockedLogs);
        $totalQueries = (int) ($liveStats['total'] ?? 0);
    }

    $blockRate = ($totalQueries > 0)
        ? round(($totalBlocked / $totalQueries) * 100, 2)
        : 0.0;

    // Agregação client-side de top domínios e clientes
    $domainCounts = [];
    $clientCounts = [];
    foreach ($blockedLogs as $log) {
        $d = (string) ($log['domain']    ?? '');
        $c = (string) ($log['client_ip'] ?? '');
        if ($d !== '') $domainCounts[$d] = ($domainCounts[$d] ?? 0) + 1;
        if ($c !== '') $clientCounts[$c] = ($clientCounts[$c] ?? 0) + 1;
    }
    arsort($domainCounts);
    arsort($clientCounts);

    $topDomains = [];
    foreach (array_slice($domainCounts, 0, 10, true) as $domain => $count) {
        $topDomains[] = ['label' => $domain, 'count' => $count];
    }

    $topClients = [];
    foreach (array_slice($clientCounts, 0, 10, true) as $ip => $count) {
        $topClients[] = ['label' => $ip, 'count' => $count];
    }

    // Logs recentes limitados ao pedido do usuário
    $recent = [];
    foreach (array_slice($blockedLogs, 0, $threatLimit) as $log) {
        $ts = (int) ($log['timestamp'] ?? 0);
        $recent[] = [
            'time'      => $ts > 0 ? date('H:i:s', $ts) : '--:--:--',
            'date'      => $ts > 0 ? date('d/m/y', $ts)  : '--/--/--',
            'client_ip' => (string) ($log['client_ip'] ?? ''),
            'domain'    => (string) ($log['domain']    ?? ''),
            'category'  => 'Bloqueado',
            'severity'  => '',
            'action'    => 'blocked',
        ];
    }

    $payload = [
        'totals' => [
            'blacklist' => $totalBlacklist,
            'threats'   => $totalBlocked,
            'queries'   => $totalQueries,
            'ratio'     => $blockRate,
        ],
        'top' => [
            'domains' => $topDomains,
            'clients' => $topClients,
        ],
        'recent' => $recent,
    ];

    $_SESSION[$cacheKey]   = $payload;
    $_SESSION[$cacheTsKey] = $now;

    echo json_encode(['status' => 'success', 'data' => $payload]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Falha ao carregar dados de ameaças']);
}
