<?php

namespace App;

/**
 * Classe responsável por extrair métricas de segurança (logins, portas, updates).
 */

require_once __DIR__ . '/ShellHelper.php';

class SecurityMonitor {

    public function getFailedLogins(): int {
        if (PHP_OS_FAMILY === 'Windows') return 0;
        // Requer sudo NOPASSWD no sudoers para www-data ou root, caso contrário retornará 0
        $output = [];
        $ret = 0;
        \App\ShellHelper::shell('sudo -n journalctl -u ssh --since today --grep "Failed password" 2>/dev/null | wc -l', $output, $ret);
        return (int)trim(implode('', $output));
    }

    public function getListeningPortsCount(): int {
        if (PHP_OS_FAMILY === 'Windows') return 0;
        $output = [];
        $ret = 0;
        \App\ShellHelper::shell('ss -tuln 2>/dev/null | grep LISTEN | wc -l', $output, $ret);
        return (int)trim(implode('', $output));
    }

    public function getPendingUpdates(): int {
        if (PHP_OS_FAMILY === 'Windows') return 0;
        // apt-get -s upgrade é passivo, não requer sudo em debian base na maioria das vezes,
        // mas pode demorar. É recomendável chamar uma cache ou contar arquivos em /var/lib/update-notifier
        $updateFile = '/var/lib/update-notifier/updates-available';
        if (file_exists($updateFile)) {
            $content = file_get_contents($updateFile);
            if (preg_match('/^\s*(\d+)\s+packages can be updated/m', $content, $matches)) {
                return (int)$matches[1];
            }
        }
        
        // Fallback rápido
        $output = [];
        $ret = 0;
        \App\ShellHelper::shell('apt-get -s upgrade 2>/dev/null | grep -c "^Inst"', $output, $ret);
        return (int)trim(implode('', $output));
    }
}
