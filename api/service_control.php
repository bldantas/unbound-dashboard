<?php
require_once dirname(__DIR__) . '/src/Auth.php';
require_once dirname(__DIR__) . '/src/UnboundManager.php';
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/ShellHelper.php';

use App\Auth;
use App\ShellHelper;
use App\UnboundManager;
use App\Database;

Auth::check();
if (!\App\Auth::isAdmin()) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Acesso Negado']); exit; }

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'update_blacklist') {
    \App\ShellHelper::shell('php ' . escapeshellarg(dirname(__DIR__) . '/scripts/update_blacklist.php') . ' > /dev/null 2>&1 &', $tmpOutput, $tmpReturn);
    echo json_encode(['success' => true, 'message' => 'Sincronização iniciada em segundo plano. Poderá levar até 1 minuto.']);
    exit;
}

if ($action === 'change_blacklist_source') {
    $src = $_POST['source'] ?? 'stevenblack';
    $db = \App\Database::getInstance();
    $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('blacklist_source', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$src, $src]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'sync_status') {
    $file = dirname(__DIR__) . '/src/data/tmp/blacklist_progress.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        echo json_encode(['success' => true, 'status' => $data['status'] ?? 'idle', 'progress' => $data['progress'] ?? 0]);
    } else {
        echo json_encode(['success' => true, 'status' => 'idle', 'progress' => 0]);
    }
    exit;
}

$allowed = ['start', 'stop', 'restart'];

if (!in_array($action, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
}

$output = [];
$returnVar = 0;
ShellHelper::exec('/usr/bin/systemctl', [$action, 'unbound'], $output, $returnVar, true);

$outputStr = implode("\n", $output);

// Give the service a moment to start/stop before checking status
sleep(1);

$unbound = new UnboundManager();
$isRunning = $unbound->isServiceRunning();

$labels = [
    'start'   => 'iniciado',
    'stop'    => 'parado',
    'restart' => 'reiniciado',
];

$success = ($returnVar === 0);

echo json_encode([
    'success'    => $success,
    'running'    => $isRunning,
    'message'    => $success
        ? "Serviço {$labels[$action]} com sucesso."
        : "Erro ao {$action} o serviço: {$outputStr}",
]);
