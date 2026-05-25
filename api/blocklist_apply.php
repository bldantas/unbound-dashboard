<?php
/**
 * api/blocklist_apply.php — regera /etc/unbound/includes/blocked_domains.conf
 * com as blocklists ativas (multi-source via API) + allowlist e reinicia o Unbound.
 *
 * Usado pelo botão "Aplicar & Recarregar Unbound" em blocklists.php.
 * Reutiliza applyConfig do UnboundConfigManager passando o estado atual do
 * unbound.conf (parseConfig) — isso garante que o generateBlockedDomainsConf
 * é chamado (ele agora consulta a API e injeta as exceções) e o Unbound é
 * reiniciado via systemctl no fim.
 */

require_once dirname(__DIR__) . '/src/Auth.php';
require_once dirname(__DIR__) . '/src/UnboundConfigManager.php';

use App\Auth;
use App\UnboundConfigManager;

Auth::check();
if (!Auth::can('blocklist.write')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado — requer blocklist.write']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
if ($action !== 'apply_blocklists') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action inválida']);
    exit;
}

try {
    $cfg = new UnboundConfigManager();
    $current = $cfg->parseConfig();
    // Garante que o array tem a chave blocked_domains com a lista manual atual.
    $current['blocked_domains'] = $cfg->loadBlocklist();
    $result = $cfg->applyConfig($current);
    echo json_encode($result);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
