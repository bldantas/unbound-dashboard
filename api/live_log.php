<?php
require_once '../src/Auth.php';
require_once '../src/ShellHelper.php';
\App\Auth::check();
if (!\App\Auth::isAdmin()) {
    http_response_code(403);
    exit;
}
header('Content-Type: application/json');

$logFile = '/var/log/unbound.log';
$output = [];
\App\ShellHelper::exec('/usr/bin/tail', ['-n', '300', $logFile], $output, $tmpRet, true);
if (empty($output)) {
    \App\ShellHelper::exec('/usr/bin/journalctl', ['-u', 'unbound', '-n', '300', '--no-pager'], $output, $tmpRet, true);
}

$queries = [];
foreach ($output as $line) {
    // Formato Resposta Unbound: "info: 192.168.1.10 google.com. A IN NOERROR 0.015000 1 45"
    // Replies têm RCODE + tempo após "IN" — checamos primeiro por ser o padrão mais específico
    if (preg_match('/info:\s+([\da-fA-F\.\:]+)\s+(\S+)\s+([A-Z0-9]+)\s+IN\s+([A-Z]+)\s+([0-9\.]+)/', $line, $m)) {
        $queries[] = [
            'raw' => $line,
            'type' => 'reply',
            'client' => $m[1],
            'domain' => rtrim($m[2], '.'),
            'qtype' => $m[3],
            'rcode' => $m[4],
            'time' => $m[5]
        ];
    }
    // Formato Consulta Unbound: "info: 192.168.1.10 google.com. A IN" (linha termina após IN ou só tem espaços)
    elseif (preg_match('/info:\s+([\da-fA-F\.\:]+)\s+(\S+)\s+([A-Z0-9]+)\s+IN\s*$/', $line, $m)) {
        $queries[] = [
            'raw' => $line,
            'type' => 'query',
            'client' => $m[1],
            'domain' => rtrim($m[2], '.'),
            'qtype' => $m[3]
        ];
    }
}

echo json_encode(['status' => 'success', 'data' => $queries]);
