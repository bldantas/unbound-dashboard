<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

class NetworkManager {

    /**
     * Normaliza a lista de servidores NTP preservando hostnames, IPv4 e IPv6.
     */
    private function normalizeNtpServers($servers): string {
        if (is_array($servers)) {
            $servers = implode(' ', $servers);
        }

        $entries = preg_split('/[\s,;]+/', trim((string) $servers)) ?: [];
        $normalized = [];

        foreach ($entries as $entry) {
            $entry = preg_replace('/[^A-Za-z0-9.\-:\[\]%_]/', '', trim($entry));
            if ($entry !== '') {
                $normalized[] = $entry;
            }
        }

        return implode(' ', array_values(array_unique($normalized)));
    }

    /**
     * Retorna a lista de fusos válidos reconhecidos pelo sistema PHP.
     */
    public function getAvailableTimezones(): array {
        return timezone_identifiers_list();
    }

    /**
     * Bloqueia alterações diretas na loopback para evitar regressões.
     */
    private function isLoopbackInterface(string $iface): bool {
        return strtolower(trim($iface)) === 'lo';
    }

    /**
     * Aceita aliases de loopback (lo.1, lo:1, etc) como interfaces virtuais válidas.
     */
    private function isLoopbackAlias(string $iface): bool {
        return (bool) preg_match('/^lo[.:][0-9]+$/i', trim($iface));
    }

    /**
     * Retorna o hostname atual do sistema.
     */
    public function getHostname(): string {
        return gethostname();
    }

    /**
     * Altera o hostname do sistema.
     * Requer permissão sudo para 'hostnamectl'.
     */
    public function setHostname(string $newHostname): array {
        $newHostname = preg_replace('/[^a-zA-Z0-9.-]/', '', $newHostname);
        if (empty($newHostname)) {
            return ['success' => false, 'message' => 'Hostname inválido.'];
        }

        $output = [];
        $returnVar = 0;
        \App\ShellHelper::exec('/usr/bin/hostnamectl', ['set-hostname', $newHostname], $output, $returnVar, true);

        if ($returnVar === 0) {
            return ['success' => true, 'message' => 'Hostname alterado com sucesso para ' . $newHostname];
        } else {
            return ['success' => false, 'message' => 'Erro ao alterar hostname: ' . implode(" ", $output)];
        }
    }

    /**
     * Obtém os servidores DNS configurados no sistema (/etc/resolv.conf).
     */
    public function getSystemDNS(): array {
        $dns = [];
        if (file_exists('/etc/resolv.conf')) {
            $lines = file('/etc/resolv.conf');
            foreach ($lines as $line) {
                if (preg_match('/^nameserver\s+(.+)/', trim($line), $m)) {
                    $dns[] = trim($m[1]);
                }
            }
        }
        return $dns;
    }

    /**
     * Atualiza os servidores DNS do sistema.
     * Requer permissão sudo para gravar no /etc/resolv.conf.
     */
    public function setSystemDNS(array $dnsArray): array {
        $content = "# Gerado pelo Unbound Dashboard\n";
        foreach ($dnsArray as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $content .= "nameserver $ip\n";
            }
        }

        $tmpFile = dirname(__FILE__) . '/data/tmp/resolv_conf_new';
        file_put_contents($tmpFile, $content);
        $output = [];
        $returnVar = 0;
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, '/etc/resolv.conf'], $output, $returnVar, true);
        
        if ($returnVar === 0) {
            return ['success' => true, 'message' => 'DNS do sistema atualizado com sucesso.'];
        } else {
            return ['success' => false, 'message' => 'Erro ao aplicar DNS: ' . implode(" ", $output)];
        }
    }

    public function getInterfaceConfig(string $ifaceName): array {
        $config = [
            'mode' => 'dhcp', 'address' => '', 'gateway' => '', 'netmask' => '',
            'v6_enabled' => false, 'v6_mode' => 'static', 'v6_address' => '', 'v6_gateway' => '', 'v6_netmask' => ''
        ];
        if (!file_exists('/etc/network/interfaces')) return $config;

        $ifaceName = trim($ifaceName);
        if ($ifaceName === '') return $config;
        $ifacePattern = preg_quote($ifaceName, '/');

        $lines = file('/etc/network/interfaces');
        $inBlock = ''; // 'v4' or 'v6'
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;

            if (preg_match("/^iface\s+$ifacePattern\s+inet\s+(static|dhcp)/", $line, $m)) {
                $inBlock = 'v4';
                $config['mode'] = $m[1];
                continue;
            } elseif (preg_match("/^iface\s+$ifacePattern\s+inet6\s+(static|dhcp|auto)/", $line, $m)) {
                $inBlock = 'v6';
                $config['v6_enabled'] = true;
                $config['v6_mode'] = $m[1];
                continue;
            }

            if ($inBlock !== '') {
                if (preg_match("/^iface\s+/", $line) || preg_match("/^auto\s+/", $line)) {
                    $inBlock = '';
                    continue; // Skip processing this start line here, let the loop hit it
                }
                
                if ($inBlock === 'v4') {
                    if (preg_match("/^address\s+(.+)/", $line, $m)) $config['address'] = $m[1];
                    if (preg_match("/^gateway\s+(.+)/", $line, $m)) $config['gateway'] = $m[1];
                    if (preg_match("/^netmask\s+(.+)/", $line, $m)) $config['netmask'] = $m[1];
                } elseif ($inBlock === 'v6') {
                    if (preg_match("/^address\s+(.+)/", $line, $m)) $config['v6_address'] = $m[1];
                    if (preg_match("/^gateway\s+(.+)/", $line, $m)) $config['v6_gateway'] = $m[1];
                    if (preg_match("/^netmask\s+(\d+)/", $line, $m)) $config['v6_netmask'] = $m[1];
                }
            }
        }
        return $config;
    }

    /**
     * Atualiza a configuração de uma interface no arquivo /etc/network/interfaces.
     */
    public function updateInterfaceConfig(string $iface, string $mode, string $address = '', string $gateway = '', string $netmask = '', bool $v6_enabled = false, string $v6_mode = 'static', string $v6_address = '', string $v6_gateway = '', string $v6_netmask = ''): array {
        $iface = trim($iface);
        if (!preg_match('/^[a-zA-Z0-9_.:-]+$/', $iface)) {
            return ['success' => false, 'message' => 'Nome de interface inválido.'];
        }

        if ($this->isLoopbackInterface($iface)) {
            return ['success' => false, 'message' => 'A interface loopback (lo) não pode ser alterada por esta tela.'];
        }

        if (!$this->interfaceExists($iface)) {
            return ['success' => false, 'message' => "Interface $iface não encontrada no sistema."];
        }

        if (!file_exists('/etc/network/interfaces')) {
            return ['success' => false, 'message' => 'Arquivo interfaces não encontrado.'];
        }

        $ifacePattern = preg_quote($iface, '/');

        if ($mode === 'static') {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return ['success' => false, 'message' => 'Endereço IP inválido.'];
            }
            if (!empty($gateway) && !filter_var($gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return ['success' => false, 'message' => 'Gateway inválido.'];
            }
            if (!empty($netmask) && !filter_var($netmask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !preg_match('/^([1-9]|[1-2]\d|3[0-2])$/', $netmask)) {
                return ['success' => false, 'message' => 'Máscara de rede inválida (deve ser IP ou formato CIDR).'];
            }
        }

        if ($v6_enabled && $v6_mode === 'static') {
            if (!filter_var($v6_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return ['success' => false, 'message' => 'Endereço IPv6 inválido.'];
            }
            if (!empty($v6_gateway) && !filter_var($v6_gateway, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return ['success' => false, 'message' => 'Gateway IPv6 inválido.'];
            }
            if (!empty($v6_netmask) && !preg_match('/^([1-9]|[1-9]\d|1[0-1]\d|12[0-8])$/', $v6_netmask)) {
                return ['success' => false, 'message' => 'Máscara(Prefixo) IPv6 inválida (deve ser entre 1 e 128).'];
            }
        }

        $lines = file('/etc/network/interfaces');
        $newLines = [];
        $skip = false;
        $hasAuto = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match("/^auto\s+$ifacePattern$/", $trimmed) || preg_match("/^allow-hotplug\s+$ifacePattern$/", $trimmed)) {
                $hasAuto = true;
            }

            if (preg_match("/^iface\s+$ifacePattern\s+inet\s+/", $trimmed) || preg_match("/^iface\s+$ifacePattern\s+inet6\s+/", $trimmed)) {
                $skip = true;
                continue;
            }

            if ($skip && (preg_match("/^iface\s+/", $trimmed) || preg_match("/^auto\s+/", $trimmed) || preg_match("/^allow-hotplug\s+/", $trimmed))) {
                $skip = false;
            }

            if (!$skip) {
                $newLines[] = $line;
            }
        }

        while (count($newLines) > 0 && trim(end($newLines)) === '') {
            array_pop($newLines);
        }

        if (!$hasAuto) {
            $newLines[] = "\nauto $iface\n";
        } else {
            $newLines[] = "\n";
        }

        $newLines[] = "iface $iface inet $mode\n";
        if ($mode === 'static') {
            if (!empty($address)) $newLines[] = "\taddress $address\n";
            if (!empty($netmask)) $newLines[] = "\tnetmask $netmask\n";
            if (!empty($gateway)) $newLines[] = "\tgateway $gateway\n";
        }

        if ($v6_enabled) {
            $newLines[] = "\niface $iface inet6 $v6_mode\n";
            if ($v6_mode === 'static') {
                if (!empty($v6_address)) $newLines[] = "\taddress $v6_address\n";
                if (!empty($v6_netmask)) $newLines[] = "\tnetmask $v6_netmask\n";
                if (!empty($v6_gateway)) $newLines[] = "\tgateway $v6_gateway\n";
            }
        }
        $newLines[] = "\n";

        $newContent = implode("", $newLines);
        $tmpFile = dirname(__FILE__) . '/data/tmp/interfaces_new';
        file_put_contents($tmpFile, $newContent);
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, '/etc/network/interfaces'], $output, $returnVar, true);

        if ($returnVar === 0) {
            return ['success' => true, 'message' => "Configuração da interface $iface salva. É necessário reiniciar a interface para aplicar."];
        } else {
            return ['success' => false, 'message' => 'Erro ao salvar configuração: ' . implode(" ", $output)];
        }
    }

    /**
     * Aplica as mudanças de rede reiniciando a interface.
     * CUIDADO: Pode derrubar a conexão se o IP mudar!
     */
    public function applyInterfaceChanges(string $iface): array {
        $iface = trim($iface);
        if (!preg_match('/^[a-zA-Z0-9_.:-]+$/', $iface)) {
            return ['success' => false, 'message' => 'Nome de interface inválido.'];
        }

        if ($this->isLoopbackInterface($iface)) {
            return ['success' => false, 'message' => 'A interface loopback (lo) não pode ser reaplicada por esta tela.'];
        }

        if (!$this->interfaceExists($iface)) {
            return ['success' => false, 'message' => "Interface $iface não encontrada no sistema."];
        }

        $safeIface = escapeshellarg($iface);
        $output = [];
        $returnVar = 0;
        $usedFallback = false;

        // Usa --force para reaplicar também cenários em que a interface já está ativa.
        // Isso evita que mudanças de IPv6 fiquem sem efeito em alguns ambientes ifupdown.
        $cmd = "sudo /usr/sbin/ifdown --force $safeIface; sudo /usr/sbin/ifup --force $safeIface";
        \App\ShellHelper::shell($cmd, $output, $returnVar);

        // Fallback: tenta subir a interface novamente caso o ciclo down/up retorne erro.
        if ($returnVar !== 0) {
            $fallbackOutput = [];
            $fallbackRet = 0;
            \App\ShellHelper::shell("sudo /usr/sbin/ifup --force $safeIface", $fallbackOutput, $fallbackRet);
            if ($fallbackRet === 0) {
                $usedFallback = true;
                $returnVar = 0;
                $output = array_merge($output, $fallbackOutput);
            } else {
                $output = array_merge($output ?? [], $fallbackOutput ?? []);
                $returnVar = $fallbackRet;
            }
        }

        if ($returnVar === 0) {
            $ifaceConfig = $this->getInterfaceConfig($iface);
            if (!empty($ifaceConfig['v6_enabled'])) {
                $globalV6 = $this->getGlobalIpv6Addrs($iface);
                if (empty($globalV6)) {
                    if (($ifaceConfig['v6_mode'] ?? 'static') === 'static') {
                        $prefix = $usedFallback
                            ? "Interface $iface reaplicada com fallback,"
                            : "Interface $iface reiniciada,";
                        return [
                            'success' => false,
                            'message' => "$prefix mas o IPv6 global não foi detectado na interface após aplicar."
                        ];
                    }

                    $prefix = $usedFallback
                        ? "Interface $iface reaplicada com fallback."
                        : "Interface $iface reiniciada.";
                    return [
                        'success' => true,
                        'message' => "$prefix IPv6 ainda não detectado; em modo auto isso pode levar alguns segundos."
                    ];
                }

                $v6List = implode(', ', $globalV6);
                if ($usedFallback) {
                    return ['success' => true, 'message' => "Interface $iface reaplicada com fallback. IPv6 global detectado: $v6List."];
                }
                return ['success' => true, 'message' => "Interface $iface reiniciada com sucesso. IPv6 global detectado: $v6List."];
            }

            if ($usedFallback) {
                return ['success' => true, 'message' => "Interface $iface reaplicada com sucesso (fallback ifup)."];
            }
            return ['success' => true, 'message' => "Interface $iface reiniciada com sucesso."];
        } else {
            $details = trim(implode(" ", $output ?? []));
            if ($details === '') {
                $details = 'Sem saída de erro do sistema.';
            }
            return ['success' => false, 'message' => "Erro ao aplicar mudanças em $iface: $details"];
        }
    }

    /**
     * Retorna lista de IPv6 globais válidos ativos na interface.
     */
    private function getGlobalIpv6Addrs(string $iface): array {
        $output = [];
        $returnVar = 0;
        \App\ShellHelper::exec('/usr/sbin/ip', ['-j', '-6', 'addr', 'show', 'dev', $iface], $output, $returnVar, false);
        if ($returnVar !== 0) {
            return [];
        }

        $data = json_decode(implode("", $output), true);
        if (!is_array($data) || empty($data[0]['addr_info']) || !is_array($data[0]['addr_info'])) {
            return [];
        }

        $result = [];

        foreach ($data[0]['addr_info'] as $addr) {
            $isV6 = ($addr['family'] ?? '') === 'inet6';
            $isGlobal = ($addr['scope'] ?? '') === 'global';
            $isTentative = !empty($addr['tentative']);
            $isDadFailed = !empty($addr['dadfailed']);
            $local = trim((string)($addr['local'] ?? ''));

            if ($isV6 && $isGlobal && !$isTentative && !$isDadFailed && $local !== '') {
                $result[] = $local;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Verifica se uma interface existe no sistema.
     */
    private function interfaceExists(string $iface): bool {
        if ($this->isLoopbackAlias($iface)) {
            return true;
        }

        $output = [];
        $returnVar = 0;
        \App\ShellHelper::exec('/usr/sbin/ip', ['link', 'show', 'dev', $iface], $output, $returnVar, false);
        return $returnVar === 0;
    }

    /**
     * Retorna informações detalhadas das interfaces de rede.
     */
    public function getInterfacesDetailed(): array {
        $output = [];
        \App\ShellHelper::exec('/usr/sbin/ip', ['-j', 'addr', 'show'], $output, $returnVar, false);
        $data = json_decode(implode("", $output), true);
        return is_array($data) ? $data : [];
    }

    public function getNtpServers(): string {
        if (file_exists('/etc/systemd/timesyncd.conf')) {
            $lines = file('/etc/systemd/timesyncd.conf');
            foreach ($lines as $line) {
                if (preg_match('/^NTP=(.+)/', trim($line), $m)) {
                    return trim($m[1]);
                }
            }
        }
        return '';
    }

    public function setNtpServers($servers): array {
        $servers = $this->normalizeNtpServers($servers);
        $file = '/etc/systemd/timesyncd.conf';
        if (!file_exists($file)) return ['success' => false, 'message' => 'timesyncd.conf não encontrado.'];

        $lines = file($file);
        $newLines = [];
        $found = false;

        foreach ($lines as $line) {
            if (preg_match('/^#?NTP=/', trim($line))) {
                if (!empty($servers)) $newLines[] = "NTP=$servers\n";
                else $newLines[] = "#NTP=\n";
                $found = true;
            } else {
                $newLines[] = $line;
            }
        }
        if (!$found && !empty($servers)) {
            $newLines[] = "NTP=$servers\n";
        }

        $newContent = implode("", $newLines);
        $tmpFile = dirname(__FILE__) . '/data/tmp/timesyncd.conf.new';
        file_put_contents($tmpFile, $newContent);
        $safeTmp = escapeshellarg($tmpFile);
        $safeFile = escapeshellarg($file);
        \App\ShellHelper::shell("sudo /usr/bin/mv $safeTmp $safeFile && sudo /usr/bin/systemctl restart systemd-timesyncd", $out, $ret);

        if ($ret === 0) return ['success' => true, 'message' => 'Servidores NTP salvos e serviço reiniciados com sucesso.'];
        return ['success' => false, 'message' => 'Erro ao salvar configuração NTP: ' . implode(" ", $out)];
    }

    public function getSystemTimezone(): string {
        $out = [];
        $ret = 0;
        \App\ShellHelper::exec('/usr/bin/timedatectl', ['show', '-p', 'Timezone', '--value'], $out, $ret, false);
        return ($ret === 0 && !empty($out)) ? trim($out[0]) : '';
    }

    public function setSystemTimezone(string $tz): array {
        $tz = trim($tz);
        if (empty($tz)) return ['success' => false, 'message' => 'Timezone vazio.'];

        if (!in_array($tz, $this->getAvailableTimezones(), true)) {
            return ['success' => false, 'message' => 'Timezone inválido. Selecione um fuso horário válido.'];
        }

        \App\ShellHelper::exec('/usr/bin/timedatectl', ['set-timezone', $tz], $out, $ret, true);
        if ($ret === 0) return ['success' => true, 'message' => 'Fuso horário: ' . $tz];
        return ['success' => false, 'message' => 'Erro Timezone: ' . implode(" ", $out)];
    }
}
