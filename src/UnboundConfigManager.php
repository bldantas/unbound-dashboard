<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

/**
 * Manage the reading and updating of unbound.conf securely.
 */
class UnboundConfigManager
{
    private string $configPath;
    private string $tempConfigPath;
    private string $blocklistJsonPath;
    private string $blockedConfPath;
    private string $tempBlockedConfPath;
    private string $antiDohConfPath;
    private string $tempAntiDohPath;
    private string $antiDohHostsJsonPath;
    private string $officialBlocklistPath;
    private string $settingsPath;
    private string $localRecordsJsonPath;
    private string $tempLocalRecordsPath;

    private string $confdDir;
    private array $modularFiles;

    public function __construct(
        string $configPath = '/etc/unbound/unbound.conf'
    ) {
        $tempDir = dirname(__FILE__) . '/data/tmp/';
        $this->configPath = $configPath;
        $this->tempConfigPath = $tempDir . 'unbound_web_config.tmp';
        $this->confdDir = '/etc/unbound/includes';
        $this->blocklistJsonPath = dirname(__FILE__) . '/data/blocklist.json';
        $this->blockedConfPath = $this->confdDir . '/blocked_domains.conf';
        $this->tempBlockedConfPath = $tempDir . 'unbound_blocked_domains.tmp';
        $this->antiDohConfPath = $this->confdDir . '/anti_doh.conf';
        $this->tempAntiDohPath = $tempDir . 'unbound_anti_doh.tmp';
        $this->antiDohHostsJsonPath = dirname(__FILE__) . '/anti_doh_hosts.json';
        $this->officialBlocklistPath = dirname(__FILE__) . '/data/official_blocklist.conf';
        $this->settingsPath = dirname(__FILE__) . '/data/settings.json';
        $this->localRecordsJsonPath = dirname(__FILE__) . '/data/local_records.json';
        $this->tempLocalRecordsPath = $tempDir . 'unbound_local_records.tmp';

        $this->modularFiles = [
            'interfaces' => $this->confdDir . '/interfaces.conf',
            'general' => $this->confdDir . '/general.conf',
            'optimization' => $this->confdDir . '/optimization.conf',
            'performance' => $this->confdDir . '/performance.conf',
            'security' => $this->confdDir . '/security.conf',
            'forwarders' => $this->confdDir . '/forwarders.conf',
            'local_records' => $this->confdDir . '/local_records.conf'
        ];
        
        // Garantir que o diretório de includes existe
        $this->ensureDirectoriesAndFiles();
    }

    /**
     * Garante que os diretórios e arquivos necessários existem
     */
    private function ensureDirectoriesAndFiles(): void
    {
        // Criar diretório de includes se não existir
        if (!is_dir($this->confdDir)) {
            @mkdir($this->confdDir, 0755, true);
        }

        // Garantir que o arquivo temporário de bloqueio existe (vazio se não existir)
        if (!file_exists($this->tempBlockedConfPath)) {
            file_put_contents($this->tempBlockedConfPath, "# Blocked Domains Configuration\n# This file is auto-generated\n\n");
            @chmod($this->tempBlockedConfPath, 0664);
        }

        // Garantir que o arquivo final de bloqueio existe
        if (!file_exists($this->blockedConfPath)) {
            file_put_contents($this->blockedConfPath, "# Blocked Domains Configuration\n# This file is auto-generated\n\n");
            @chmod($this->blockedConfPath, 0664);
        }

        // Garantir que o arquivo de Anti-DoH existe (vazio = sem zones, desativado)
        if (!file_exists($this->antiDohConfPath)) {
            file_put_contents($this->antiDohConfPath, "# Anti-DoH Filter — DNS-over-HTTPS endpoints\n# Auto-generated. Toggle em Configurações → Lista de Bloqueios.\nserver:\n");
            @chmod($this->antiDohConfPath, 0664);
        }
        if (!file_exists($this->tempAntiDohPath)) {
            file_put_contents($this->tempAntiDohPath, "# Anti-DoH Filter\nserver:\n");
            @chmod($this->tempAntiDohPath, 0664);
        }

        // Garantir que outros arquivos modulares existem
        $moduleDefaults = [
            'interfaces' => "# Interface Configuration\nserver:\n",
            'general' => "# General Configuration\nserver:\n    verbosity: 1\n",
            'optimization' => "# Optimization Configuration\nserver:\n    prefetch: yes\n",
            'performance' => "# Performance Configuration\nserver:\n    num-threads: 4\n",
            'security' => "# Security Configuration\nserver:\n    hide-identity: yes\n    hide-version: yes\n",
            'forwarders' => "# Forwarders Configuration\n",
            'local_records' => "# Local Records\n"
        ];

        foreach ($this->modularFiles as $key => $filePath) {
            if (!file_exists($filePath)) {
                $content = $moduleDefaults[$key] ?? "# $key Configuration\n";
                @file_put_contents($filePath, $content);
                @chmod($filePath, 0664);
            }
        }
    }

    public function readRawConfig(): string
    {
        if (!file_exists($this->configPath)) {
            if (PHP_OS_FAMILY === 'Windows') {
                return $this->getMockConfig();
            }
            return "";
        }
        return file_get_contents($this->configPath);
    }

    /**
     * Parseia o arquivo de configuração e retorna um array com as chaves que nos interessam.
     */
    public function parseConfig(): array
    {
        $files = [$this->configPath];
        foreach ($this->modularFiles as $file) {
            if (file_exists($file)) $files[] = $file;
        }
        if (file_exists($this->blockedConfPath)) $files[] = $this->blockedConfPath;

        $content = "";
        foreach ($files as $file) {
            if (file_exists($file)) {
                $content .= file_get_contents($file) . "\n";
            }
        }

        $config = [
            'access-control' => [],
            'do-not-query-address' => [],
            'interfaces' => [],
            'includes' => [],
            'module-config' => '',
            'forward-zones' => []
        ];

        $scalars = [
            'do-ip4' => 'yes',
            'do-ip6' => 'no',
            'num-threads' => '1',
            'prefetch' => 'no',
            'minimal-responses' => 'no',
            'serve-expired' => 'no',
            'dnssec-enabled' => 'no',
            'cache-slabs' => '',
            'msg-cache-size' => '',
            'auto-trust-anchor-file' => '',
            'so-reuseport' => '',
            'rrset-roundrobin' => '',
            'hide-identity' => '',
            'hide-version' => '',
            'harden-glue' => '',
            'harden-algo-downgrade' => '',
            'harden-below-nxdomain' => '',
            'harden-dnssec-stripped' => '',
            'harden-large-queries' => '',
            'harden-referral-path' => '',
            'harden-short-bufsize' => '',
            'do-not-query-localhost' => '',
            'aggressive-nsec' => '',
            'qname-minimisation' => '',
            'deny-any' => '',
            'use-caps-for-id' => '',
            'val-clean-additional' => '',
            'prefetch-key' => '',
            'outgoing-range' => '',
            'outgoing-port-avoid' => '',
            'outgoing-port-permit' => '',
            'num-queries-per-thread' => '',
            'rrset-cache-size' => '',
            'msg-cache-slabs' => '',
            'rrset-cache-slabs' => '',
            'infra-cache-slabs' => '',
            'key-cache-slabs' => '',
            'cache-min-ttl' => '',
            'cache-max-ttl' => '',
            'infra-host-ttl' => '',
            'infra-lame-ttl' => '',
            'infra-cache-numhosts' => '',
            'infra-cache-lame-size' => '',
            'edns-buffer-size' => '',
            'delay-close' => '',
            'neg-cache-size' => '',
            'ratelimit' => '',
            'unwanted-reply-threshold' => '',
            'tls-port' => '',
            'https-port' => '',
            'tls-service-key' => '',
            'tls-service-pem' => ''
        ];

        foreach ($scalars as $k => $v) {
            $config[$k] = $v;
        }

        $lines = explode("\n", $content);
        $currentForwardZone = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed) || substr($trimmed, 0, 1) === '#') continue;

            if (preg_match('/^access-control:\s+([0-9a-fA-F\.\:\/]+)\s+(\S+)/', $trimmed, $matches)) {
                $config['access-control'][] = ['ip' => $matches[1], 'action' => $matches[2]];
            } elseif (preg_match('/^do-not-query-address:\s*(\S+)/', $trimmed, $matches)) {
                $config['do-not-query-address'][] = $matches[1];
            } elseif (preg_match('/^auto-trust-anchor-file:\s*(.+)$/', $trimmed, $matches)) {
                $config['auto-trust-anchor-file'] = trim(trim($matches[1]), '"\'');
                $config['dnssec-enabled'] = 'yes';
            } elseif (preg_match('/^interface:\s*(\S+)/', $trimmed, $matches)) {
                $config['interfaces'][] = $matches[1];
            } elseif (preg_match('/^include:\s*(\S+)/', $trimmed, $matches)) {
                $config['includes'][] = $matches[1];
            } elseif (preg_match('/^module-config:\s*"(.+)"/', $trimmed, $matches)) {
                $config['module-config'] = $matches[1];
            } elseif (strpos($trimmed, 'forward-zone:') === 0) {
                $currentForwardZone = ['name' => '', 'addresses' => []];
                $config['forward-zones'][] = &$currentForwardZone;
            } elseif ($currentForwardZone !== null) {
                if (preg_match('/^name:\s*"(.+)"/', $trimmed, $matches)) {
                    $currentForwardZone['name'] = $matches[1];
                } elseif (preg_match('/^forward-addr:\s*(\S+)/', $trimmed, $matches)) {
                    $currentForwardZone['addresses'][] = $matches[1];
                }
            } else {
                foreach (array_keys($scalars) as $key) {
                    if (preg_match('/^' . $key . ':\s*(.+)$/', $trimmed, $matches)) {
                        $config[$key] = trim($matches[1]);
                        break;
                    }
                }
            }
        }

        $config['interfaces'] = array_values(array_unique($config['interfaces']));

        $uniqueAC = [];
        foreach ($config['access-control'] as $ac) {
            $key = $ac['ip'] . '|' . $ac['action'];
            $uniqueAC[$key] = $ac;
        }
        $config['access-control'] = array_values($uniqueAC);

        return $config;
    }

    public function generateModularConfigs(array $newParams): array
    {
        $oldConfig = $this->parseConfig();
        $configs = [];

        $booleanFlags = [
            'do-ip4',
            'do-ip6',
            'do-udp',
            'do-tcp',
            'log-queries',
            'log-replies',
            'log-time-ascii',
            'extended-statistics',
            'statistics-cumulative',
            'prefetch',
            'minimal-responses',
            'serve-expired',
            'rrset-roundrobin',
            'qname-minimisation',
            'aggressive-nsec',
            'prefetch-key',
            'hide-identity',
            'hide-version',
            'use-caps-for-id',
            'deny-any',
            'val-clean-additional',
            'do-not-query-localhost',
            'dnssec-enabled',
            'so-reuseport',
            'harden-glue',
            'harden-algo-downgrade',
            'harden-below-nxdomain',
            'harden-dnssec-stripped',
            'harden-large-queries',
            'harden-referral-path',
            'harden-short-bufsize'
        ];

        $filterVal = function ($key, $val) use ($booleanFlags) {
            if ($val === 'no' && !in_array($key, $booleanFlags)) return '';
            return $val;
        };

        // 1. Interfaces
        $interfaces = !empty($newParams['interfaces']) ? $newParams['interfaces'] : $oldConfig['interfaces'];
        if (empty($interfaces)) $interfaces = ['0.0.0.0', '::0'];

        // "Bases" são as interfaces SEM `@porta` — o que o user define manualmente.
        // Quando habilitamos DoT/DoH, geramos listeners adicionais `iface@porta`
        // automaticamente (Unbound exige isso pra escutar nas portas extras).
        $baseInterfaces = [];
        foreach ($interfaces as $iface) {
            $iface = trim((string) $iface);
            if ($iface === '') continue;
            // Strip trailing @PORT se vier (re-aplicamos abaixo).
            $base = preg_replace('/@\d+$/', '', $iface);
            $baseInterfaces[] = $base;
        }
        $baseInterfaces = array_values(array_unique($baseInterfaces));

        $intContent = "";
        foreach ($baseInterfaces as $iface) {
            $intContent .= "    interface: {$iface}\n";
        }
        $port = $filterVal('port', $newParams['port'] ?? $oldConfig['port'] ?? '');
        $intContent .= "    port: " . (!empty($port) ? (int)$port : 53) . "\n";

        // Master switch DoT/DoH. Tres origens (em ordem de prioridade):
        //   1. $newParams['tls-enabled'] = 'yes'|'no' — explícito via form
        //   2. Houver tls-port OU https-port em $newParams (ex: applyConfig
        //      vindo de tls_generate_cert/upload, sem master switch)
        //   3. Fallback: estado anterior (oldConfig tem alguma porta TLS)
        if (array_key_exists('tls-enabled', $newParams)) {
            $tlsEnabled = $newParams['tls-enabled'] === 'yes';
        } elseif (!empty($newParams['tls-port']) || !empty($newParams['https-port'])) {
            $tlsEnabled = true;
        } else {
            $tlsEnabled = !empty($oldConfig['tls-port']) || !empty($oldConfig['https-port']);
        }

        // Auto-listen pra DoT/DoH (skip loopback)
        $tlsPortNum   = $tlsEnabled ? (int) ($newParams['tls-port']   ?? $oldConfig['tls-port']   ?? 853) : 0;
        $httpsPortNum = $tlsEnabled ? (int) ($newParams['https-port'] ?? $oldConfig['https-port'] ?? 443) : 0;
        $isLoopback = function (string $ip): bool {
            $ip = strtolower(trim($ip));
            return $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '127.');
        };
        foreach ($baseInterfaces as $iface) {
            if ($isLoopback($iface)) continue;
            if ($tlsPortNum > 0)   $intContent .= "    interface: {$iface}@{$tlsPortNum}\n";
            if ($httpsPortNum > 0) $intContent .= "    interface: {$iface}@{$httpsPortNum}\n";
        }

        // Exporta no array de configs E em $newParams pra etapa 2 (general.conf)
        // saber quais valores escrever — o $tlsEnabled é a fonte da verdade.
        $configs['interfaces'] = $intContent;
        $newParams['_tls_enabled']   = $tlsEnabled;
        $newParams['_tls_port_num']  = $tlsPortNum;
        $newParams['_https_port_num']= $httpsPortNum;

        // 2. General
        $threads = !empty($newParams['num-threads']) ? (int)$newParams['num-threads'] : 4;
        $threadsVal = $newParams['num-threads'] ?? $oldConfig['num-threads'] ?? '';
        $threads = !empty($threadsVal) ? (int)$threadsVal : 4;
        $ip4 = (isset($newParams['do-ip4'])) ? ($newParams['do-ip4'] === 'yes' ? 'yes' : 'no') : ($oldConfig['do-ip4'] ?? 'yes');
        $ip6 = (isset($newParams['do-ip6'])) ? ($newParams['do-ip6'] === 'yes' ? 'yes' : 'no') : ($oldConfig['do-ip6'] ?? 'no');
        if ($ip4 === 'no' && $ip6 === 'no') $ip4 = 'yes';

        $genContent = "    verbosity: " . (!empty($newParams['verbosity']) ? (int)$newParams['verbosity'] : 1) . "\n";
        $genContent .= "    chroot: \"\"\n";
        $genContent .= "    directory: \"/etc/unbound\"\n";
        $genContent .= "    username: \"unbound\"\n";
        if (!empty($oldConfig['module-config'])) {
            $genContent .= "    module-config: \"{$oldConfig['module-config']}\"\n";
        }
        $genContent .= "    num-threads: {$threads}\n";
        $genContent .= "    do-ip4: {$ip4}\n";
        $genContent .= "    do-ip6: {$ip6}\n";
        $genContent .= "    do-udp: yes\n";
        $genContent .= "    do-tcp: yes\n";
        $genContent .= "    log-queries: yes\n";
        $genContent .= "    log-replies: yes\n";
        $genContent .= "    log-time-ascii: yes\n";
        $genContent .= "    extended-statistics: yes\n";
        $genContent .= "    statistics-interval: 0\n";
        $genContent .= "    statistics-cumulative: no\n";
        if (!empty($newParams['edns-buffer-size'])) $genContent .= "    edns-buffer-size: {$newParams['edns-buffer-size']}\n";
        $edns = $newParams['edns-buffer-size'] ?? $oldConfig['edns-buffer-size'] ?? '';
        if (!empty($edns)) $genContent .= "    edns-buffer-size: {$edns}\n";
        if (file_exists('/etc/unbound/root.hints')) $genContent .= "    root-hints: \"/etc/unbound/root.hints\"\n";
        // Bloco TLS:
        //   - `tls-port`/`https-port` SÓ escritas quando master switch on
        //     (estas portas + interface@porta = listener TLS efetivo).
        //   - `tls-service-pem`/`tls-service-key` escritos SEMPRE que houver
        //     valor (fallback pro oldConfig). Unbound ignora paths sem porta
        //     configurada, mas mantê-los persiste o "qual cert usar" quando
        //     o user ligar o toggle depois. Era a causa do bug v2.30.1 onde
        //     paths sumiam silenciosamente do general.conf após save.
        $finalTlsKey = $newParams['tls-service-key'] ?? $oldConfig['tls-service-key'] ?? '';
        $finalTlsPem = $newParams['tls-service-pem'] ?? $oldConfig['tls-service-pem'] ?? '';

        if (!empty($newParams['_tls_enabled'])) {
            $finalTlsPort   = (int) ($newParams['_tls_port_num']   ?? 0);
            $finalHttpsPort = (int) ($newParams['_https_port_num'] ?? 0);
            if ($finalTlsPort > 0)   $genContent .= "    tls-port: {$finalTlsPort}\n";
            if ($finalHttpsPort > 0) $genContent .= "    https-port: {$finalHttpsPort}\n";
        }
        if (!empty($finalTlsKey)) $genContent .= "    tls-service-key: \"{$finalTlsKey}\"\n";
        if (!empty($finalTlsPem)) $genContent .= "    tls-service-pem: \"{$finalTlsPem}\"\n";
        $configs['general'] = $genContent;

        // 3. Optimization
        $optFlags = ['prefetch', 'minimal-responses', 'serve-expired', 'rrset-roundrobin', 'qname-minimisation', 'aggressive-nsec', 'prefetch-key'];
        $optContent = "";
        foreach ($optFlags as $flag) {
            $val = (isset($newParams[$flag]) && $newParams[$flag] === 'yes') ? 'yes' : 'no';
            if (isset($newParams[$flag])) {
                $val = $newParams[$flag] === 'yes' ? 'yes' : 'no';
            } else {
                $val = ($oldConfig[$flag] ?? 'no') === 'yes' ? 'yes' : 'no';
            }
            $optContent .= "    {$flag}: {$val}\n";
        }
        $optVars = ['cache-min-ttl', 'cache-max-ttl', 'infra-host-ttl', 'infra-lame-ttl', 'infra-cache-numhosts', 'infra-cache-lame-size', 'neg-cache-size'];
        foreach ($optVars as $var) {
            $val = $filterVal($var, $newParams[$var] ?? '');
            $val = $filterVal($var, $newParams[$var] ?? $oldConfig[$var] ?? '');
            if (!empty($val)) $optContent .= "    {$var}: {$val}\n";
        }
        $configs['optimization'] = $optContent;

        // 4. Performance
        $perfContent = "";
        $slabs = !empty($newParams['cache-slabs']) ? (int)$newParams['cache-slabs'] : $threads;
        $mSlabs = $filterVal('msg-cache-slabs', $newParams['msg-cache-slabs'] ?? '');
        $rSlabs = $filterVal('rrset-cache-slabs', $newParams['rrset-cache-slabs'] ?? '');
        $iSlabs = $filterVal('infra-cache-slabs', $newParams['infra-cache-slabs'] ?? '');
        $kSlabs = $filterVal('key-cache-slabs', $newParams['key-cache-slabs'] ?? '');
        $slabsVal = $newParams['cache-slabs'] ?? $oldConfig['cache-slabs'] ?? '';
        $slabs = !empty($slabsVal) ? (int)$slabsVal : $threads;
        $mSlabs = $filterVal('msg-cache-slabs', $newParams['msg-cache-slabs'] ?? $oldConfig['msg-cache-slabs'] ?? '');
        $rSlabs = $filterVal('rrset-cache-slabs', $newParams['rrset-cache-slabs'] ?? $oldConfig['rrset-cache-slabs'] ?? '');
        $iSlabs = $filterVal('infra-cache-slabs', $newParams['infra-cache-slabs'] ?? $oldConfig['infra-cache-slabs'] ?? '');
        $kSlabs = $filterVal('key-cache-slabs', $newParams['key-cache-slabs'] ?? $oldConfig['key-cache-slabs'] ?? '');

        $perfContent .= "    msg-cache-slabs: " . (!empty($mSlabs) ? $mSlabs : $slabs) . "\n";
        $perfContent .= "    rrset-cache-slabs: " . (!empty($rSlabs) ? $rSlabs : $slabs) . "\n";
        if (!empty($iSlabs)) $perfContent .= "    infra-cache-slabs: {$iSlabs}\n";
        if (!empty($kSlabs)) $perfContent .= "    key-cache-slabs: {$kSlabs}\n";

        $msgSize = $filterVal('msg-cache-size', $newParams['msg-cache-size'] ?? '50m');
        $msgSize = $filterVal('msg-cache-size', $newParams['msg-cache-size'] ?? $oldConfig['msg-cache-size'] ?? '50m');
        $perfContent .= "    msg-cache-size: " . (!empty($msgSize) ? $msgSize : '50m') . "\n";

        $rrSize = $filterVal('rrset-cache-size', $newParams['rrset-cache-size'] ?? '');
        $rrSize = $filterVal('rrset-cache-size', $newParams['rrset-cache-size'] ?? $oldConfig['rrset-cache-size'] ?? '');
        if (!empty($rrSize)) {
            $perfContent .= "    rrset-cache-size: {$rrSize}\n";
        } else {
            if (preg_match('/^(\d+)([mkg])$/i', $msgSize, $m)) {
                $perfContent .= "    rrset-cache-size: " . ((int)$m[1] * 2) . $m[2] . "\n";
            } else {
                $perfContent .= "    rrset-cache-size: 100m\n";
            }
        }

        $perfVars = ['outgoing-range', 'outgoing-port-avoid', 'outgoing-port-permit', 'num-queries-per-thread', 'delay-close'];
        foreach ($perfVars as $var) {
            $val = $filterVal($var, $newParams[$var] ?? '');
            $val = $filterVal($var, $newParams[$var] ?? $oldConfig[$var] ?? '');
            if (!empty($val)) $perfContent .= "    {$var}: {$val}\n";
        }
        if (isset($newParams['so-reuseport']) && $newParams['so-reuseport'] === 'yes') $perfContent .= "    so-reuseport: yes\n";
        
        $soReuse = 'no';
        if (isset($newParams['so-reuseport'])) {
            $soReuse = $newParams['so-reuseport'] === 'yes' ? 'yes' : 'no';
        } else {
            $soReuse = ($oldConfig['so-reuseport'] ?? 'no') === 'yes' ? 'yes' : 'no';
        }
        if ($soReuse === 'yes') $perfContent .= "    so-reuseport: yes\n";
        
        $configs['performance'] = $perfContent;

        // 5. Security (DNSSEC + Access Control + Hardening)
        $dnssec = (isset($newParams['dnssec-enabled'])) ? ($newParams['dnssec-enabled'] === 'yes' ? 'yes' : 'no') : ($oldConfig['dnssec-enabled'] ?? 'yes');
        $secContent = "";
        if ($dnssec === 'yes') {
            $secContent .= "    auto-trust-anchor-file: \"/var/lib/unbound/root.key\"\n";
        }

        $secFlags = ['hide-identity', 'hide-version', 'harden-glue', 'harden-algo-downgrade', 'harden-below-nxdomain', 'harden-dnssec-stripped', 'harden-large-queries', 'harden-referral-path', 'harden-short-bufsize', 'do-not-query-localhost', 'deny-any', 'use-caps-for-id', 'val-clean-additional'];
        foreach ($secFlags as $flag) {
            if (isset($newParams[$flag])) {
                $secContent .= "    {$flag}: " . ($newParams[$flag] === 'yes' ? 'yes' : 'no') . "\n";
            } elseif (isset($oldConfig[$flag]) && $oldConfig[$flag] !== '') {
                $secContent .= "    {$flag}: {$oldConfig[$flag]}\n";
            }
        }

        if (!empty($newParams['ratelimit'])) $secContent .= "    ratelimit: {$newParams['ratelimit']}\n";
        if (!empty($newParams['unwanted-reply-threshold'])) $secContent .= "    unwanted-reply-threshold: {$newParams['unwanted-reply-threshold']}\n";

        $acParams = isset($newParams['access-control']) && is_array($newParams['access-control']) ? $newParams['access-control'] : $oldConfig['access-control'];
        if (!empty($acParams)) {
            foreach ($acParams as $ac) {
                if (!empty($ac['ip']) && !empty($ac['action'])) {
                    $secContent .= "    access-control: {$ac['ip']} {$ac['action']}\n";
                }
            }
        } else {
            $secContent .= "    access-control: 127.0.0.0/8 allow\n";
        }

        $dnqParams = isset($newParams['do-not-query-address']) && is_array($newParams['do-not-query-address']) ? $newParams['do-not-query-address'] : $oldConfig['do-not-query-address'];
        if (!empty($dnqParams)) {
            foreach ($dnqParams as $dnq) {
                if (!empty($dnq)) $secContent .= "    do-not-query-address: {$dnq}\n";
            }
        }

        $configs['security'] = $secContent;

        // 6. Forwarders
        $fwdContent = "";
        if (!empty($newParams['forward-zones']) && is_array($newParams['forward-zones'])) {
            foreach ($newParams['forward-zones'] as $zone) {
                if (!empty($zone['addresses'])) {
                    $fwdContent .= "\nforward-zone:\n";
                    $fwdContent .= "    name: \"{$zone['name']}\"\n";
                    foreach ($zone['addresses'] as $addr) {
                        $fwdContent .= "    forward-addr: {$addr}\n";
                    }
                }
            }
        }
        $configs['forwarders'] = $fwdContent;

        return $configs;
    }

    public function generateRawConfig(array $newParams): string
    {
        $content = "server:\n";
        $content .= "    # Configurações Modulares\n";
        foreach ($this->modularFiles as $key => $path) {
            if ($key === 'forwarders') continue; // Fora do bloco server
            $content .= "    include: \"{$path}\"\n";
        }
        // Include bloqueios
        $content .= "    include: \"{$this->blockedConfPath}\"\n";
        // Include Anti-DoH (DNS-over-HTTPS endpoints) — sempre incluso; conteúdo é vazio se feature off.
        $content .= "    include: \"{$this->antiDohConfPath}\"\n";

        $content .= "\n# Remote Control\n";
        $content .= "remote-control:\n";
        $content .= "    control-enable: yes\n";
        $content .= "    control-interface: 127.0.0.1\n";
        $content .= "    server-key-file: \"/etc/unbound/unbound_server.key\"\n";
        $content .= "    server-cert-file: \"/etc/unbound/unbound_server.pem\"\n";
        $content .= "    control-key-file: \"/etc/unbound/unbound_control.key\"\n";
        $content .= "    control-cert-file: \"/etc/unbound/unbound_control.pem\"\n";

        $content .= "\n# Forward Zones\n";
        $content .= "include: \"{$this->modularFiles['forwarders']}\"\n";

        return $content;
    }

    /**
     * Aplica a configuração checando a sintaxe e usando elevação de privilégio via sudo.
     * Retorna array com success: booleano e message: texto do erro ou sucesso.
     */
    /**
     * Checa se alguma das portas pedidas já tem outro processo escutando.
     * Retorna lista de mensagens de conflito. Vazio = tudo OK.
     *
     * Filtra o próprio unbound do resultado (porque ele DEVE estar escutando
     * em 53 e tudo bem se já há listener nas portas DoT/DoH atuais — só
     * vamos re-bind). Loopback é ignorado (TLS local não conflita com web).
     */
    private function _findPortConflicts(array $ports): array
    {
        if (empty($ports)) return [];
        $ssOut = []; $ret = 0;
        // `ss -ltnp` mostra pid/process — precisa de privilégio elevado pra
        // ver processos de outros donos, mas tudo bem mostrar só a porta.
        \App\ShellHelper::exec('/usr/bin/ss', ['-ltnp'], $ssOut, $ret, false);
        if ($ret !== 0) return []; // sem ss, melhor permitir do que falhar fechado

        $conflicts = [];
        foreach ($ports as $label => $portNum) {
            $portNum = (int) $portNum;
            if ($portNum <= 0) continue;
            foreach ($ssOut as $line) {
                // Match na coluna Local Address:Port — pega o lado esquerdo do espaço
                // Formato: "LISTEN 0 4096 10.0.0.1:443 0.0.0.0:* users:((\"apache2\",pid=...))"
                if (!preg_match('/\s+([\d\.\*\[\]:a-f]+):(\d+)\s+/i', $line, $m)) continue;
                if ((int) $m[2] !== $portNum) continue;
                $bindAddr = $m[1];
                // Skip se é o próprio unbound — extrai o nome do processo da linha
                if (preg_match('/users:\(\("([^"]+)"/', $line, $procMatch)) {
                    if ($procMatch[1] === 'unbound') continue;
                    $conflicts[] = "porta $portNum ({$label}) já está ocupada por '{$procMatch[1]}' em {$bindAddr}";
                } else {
                    $conflicts[] = "porta $portNum ({$label}) já está em uso em {$bindAddr}";
                }
            }
        }
        return array_values(array_unique($conflicts));
    }

    public function applyConfig(array $newParams): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return ['success' => true, 'message' => 'Configuração simulada e salva com sucesso no Windows!'];
        }

        // Pré-flight: se o user pediu pra habilitar DoT/DoH, checa se algum
        // OUTRO processo já está escutando nas portas configuradas. Sem isso,
        // o Unbound aceita o config e morre no startup com "Address already
        // in use" (foi o que aconteceu quando user tentou DoH=443 com Apache
        // já servindo o dashboard em 443).
        $tlsEnabledRequest = ($newParams['tls-enabled'] ?? null) === 'yes';
        if ($tlsEnabledRequest) {
            $portsToTest = [
                'DoT'  => (int) ($newParams['tls-port']   ?? 0),
                'DoH'  => (int) ($newParams['https-port'] ?? 0),
            ];
            $conflicts = $this->_findPortConflicts($portsToTest);
            if (!empty($conflicts)) {
                return [
                    'success' => false,
                    'message' => 'Conflito de porta: ' . implode('; ', $conflicts) .
                        '. Mude pra uma porta livre (ex: 8443 pra DoH se Apache ocupa 443) ou pare o serviço conflitante antes de habilitar.',
                ];
            }
        }

        if (isset($newParams['blocked_domains'])) {
            $this->saveBlocklist($newParams['blocked_domains']);
            $this->generateBlockedDomainsConf($newParams['blocked_domains']);
        } else {
            // Garante que o aqruivo temporário existe para o checkconf
            $this->generateBlockedDomainsConf($this->loadBlocklist());
        }

        // Anti-DoH: lê o estado de newParams se vier; senão, do settings persistido.
        $antiDohEnabled = $newParams['anti_doh_enabled']
            ?? ((bool) ($this->loadSettings()['anti_doh_enabled'] ?? false));
        $this->generateAntiDohConf((bool) $antiDohEnabled);

        if (isset($newParams['local_records'])) {
            $this->saveLocalRecords($newParams['local_records']);
            $this->generateLocalRecordsConf($newParams['local_records']);
        } else {
            $this->generateLocalRecordsConf($this->loadLocalRecords());
        }

        // Move blocklist conf se foi alterada (ou se estamos migrando)
        \App\ShellHelper::exec('/usr/bin/cp', [$this->tempBlockedConfPath, $this->blockedConfPath], $cpBlockedOutput, $cpBlockedReturn, true);
        if ($cpBlockedReturn !== 0) {
            return ['success' => false, 'message' => "Falha ao salvar lista de bloqueio modular:\n" . implode("\n", $cpBlockedOutput)];
        }

        // Move Anti-DoH conf (mesmo padrão do blocked_domains)
        \App\ShellHelper::exec('/usr/bin/cp', [$this->tempAntiDohPath, $this->antiDohConfPath], $cpAntiDohOutput, $cpAntiDohReturn, true);
        if ($cpAntiDohReturn !== 0) {
            return ['success' => false, 'message' => "Falha ao salvar filtro Anti-DoH:\n" . implode("\n", $cpAntiDohOutput)];
        }

        \App\ShellHelper::exec('/usr/bin/cp', [$this->tempLocalRecordsPath, $this->modularFiles['local_records']], $cpLocalOutput, $cpLocalReturn, true);

        $tempDir = dirname(__FILE__) . '/data/tmp/';
        // 1. Gera e salva arquivos modulares temporários
        $modularConfigs = $this->generateModularConfigs($newParams);
        foreach ($modularConfigs as $key => $content) {
            $tempPath = $tempDir . "unbound_{$key}.conf";
            file_put_contents($tempPath, "# Arquivo modular: {$key}\n" . $content);
            chmod($tempPath, 0664);
        }

        // 2. Gera o novo unbound.conf (container)
        $raw = $this->generateRawConfig($newParams);
        file_put_contents($this->tempConfigPath, $raw);
        chmod($this->tempConfigPath, 0664);

        // 3. Checa sintaxe (usando o tempConfigPath que já faz include dos temps em /tmp se precisássemos, 
        // mas aqui vamos mover primeiro para conf.d e checar o final)
        // Na verdade, para checar sintaxe ANTES de quebrar o original, precisamos que o tempConfigPath
        // aponte para os arquivos certos.
        // VAMOS FAZER UM TRUQUE: Mover para o destino FINAL e se falhar no checkconf, tentar restaurar?
        // Ou melhor: Gerar um unbund.conf temporário que aponta para os /tmp/unbound_*.conf

        $checkRaw = "server:\n";
        foreach (array_keys($this->modularFiles) as $key) {
            if ($key === 'forwarders' || $key === 'local_records') continue;
            $checkRaw .= "    include: \"" . $tempDir . "unbound_{$key}.conf\"\n";
        }
        $checkRaw .= "    include: \"{$this->tempBlockedConfPath}\"\n";
        $checkRaw .= "    include: \"{$this->tempAntiDohPath}\"\n";
        $checkRaw .= "    include: \"{$this->tempLocalRecordsPath}\"\n";
        $checkRaw .= "\ninclude: \"" . $tempDir . "unbound_forwarders.conf\"\n";

        $checkTempPath = $tempDir . "unbound_check.conf";
        file_put_contents($checkTempPath, $checkRaw);

        \App\ShellHelper::exec('/usr/sbin/unbound-checkconf', [$checkTempPath], $checkOutput, $checkReturn, true);

        if ($checkReturn !== 0) {
            return ['success' => false, 'message' => "Erro de sintaxe modular:\n" . implode("\n", $checkOutput)];
        }

        // 4. Move todos os arquivos para o destino final
        foreach (array_keys($this->modularFiles) as $key) {
            $dest = $this->modularFiles[$key];
            $src = $tempDir . "unbound_{$key}.conf";
            
            // Pula se o arquivo temporário não existe (ex: local_records que é tratado separadamente como .tmp)
            if (!file_exists($src)) continue;

            \App\ShellHelper::exec('/usr/bin/cp', [$src, $dest], $cpModularOutput, $cpModularReturn, true);
            if ($cpModularReturn !== 0) {
                return ['success' => false, 'message' => "Erro ao mover arquivo modular {$key}:\n" . implode("\n", $cpModularOutput)];
            }
        }

        // 5. Move o unbound.conf master
        \App\ShellHelper::exec('/usr/bin/cp', [$this->tempConfigPath, $this->configPath], $cpOutput, $cpReturn, true);

        if ($cpReturn !== 0) {
            return ['success' => false, 'message' => "Falha ao sobrescrever unbound.conf:\n" . implode("\n", $cpOutput)];
        }

        // 6. Reinicia Unbound (Debian 13 default é systemd)
        \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', 'unbound'], $restartOutput, $restartReturn, true);

        // Garantir permissões corretas pós-mudança
        \App\ShellHelper::exec('/usr/local/bin/unbound-health-fix.sh', [], $healthOutput, $healthReturn, true);

        return ['success' => true, 'message' => 'Configuração modular aplicada com sucesso!'];
    }

    public function loadBlocklist(): array
    {
        if (!file_exists($this->blocklistJsonPath)) return [];
        return json_decode(file_get_contents($this->blocklistJsonPath), true) ?: [];
    }

    public function saveBlocklist(array $domains): void
    {
        file_put_contents($this->blocklistJsonPath, json_encode(array_values(array_unique($domains)), JSON_PRETTY_PRINT));
    }

    public function generateBlockedDomainsConf(array $domains): void
    {
        $content = "# Arquivo gerado automaticamente pelo Unbound Dashboard\n";
        $content .= "server:\n";

        // 1. Inserir lista oficial se habilitada
        $settings = $this->loadSettings();
        if ($settings['official_blocklist_enabled'] ?? false) {
            if (file_exists($this->officialBlocklistPath)) {
                $content .= "\n    # --- LISTA OFICIAL ANATEL ---\n";
                // Indenta as linhas da lista oficial para ficarem dentro do bloco server
                $officialLines = explode("\n", file_get_contents($this->officialBlocklistPath));
                foreach ($officialLines as $line) {
                    if (trim($line) !== "") {
                        $content .= "    " . trim($line) . "\n";
                    }
                }
                $content .= "    # --- FIM LISTA OFICIAL ---\n\n";
            }
        }

        foreach ($domains as $domain) {
            $domain = trim($domain, " .");
            if (!empty($domain)) {
                $content .= "    local-zone: \"{$domain}.\" static\n";
                $content .= "    local-data: \"{$domain}. IN A 0.0.0.0\"\n";
            }
        }
        file_put_contents($this->tempBlockedConfPath, $content);
        chmod($this->tempBlockedConfPath, 0664);
    }

    /**
     * Carrega a lista curada de endpoints DoH (JSON shippado com o dashboard).
     * Linhas vazias e comentários (`#`) são ignorados pra suportar edição manual.
     */
    public function loadAntiDohHosts(): array
    {
        if (!file_exists($this->antiDohHostsJsonPath)) return [];
        $raw = json_decode(file_get_contents($this->antiDohHostsJsonPath), true);
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $entry) {
            $h = strtolower(trim((string) $entry));
            if ($h === '' || $h[0] === '#') continue;
            $out[] = $h;
        }
        return array_values(array_unique($out));
    }

    /**
     * Escreve o arquivo de Anti-DoH no temp path. Se `$enabled=false`, gera
     * arquivo válido mas vazio (apenas `server:` sem zones) — assim o
     * `include:` no unbound.conf continua sempre válido.
     */
    public function generateAntiDohConf(bool $enabled): void
    {
        $content  = "# Anti-DoH Filter — DNS-over-HTTPS endpoints conhecidos.\n";
        $content .= "# Auto-gerado pelo Unbound Dashboard. NÃO edite à mão.\n";
        $content .= "# Toggle em Configurações → Lista de Bloqueios.\n";
        $content .= "server:\n";
        if ($enabled) {
            foreach ($this->loadAntiDohHosts() as $host) {
                $content .= "    local-zone: \"{$host}.\" always_nxdomain\n";
            }
        }
        file_put_contents($this->tempAntiDohPath, $content);
        @chmod($this->tempAntiDohPath, 0664);
    }

    public function loadLocalRecords(): array
    {
        if (!file_exists($this->localRecordsJsonPath)) return [];
        return json_decode(file_get_contents($this->localRecordsJsonPath), true) ?: [];
    }

    public function saveLocalRecords(array $records): void
    {
        file_put_contents($this->localRecordsJsonPath, json_encode(array_values($records), JSON_PRETTY_PRINT));
    }

    public function generateLocalRecordsConf(array $records): void
    {
        $content = "# Registros Locais Customizados (Unbound Dashboard)\nserver:\n";
        foreach ($records as $r) {
            $name = trim($r['name'], " .");
            $type = trim($r['type']);
            $val  = trim($r['value']);
            if (!empty($name) && !empty($type) && !empty($val)) {
                $content .= "    local-data: \"{$name}. IN {$type} {$val}\"\n";
            }
        }
        file_put_contents($this->tempLocalRecordsPath, $content);
        chmod($this->tempLocalRecordsPath, 0664);
    }

    public function syncOfficialBlocklist(string $url): bool
    {
        $content = @file_get_contents($url);
        if ($content === false) return false;

        // Save to flat file for Unbound include
        if (file_put_contents($this->officialBlocklistPath, $content) === false) return false;

        // --- Sync com DuckDB via FastAPI (era MariaDB) — Dashboard Intelligence ---
        try {
            require_once __DIR__ . '/ApiClient.php';
            $jwt = $_SESSION['api_jwt'] ?? '';

            if ($jwt !== '') {
                // 1. Remove judicial entries antigas
                \App\ApiClient::post('/api/v1/blocklist/clear-category', $jwt, ['category' => 'Judicial']);

                // 2. Parse domains do flat file
                $lines = explode("\n", $content);
                $domains = [];
                foreach ($lines as $line) {
                    if (preg_match('/local-zone:\s*"([^"]+)"/', $line, $m)) {
                        $domains[] = strtolower($m[1]);
                    }
                }

                // 3. Bulk insert em chunks (FastAPI processa cada UPSERT)
                if (!empty($domains)) {
                    $chunks = array_chunk(array_unique($domains), 1000);
                    foreach ($chunks as $chunk) {
                        $payload = array_map(static function (string $d): array {
                            return ['domain' => $d, 'category' => 'Judicial', 'severity' => 'High'];
                        }, $chunk);
                        \App\ApiClient::post('/api/v1/blocklist/bulk-insert', $jwt, $payload);
                    }
                }

                // Atualiza cache de contagens da blocklist
                $countsFile = __DIR__ . '/../data/blocklist_counts.json';
                $resp = \App\ApiClient::get('/api/v1/blocklist/counts', $jwt);
                if ($resp['ok'] && is_array($resp['data'])) {
                    file_put_contents($countsFile, json_encode([
                        'adware'     => (int) ($resp['data']['adware'] ?? 0),
                        'phishing'   => (int) ($resp['data']['phishing'] ?? 0),
                        'judicial'   => (int) ($resp['data']['judicial'] ?? 0),
                        'updated_at' => time(),
                    ]));
                }
            }
        } catch (\Exception $e) {
            error_log('Error syncing Judicial list via FastAPI: ' . $e->getMessage());
        }

        return true;
    }

    public function loadSettings(): array
    {
        if (!file_exists($this->settingsPath)) return [];
        return json_decode(file_get_contents($this->settingsPath), true) ?: [];
    }

    public function saveSettings(array $settings): void
    {
        $oldSettings = $this->loadSettings();
        file_put_contents($this->settingsPath, json_encode($settings, JSON_PRETTY_PRINT));

        // Se o horário mudou ou se o status de habilitado mudou, atualiza crontab
        if (($settings['official_blocklist_update_time'] ?? '') !== ($oldSettings['official_blocklist_update_time'] ?? '') ||
            ($settings['official_blocklist_enabled'] ?? false) !== ($oldSettings['official_blocklist_enabled'] ?? false)
        ) {
            $this->updateCrontab($settings);
        }
    }

    public function updateCrontab(array $settings): void
    {
        $enabled = $settings['official_blocklist_enabled'] ?? false;
        $time = $settings['official_blocklist_update_time'] ?? '03:00';

        // Remove entrada anterior do dashboard se existir
        \App\ShellHelper::exec('/usr/bin/crontab', ['-l'], $output, $return, false);
        $newCrontab = [];
        $identifier = "# UNBOUND-DASHBOARD-SYNC";

        foreach ($output as $line) {
            if (strpos($line, $identifier) === false) {
                $newCrontab[] = $line;
            }
        }

        if ($enabled && !empty($time)) {
            $parts = explode(':', $time);
            $hour = intval($parts[0] ?? 3);
            $min = intval($parts[1] ?? 0);

            $scriptPath = dirname(__FILE__) . '/scripts/sync_judicial_list.php';
            $cronLine = "{$min} {$hour} * * * /usr/bin/php {$scriptPath} >> /tmp/unbound_sync.log 2>&1 {$identifier}";
            $newCrontab[] = $cronLine;
        }

        $tempCrontab = "/tmp/crontab_www_data.tmp";
        file_put_contents($tempCrontab, implode("\n", $newCrontab) . "\n");
        \App\ShellHelper::exec('/usr/bin/crontab', [$tempCrontab], $crontabOutput, $crontabReturn, false);
        unlink($tempCrontab);
    }

    /**
     * Retorna config default caso não consiga ler (útil no Windows)
     */
    private function getMockConfig(): string
    {
        return "server:
    verbosity: 1
    port: 53
    do-ip4: yes
    do-ip6: yes
    do-udp: yes
    do-tcp: yes
    
    access-control: 127.0.0.0/8 allow
    access-control: 192.168.0.0/16 allow
    
    cache-slabs: 4
    msg-cache-size: 50m
    
    auto-trust-anchor-file: \"/var/lib/unbound/root.key\"
";
    }
}
