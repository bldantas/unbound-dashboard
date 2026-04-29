<?php
/**
 * API para consulta da lista de domínios bloqueados pela ANATEL (Anablock).
 * Suporta busca por termo, paginação e ordenação.
 */
require_once __DIR__ . '/../src/Auth.php';

use App\Auth;

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$blocklistFile = __DIR__ . '/../src/data/official_blocklist.conf';

if (!file_exists($blocklistFile)) {
    echo json_encode([
        'success' => false,
        'error' => 'Arquivo de blocklist não encontrado.',
        'domains' => [],
        'total' => 0,
        'filtered' => 0,
    ]);
    exit;
}

// Parâmetros de requisição
$search   = trim($_GET['search'] ?? '');
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = min(100, max(10, intval($_GET['per_page'] ?? 50)));
$tldFilter = trim($_GET['tld'] ?? '');

// Cache em memória: ler e parsear o arquivo
$cacheFile = __DIR__ . '/../src/data/tmp/blocklist_cache.json';
$cacheMaxAge = 3600; // 1 hora

$domains = [];
$stats = ['total' => 0, 'tlds' => []];

// Tentar usar cache
$useCache = false;
if (file_exists($cacheFile)) {
    $cacheMtime = filemtime($cacheFile);
    $blocklistMtime = filemtime($blocklistFile);
    if ($cacheMtime > $blocklistMtime && (time() - $cacheMtime) < $cacheMaxAge) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['domains'])) {
            $domains = $cached['domains'];
            $stats = $cached['stats'] ?? $stats;
            $useCache = true;
        }
    }
}

if (!$useCache) {
    // Parsear o arquivo da blocklist
    $lines = file($blocklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $tldCounts = [];

    foreach ($lines as $line) {
        $line = trim($line);
        // Formato: local-zone: "dominio.com" always_nxdomain
        if (preg_match('/^local-zone:\s*"([^"]+)"\s+always_nxdomain/', $line, $matches)) {
            $domain = strtolower($matches[1]);
            $domains[] = $domain;

            // Extrair TLD
            $parts = explode('.', $domain);
            $tld = end($parts);
            if (!isset($tldCounts[$tld])) {
                $tldCounts[$tld] = 0;
            }
            $tldCounts[$tld]++;
        }
    }

    arsort($tldCounts);
    $stats = [
        'total' => count($domains),
        'tlds' => $tldCounts,
    ];

    // Salvar cache
    $tmpDir = dirname($cacheFile);
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode(['domains' => $domains, 'stats' => $stats]));
}

// Aplicar filtros
$filtered = $domains;

if ($search !== '') {
    $searchLower = strtolower($search);
    $filtered = array_filter($filtered, function($domain) use ($searchLower) {
        return strpos($domain, $searchLower) !== false;
    });
    $filtered = array_values($filtered);
}

if ($tldFilter !== '') {
    $tldLower = strtolower($tldFilter);
    $filtered = array_filter($filtered, function($domain) use ($tldLower) {
        return substr($domain, -strlen($tldLower) - 1) === '.' . $tldLower 
            || $domain === $tldLower;
    });
    $filtered = array_values($filtered);
}

$totalFiltered = count($filtered);
$totalPages = max(1, ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Paginar
$paginatedDomains = array_slice($filtered, $offset, $perPage);

// Top TLDs (top 15)
$topTlds = array_slice($stats['tlds'], 0, 15, true);

echo json_encode([
    'success'       => true,
    'domains'       => $paginatedDomains,
    'total'         => $stats['total'],
    'filtered'      => $totalFiltered,
    'page'          => $page,
    'per_page'      => $perPage,
    'total_pages'   => $totalPages,
    'search'        => $search,
    'tld_filter'    => $tldFilter,
    'top_tlds'      => $topTlds,
]);
