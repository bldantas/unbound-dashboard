<?php
/**
 * Script para sincronização automática da lista judicial via Cron.
 */

require_once __DIR__ . '/../UnboundConfigManager.php';

use App\UnboundConfigManager;

// Impedir execução via Web por segurança, apenas CLI
if (php_sapi_name() !== 'cli') {
    die("Apenas execução via CLI é permitida.\n");
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando sincronização automática...\n";

$configManager = new UnboundConfigManager();
$settings = $configManager->loadSettings();

if (!($settings['official_blocklist_enabled'] ?? false)) {
    echo "Bloqueio judicial desativado nas configurações. Abortando.\n";
    exit(0);
}

$url = "https://api.anablock.net.br/domains/all?output=unbound";
if ($configManager->syncOfficialBlocklist($url)) {
    echo "Lista sincronizada com sucesso.\n";
    
    // Recarrega a configuração do Unbound para aplicar a nova lista
    $config = $configManager->parseConfig();
    $blockedDomains = $configManager->loadBlocklist();
    $result = $configManager->applyConfig(['blocked_domains' => $blockedDomains]);
    
    if ($result['success']) {
        echo "Configuração aplicada e Unbound reiniciado.\n";
    } else {
        echo "Erro ao aplicar configuração: " . $result['message'] . "\n";
        exit(1);
    }
} else {
    echo "Falha ao baixar a lista da URL: $url\n";
    exit(1);
}

echo "Sincronização concluída.\n";
