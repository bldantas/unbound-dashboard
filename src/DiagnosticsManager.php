<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

class DiagnosticsManager {
    /**
     * Valida um endereço IP ou Hostname para segurança.
     */
    private function validateTarget(string $target): string {
        $target = trim($target);
        // Permite apenas caracteres alfanuméricos, pontos, hifens e endereços IPv6
        if (!preg_match('/^[a-zA-Z0-9\.\-\:\[\]]+$/', $target)) {
            throw new \Exception("Alvo de diagnóstico inválido.");
        }
        return $target;
    }

    /**
     * Executa o comando e retorna o output formatado.
     */
    private function execute(array $args, string $binary = '/usr/bin/ping', bool $useSudo = false): string {
        $output = [];
        $ret = 0;
        \App\ShellHelper::exec($binary, $args, $output, $ret, $useSudo);
        return implode("\n", $output) ?: "Sem resposta do sistema.";
    }

    public function ping(string $host): string {
        $host = $this->validateTarget($host);
        return $this->execute(['-c', '4', $host], '/usr/bin/ping');
    }

    public function dig(string $host, string $server = '127.0.0.1', string $type = 'A'): string {
        $host = $this->validateTarget($host);
        $server = $this->validateTarget($server);
        $type = in_array(strtoupper($type), ['A', 'AAAA', 'MX', 'TXT', 'PTR', 'NS', 'SOA', 'ANY']) ? strtoupper($type) : 'A';
        
        return $this->execute(['@' . $server, $host, $type], '/usr/bin/dig');
    }

    public function dnssec(string $host): string {
        $host = $this->validateTarget($host);
        return $this->execute(['+dnssec', '+multiline', $host], '/usr/bin/dig');
    }

    public function mtr(string $host): string {
        $host = $this->validateTarget($host);
        // mtr -n (no-dns) -r (report) -c 5 (count)
        return $this->execute(['-n', '-c', '5', '-r', $host], '/usr/bin/mtr');
    }

    /**
     * Executa consulta WHOIS.
     */
    public function whois(string $domain): string {
        $domain = $this->validateTarget($domain);
        return $this->execute([$domain], '/usr/bin/whois');
    }

    /**
     * Retorna apenas o tempo de consulta (query time) em ms.
     */
    public function benchmarkDns(string $host, string $server): float {
        $host = $this->validateTarget($host);
        $server = $this->validateTarget($server);

        $output = $this->execute(['@' . $server, $host], '/usr/bin/dig');

        // Busca ";; Query time: XX msec"
        if (preg_match('/Query time:\s+(\d+)\s+msec/i', $output, $matches)) {
            return (float)$matches[1];
        }

        return 9999.0; // Valor alto para indicar falha/timeout
    }

    /**
     * Executa o teste completo de conectividade
     */
    public function internetTest(string $type): string {
        $allowedTypes = ['all4', 'all6'];
        if (!in_array($type, $allowedTypes)) {
            throw new \Exception("Tipo de teste inválido.");
        }
        
        $scriptPath = __DIR__ . '/../api/scripts/internet-test.sh';
        // Executa o script
        $output = $this->execute([$scriptPath, $type], '/bin/sh', false);
        
        // Remove cores ANSI formatando a saída para web
        $filtered = preg_replace('/\e\[[0-9;]*m/', '', $output);
        // Também remove resets \e[m se hover
        $filtered = preg_replace('/\e\[m/', '', $filtered);

        return $filtered;
    }

    /**
     * Atualiza o root.hints (named.cache) baixando da internet e recarregando o serviço.
     */
    public function updateRootHints(): string {
        $scriptPath = __DIR__ . '/../api/scripts/update_root_hints.sh';
        $output = [];
        $ret = 0;
        \App\ShellHelper::exec('/bin/sh', ['-c', 'sudo ' . escapeshellarg($scriptPath)], $output, $ret, false);
        return implode("\n", $output) ?: "Sem resposta do script.";
    }
}
