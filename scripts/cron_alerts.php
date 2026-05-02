<?php
/**
 * Script para ser executado via cron a cada 1 minuto
 * * * * * * php /var/www/html/unbound-dashboard/scripts/cron_alerts.php
 */

require_once dirname(__DIR__) . '/src/Auth.php';
require_once dirname(__DIR__) . '/src/AlertManager.php';

function loadCronEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $val = trim($parts[1]);
        if ($key !== '') {
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
        }
    }
}

loadCronEnv('/etc/unbound-dashboard.env');

$cronUser = getenv('ALERTS_CRON_USER') ?: '';
$cronPass = getenv('ALERTS_CRON_PASS') ?: '';

if ($cronUser === '' || $cronPass === '') {
    echo "Cron de alertas ignorado: defina ALERTS_CRON_USER e ALERTS_CRON_PASS em /etc/unbound-dashboard.env\n";
    exit(0);
}

try {
    $login = \App\Auth::login($cronUser, $cronPass);
    if (!($login['success'] ?? false)) {
        $msg = $login['message'] ?? 'falha de autenticação';
        echo "Cron de alertas sem autenticação: {$msg}\n";
        exit(1);
    }

    $manager = new \App\AlertManager();
    $manager->checkAndReport();
    echo "Verificação concluída sucesso [".date('Y-m-d H:i:s')."]\n";
} catch (Exception $e) {
    echo "Erro ao rodar cron: " . $e->getMessage() . "\n";
}
