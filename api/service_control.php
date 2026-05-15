<?php
require_once dirname(__DIR__) . '/src/Auth.php';
require_once dirname(__DIR__) . '/src/UnboundManager.php';
require_once dirname(__DIR__) . '/src/ApiClient.php';
require_once dirname(__DIR__) . '/src/ShellHelper.php';
require_once dirname(__DIR__) . '/src/BlocklistManager.php';

use App\Auth;
use App\ShellHelper;
use App\UnboundManager;
use App\ApiClient;
use App\BlocklistManager;

Auth::check();
if (!\App\Auth::isAdmin()) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Acesso Negado']); exit; }

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'update_blacklist') {
    // Gate: respeita o setting blacklist_source_enabled. Mesmo que o frontend
    // já desabilite o botão, validamos server-side pra cobrir API direta.
    $bm = new BlocklistManager();
    if (!$bm->isBlacklistSourceEnabled()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Fonte da blacklist está desativada — ative em Configurações antes de atualizar.']);
        exit;
    }
    // Passa o JWT da sessão atual via env var pro script CLI usar nas chamadas FastAPI.
    $jwt = $_SESSION['api_jwt'] ?? '';
    $cmd = 'API_JWT=' . escapeshellarg($jwt) . ' php ' . escapeshellarg(dirname(__DIR__) . '/scripts/update_blacklist.php') . ' > /dev/null 2>&1 &';
    \App\ShellHelper::shell($cmd, $tmpOutput, $tmpReturn);
    echo json_encode(['success' => true, 'message' => 'Sincronização iniciada em segundo plano. Poderá levar até 1 minuto.']);
    exit;
}

if ($action === 'toggle_blacklist_source') {
    // Liga/desliga a fonte. Aceita {enabled: "1"|"0"} ou alterna o atual.
    $bm = new BlocklistManager();
    $raw = $_POST['enabled'] ?? null;
    if ($raw === null) {
        $newState = !$bm->isBlacklistSourceEnabled();
    } else {
        $newState = ((string) $raw) === '1';
    }
    $ok = $bm->saveBlacklistSourceEnabled($newState);
    echo json_encode([
        'success' => $ok,
        'enabled' => $newState,
        'message' => $ok
            ? ($newState ? 'Fonte ativada — próximo cron e botão Atualizar voltam a funcionar.' : 'Fonte pausada — auto-update parado. Dados atuais preservados.')
            : 'Falha ao salvar setting.',
    ]);
    exit;
}

if ($action === 'change_blacklist_source') {
    $src = $_POST['source'] ?? 'stevenblack';
    $jwt = $_SESSION['api_jwt'] ?? '';
    \App\ApiClient::post('/api/v1/exports/settings/bulk', $jwt, [
        ['setting_key' => 'blacklist_source', 'setting_value' => $src],
    ]);
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
