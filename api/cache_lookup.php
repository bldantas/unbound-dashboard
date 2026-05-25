<?php
/**
 * API: lookup detalhado de um domínio no cache do Unbound.
 *
 * POST com {csrf_token, domain}. Read-only (cap blocklist.read equivalente).
 * `unbound-control lookup <domain>` mostra info diagnóstica: rrset
 * presente no cache + TTL + delegation point + DNSSEC chain.
 */
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ShellHelper.php';

use App\Auth;
use App\ShellHelper;

header('Content-Type: application/json; charset=utf-8');

Auth::check();

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

$domain = trim((string) ($_POST['domain'] ?? ''));
if ($domain === '' || strlen($domain) > 253 || !preg_match('/^[a-zA-Z0-9._-]+$/', $domain)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Domínio inválido']);
    exit;
}

$out = [];
$ret = 0;
ShellHelper::exec('/usr/sbin/unbound-control', ['lookup', $domain], $out, $ret, true);
if ($ret !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'unbound-control lookup falhou',
        'detail' => implode("\n", $out),
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'domain' => $domain,
    'output' => implode("\n", $out),
]);
