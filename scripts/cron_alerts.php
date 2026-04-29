<?php
/**
 * Script para ser executado via cron a cada 1 minuto
 * * * * * * php /var/www/html/unbound-dashboard/scripts/cron_alerts.php
 */

require_once dirname(__DIR__) . '/src/AlertManager.php';

try {
    $manager = new \App\AlertManager();
    $manager->checkAndReport();
    echo "Verificação concluída sucesso [".date('Y-m-d H:i:s')."]\n";
} catch (Exception $e) {
    echo "Erro ao rodar cron: " . $e->getMessage() . "\n";
}
