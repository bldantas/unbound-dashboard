<?php
/**
 * API: flush total do cache do Unbound.
 *
 * POST com {csrf_token}. Admin-only.
 * `unbound-control flush_zone .` — esvazia rrset+msg cache inteiros sem
 * reiniciar o daemon. Operação rápida; impacto: hit ratio cai pra ~0%
 * temporariamente e Unbound vai consultar upstream pra cada query nova.
 */
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ShellHelper.php';

use App\Auth;
use App\ShellHelper;

header('Content-Type: application/json; charset=utf-8');

Auth::check();
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
    exit;
}

$out = [];
$ret = 0;
ShellHelper::exec('/usr/sbin/unbound-control', ['flush_zone', '.'], $out, $ret, true);
if ($ret !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'unbound-control flush_zone . falhou',
        'detail' => implode("\n", $out),
    ]);
    exit;
}

// Invalida o dump cacheado.
@unlink(__DIR__ . '/../src/data/tmp/unbound_cache_dump.json');

// Output típico: "ok removed N rrsets, M messages and K key entries"
echo json_encode([
    'success' => true,
    'message' => 'Cache do Unbound esvaziado.',
    'output' => trim(implode("\n", $out)),
]);
