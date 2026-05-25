<?php
/**
 * API: reload da config do Unbound sem restart.
 *
 * POST com {csrf_token}. Admin-only.
 * `unbound-control reload` releu o /etc/unbound/unbound.conf inteiro sem
 * derrubar o socket. Operação ~instantânea e PRESERVA o cache em memória
 * — diferente de restart que zera tudo.
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
ShellHelper::exec('/usr/sbin/unbound-control', ['reload'], $out, $ret, true);
if ($ret !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'unbound-control reload falhou',
        'detail' => implode("\n", $out),
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Unbound recarregou config sem perder cache.',
    'output' => trim(implode("\n", $out)),
]);
