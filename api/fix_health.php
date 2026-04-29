<?php
require_once dirname(__DIR__) . '/src/Auth.php';
require_once dirname(__DIR__) . '/src/ShellHelper.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Acesso Negado']); exit; }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$output = [];
$returnVar = 0;

// O sudoers deve permitir www-data rodar este shellscript sem senha
\App\ShellHelper::exec('/usr/local/bin/unbound-health-fix.sh', [], $output, $returnVar, true);

$logStr = implode("\n", $output);

if ($returnVar === 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Rotina de correção finalizada com sucesso.',
        'log' => $logStr
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A rotina retornou erro (código ' . $returnVar . ').',
        'log' => $logStr
    ]);
}
