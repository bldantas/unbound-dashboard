<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';
require_once __DIR__ . '/UnboundManager.php';

/**
 * Classe responsável por executar diagnósticos do sistema hospedeiro do Unbound
 * como verificação de sintaxe de config, versões instaladas e permissões.
 */
class SystemCheckManager {

    /**
     * Verifica a integridade da conexão com o MariaDB
     */
    public function checkMariaDBConnection(): array {
        try {
            require_once __DIR__ . '/Database.php';
            $db = \App\Database::getInstance();
            $db->query("SELECT 1");
            return ['status' => true, 'msg' => 'Conectado e Operante'];
        } catch (\Exception $e) {
            return ['status' => false, 'msg' => 'Falha Crítica PDO'];
        }
    }

    /**
     * Verifica se o serviço de ingestão de logs está ativo
     */
    public function isLoggerRunning(): bool {
        $output = [];
        $returnVar = -1;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', 'unbound-logger'], $output, $returnVar, true);
        return (isset($output[0]) && trim($output[0]) === 'active');
    }

    /**
     * Obtém detalhes da versão do Unbound e suas bibliotecas compiladas
     */
    public function getVersionDetails(): array {
        $unboundManager = new \App\UnboundManager();
        $unboundBinary = $unboundManager->getDetectedBinaryPath();
        $output = [];
        \App\ShellHelper::exec($unboundBinary, ['-V'], $output, $returnVar, false);
        
        $details = [
            'version' => $unboundManager->getVersion(),
            'modules' => '',
            'linked_libs' => []
        ];
        
        foreach ($output as $line) {
            $line = trim($line);
            if (preg_match('/^Version\s+(.+)$/i', $line, $m)) {
                $details['version'] = $m[1];
            } elseif (preg_match('/^linked libs:\s+(.+)$/i', $line, $m)) {
                $details['linked_libs'][] = $m[1];
            } elseif (preg_match('/^linked modules:\s+(.+)$/i', $line, $m)) {
                $details['modules'] = $m[1];
            }
        }
        return $details;
    }

    /**
     * Usa o utilitário unbound-checkconf para validar o arquivo principal
     * Retorna array com success (bool) e o output bruto.
     */
    public function checkConfigSyntax(): array {
        // Necessita de sudo que já foi configurado no sudoers para www-data (NOPASSWD)
        $output = [];
        $returnVar = 0;
        \App\ShellHelper::exec('/usr/sbin/unbound-checkconf', ['/etc/unbound/unbound.conf'], $output, $returnVar, true);
        
        // Formata a saída para evitar sujeita excessiva se não houver erros pesados
        $outputStr = implode("\n", $output);
        if (trim($outputStr) === '') $outputStr = "Sintaxe OK. Nenhum erro detectado.";
        
        return [
            'success' => ($returnVar === 0),
            'output' => $outputStr
        ];
    }

    /**
     * Analisa as permissões de caminhos críticos
     */
    public function checkPermissions(): array {
        $paths = [
            '/etc/unbound' => 'Diretório Principal',
            '/etc/unbound/unbound.conf' => 'Configuração Master',
            '/etc/unbound/unbound_server.pem' => 'Certificado Servidor (SSL)',
            '/etc/unbound/unbound_control.pem' => 'Certificado Controle (SSL)'
        ];
        
        $results = [];
        foreach ($paths as $path => $label) {
            if (file_exists($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                
                // Tentativa de puxar owner/group amigável via Posix
                $ownerId = fileowner($path);
                $groupId = filegroup($path);
                
                if (function_exists('posix_getpwuid')) {
                    $ownerInfo = posix_getpwuid($ownerId);
                    $owner = $ownerInfo ? $ownerInfo['name'] : $ownerId;
                } else {
                    $owner = $ownerId;
                }

                if (function_exists('posix_getgrgid')) {
                    $groupInfo = posix_getgrgid($groupId);
                    $group = $groupInfo ? $groupInfo['name'] : $groupId;
                } else {
                    $group = $groupId;
                }
                
                // Validação amigável
                $status = 'success';
                $msg = 'OK';
                
                if ($owner !== 'unbound' && $owner !== 'root') {
                    $status = 'warning';
                    $msg = 'Atípico: dono não é root ou unbound';
                }
                
                $results[] = [
                    'label' => $label,
                    'path' => $path,
                    'exists' => true,
                    'perms' => $perms,
                    'owner' => $owner,
                    'group' => $group,
                    'status' => $status,
                    'msg' => $msg
                ];
            } else {
                $results[] = [
                    'label' => $label,
                    'path' => $path,
                    'exists' => false,
                    'perms' => '-',
                    'owner' => '-',
                    'group' => '-',
                    'status' => 'error',
                    'msg' => 'Ausente'
                ];
            }
        }
        return $results;
    }
}
