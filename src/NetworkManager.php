<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

class NetworkManager {

    const NETPLAN_FILE = '/etc/netplan/99-unbound-dashboard.yaml';
    const NETPLAN_TMP  = '/tmp/unbound-dashboard-netplan.yaml';
    const NETPLAN_BACKUP_DIR = '/var/backups/unbound-dashboard';
    const LOCK_DIR = __DIR__ . '/data/tmp/locks';

    /**
     * Executa $work com lock exclusivo (LOCK_EX | LOCK_NB) em
     * `data/tmp/locks/<category>.lock`. Se outro admin já está
     * escrevendo na mesma categoria (interfaces / dns / ntp / hostname),
     * retorna failure imediato com mensagem clara — não bloqueia.
     *
     * Categorias previne race entre dois admins editando ao mesmo tempo:
     * dois rsync simultâneos no mesmo arquivo de config = corrupção
     * silenciosa.
     */
    private function _withCategoryLock(string $category, callable $work): array {
        // Sanitiza nome do lock pra evitar path traversal
        $category = preg_replace('/[^a-z0-9_-]/i', '', $category) ?: 'misc';
        $lockDir = self::LOCK_DIR;
        @mkdir($lockDir, 0775, true);
        $lockFile = $lockDir . '/' . $category . '.lock';
        $fp = @fopen($lockFile, 'c');
        if ($fp === false) {
            return ['success' => false, 'message' => "Não foi possível abrir lock file ($lockFile)"];
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return [
                'success' => false,
                'message' => "Outra operação de rede ($category) já está em andamento — aguarde alguns segundos e tente novamente.",
            ];
        }
        try {
            // Registra quem pegou o lock (informativo, debug)
            ftruncate($fp, 0);
            fwrite($fp, sprintf("pid=%d ts=%d category=%s\n", getmypid(), time(), $category));
            fflush($fp);
            return $work();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Detecta o backend de rede em uso.
     *
     * @return string 'netplan' | 'ifupdown'
     */
    public function detectBackend(): string {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $hasNetplanBin = is_executable('/usr/sbin/netplan');
        $hasNetplanDir = is_dir('/etc/netplan');
        $hasYaml = false;
        if ($hasNetplanDir) {
            $files = glob('/etc/netplan/*.yaml') ?: [];
            $hasYaml = count($files) > 0;
        }

        // Considera netplan ativo se o binário existe E há YAML configurado
        // (mesmo que seja só o /etc/netplan/50-cloud-init.yaml do cloud-init).
        if ($hasNetplanBin && $hasYaml) {
            $cached = 'netplan';
        } else {
            $cached = 'ifupdown';
        }
        return $cached;
    }

    /**
     * Detecta o renderer pra netplan: networkd ou NetworkManager.
     */
    private function detectNetplanRenderer(): string {
        $out = [];
        $ret = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', '--quiet', 'NetworkManager'], $out, $ret, false);
        if ($ret === 0) {
            return 'NetworkManager';
        }
        return 'networkd';
    }

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
     * Retorna a lista de fusos válidos. Tenta primeiro `timezone_identifiers_list()`
     * (rápido, sem I/O). Se PHP retorna vazio (ex: tzdata ausente em container
     * mínimo), faz fallback varrendo `/usr/share/zoneinfo/` em disco — é o que
     * o `timedatectl` também usa. Garantia de lista sempre populada na UI.
     */
    public function getAvailableTimezones(): array {
        $tz = @timezone_identifiers_list();
        if (is_array($tz) && count($tz) > 0) {
            return $tz;
        }
        return $this->_scanZoneinfoDir();
    }

    /**
     * Fallback: lê /usr/share/zoneinfo recursivamente e retorna IDs
     * (Continent/City). Filtra arquivos não-zona (zone.tab, posix, right…).
     */
    private function _scanZoneinfoDir(): array {
        $base = '/usr/share/zoneinfo';
        if (!is_dir($base)) return ['UTC'];
        $skip = ['posix', 'right', 'leapseconds', 'zone.tab', 'zone1970.tab', 'iso3166.tab', 'tzdata.zi', 'localtime', 'leap-seconds.list'];
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $rel = ltrim(substr($file->getPathname(), strlen($base)), '/');
            $first = explode('/', $rel, 2)[0] ?? '';
            if (in_array($first, $skip, true)) continue;
            // IDs canônicos têm pelo menos um '/' (Continent/City) ou são especiais (UTC, GMT, etc).
            // Pula nomes que parecem arquivos auxiliares.
            if (preg_match('/\.(tab|zi|list)$/', $rel)) continue;
            $out[] = $rel;
        }
        sort($out);
        return $out ?: ['UTC'];
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
     * Altera o hostname do sistema e atualiza /etc/hosts.
     * Requer permissão sudo para 'hostnamectl' e 'mv ... /etc/hosts'.
     */
    public function setHostname(string $newHostname): array {
        $newHostname = preg_replace('/[^a-zA-Z0-9.-]/', '', $newHostname);
        if (empty($newHostname)) {
            return ['success' => false, 'message' => 'Hostname inválido.'];
        }
        return $this->_withCategoryLock('hostname', function() use ($newHostname) {
            $oldHostname = gethostname() ?: '';
            $output = [];
            $returnVar = 0;
            \App\ShellHelper::exec('/usr/bin/hostnamectl', ['set-hostname', $newHostname], $output, $returnVar, true);
            if ($returnVar !== 0) {
                return ['success' => false, 'message' => 'Erro ao alterar hostname: ' . implode(" ", $output)];
            }
            $hostsRes = $this->updateEtcHosts($oldHostname, $newHostname);
            if (!$hostsRes['success']) {
                return ['success' => true, 'message' => 'Hostname alterado para ' . $newHostname . ' (mas /etc/hosts não pôde ser atualizado: ' . $hostsRes['message'] . ')'];
            }
            return ['success' => true, 'message' => 'Hostname alterado para ' . $newHostname . ' e /etc/hosts sincronizado.'];
        });
    }

    /**
     * Substitui o hostname antigo pelo novo em /etc/hosts na linha 127.0.1.1.
     * Se a linha não existir, adiciona. Operação tolerante a falhas.
     */
    private function updateEtcHosts(string $oldHostname, string $newHostname): array {
        if (!file_exists('/etc/hosts')) {
            return ['success' => false, 'message' => '/etc/hosts ausente'];
        }
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $newHostname)) {
            return ['success' => false, 'message' => 'Nome inválido'];
        }
        $lines = file('/etc/hosts');
        $newLines = [];
        $replaced = false;
        foreach ($lines as $line) {
            // Linha 127.0.1.1 (convenção Debian/Ubuntu pra hostname local)
            if (preg_match('/^\s*127\.0\.1\.1\s+/', $line)) {
                $newLines[] = "127.0.1.1\t$newHostname\n";
                $replaced = true;
                continue;
            }
            $newLines[] = $line;
        }
        if (!$replaced) {
            $newLines[] = "127.0.1.1\t$newHostname\n";
        }

        $tmpFile = dirname(__FILE__) . '/data/tmp/hosts.new';
        @mkdir(dirname($tmpFile), 0775, true);
        if (@file_put_contents($tmpFile, implode('', $newLines)) === false) {
            return ['success' => false, 'message' => "Erro ao escrever $tmpFile"];
        }

        $out = []; $ret = 0;
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, '/etc/hosts'], $out, $ret, true);
        if ($ret !== 0) {
            return ['success' => false, 'message' => 'mv falhou: ' . implode(' ', $out)];
        }
        return ['success' => true, 'message' => 'OK'];
    }

    /**
     * Detecta o backend de DNS em uso.
     *
     * @return string 'systemd-resolved' | 'file'
     */
    public function detectDnsBackend(): string {
        static $cached = null;
        if ($cached !== null) return $cached;

        $out = []; $ret = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', '--quiet', 'systemd-resolved'], $out, $ret, false);
        if ($ret === 0 && file_exists('/etc/systemd/resolved.conf')) {
            $cached = 'systemd-resolved';
        } else {
            $cached = 'file';
        }
        return $cached;
    }

    /**
     * Obtém os servidores DNS configurados no sistema. Em systemd-resolved,
     * lê de /etc/systemd/resolved.conf (chave DNS=); em backend 'file',
     * lê de /etc/resolv.conf.
     */
    public function getSystemDNS(): array {
        if ($this->detectDnsBackend() === 'systemd-resolved') {
            return $this->getSystemDNSResolved();
        }
        return $this->getSystemDNSFile();
    }

    private function getSystemDNSResolved(): array {
        $dns = [];
        if (!file_exists('/etc/systemd/resolved.conf')) return $dns;
        $lines = file('/etc/systemd/resolved.conf');
        foreach ($lines as $line) {
            // Ignora comentadas. Match: DNS=1.1.1.1 8.8.8.8 (espaço-separado)
            if (preg_match('/^DNS=\s*(.+)$/', trim($line), $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $ip) {
                    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $dns[] = $ip;
                    }
                }
                break;
            }
        }
        return $dns;
    }

    private function getSystemDNSFile(): array {
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
     * Atualiza os servidores DNS do sistema. Despacha entre
     * systemd-resolved (edita /etc/systemd/resolved.conf + restart) e
     * file (mv pra /etc/resolv.conf — legacy).
     */
    public function setSystemDNS(array $dnsArray): array {
        $validIps = [];
        foreach ($dnsArray as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $validIps[] = $ip;
            }
        }
        return $this->_withCategoryLock('dns', function() use ($validIps) {
            if ($this->detectDnsBackend() === 'systemd-resolved') {
                return $this->setSystemDNSResolved($validIps);
            }
            return $this->setSystemDNSFile($validIps);
        });
    }

    /**
     * Reescreve /etc/systemd/resolved.conf preservando outras chaves,
     * substituindo (ou inserindo) DNS= e fazendo restart do daemon.
     */
    private function setSystemDNSResolved(array $ips): array {
        $path = '/etc/systemd/resolved.conf';
        if (!file_exists($path)) {
            return ['success' => false, 'message' => "$path não encontrado — systemd-resolved instalado?"];
        }
        $lines = file($path);
        $newLines = [];
        $inResolve = false;
        $injected = false;
        $dnsLine = empty($ips) ? "#DNS=\n" : 'DNS=' . implode(' ', $ips) . "\n";

        foreach ($lines as $line) {
            $trim = rtrim($line);
            if (preg_match('/^\[Resolve\]\s*$/i', trim($trim))) {
                $inResolve = true;
                $newLines[] = $line;
                continue;
            }
            // Nova seção: se ainda não injetou, injeta antes de mudar de seção.
            if ($inResolve && preg_match('/^\[.+\]\s*$/', trim($trim))) {
                if (!$injected) {
                    $newLines[] = $dnsLine;
                    $injected = true;
                }
                $inResolve = false;
                $newLines[] = $line;
                continue;
            }
            // Linha DNS=... (ou #DNS=...) dentro de [Resolve] — substitui
            if ($inResolve && preg_match('/^#?DNS=/i', trim($trim))) {
                if (!$injected) {
                    $newLines[] = $dnsLine;
                    $injected = true;
                }
                // Não escreve a linha original (substituída)
                continue;
            }
            $newLines[] = $line;
        }
        // Se [Resolve] não existe no arquivo, adiciona
        if (!$injected) {
            $newLines[] = "\n[Resolve]\n";
            $newLines[] = $dnsLine;
        }

        $tmpFile = dirname(__FILE__) . '/data/tmp/resolved.conf.new';
        @mkdir(dirname($tmpFile), 0775, true);
        if (@file_put_contents($tmpFile, implode('', $newLines)) === false) {
            return ['success' => false, 'message' => "Erro ao escrever em $tmpFile"];
        }

        $out = []; $ret = 0;
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, $path], $out, $ret, true);
        if ($ret !== 0) {
            return ['success' => false, 'message' => 'mv falhou: ' . implode(' ', $out)];
        }

        $out2 = []; $ret2 = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', 'systemd-resolved'], $out2, $ret2, true);
        if ($ret2 !== 0) {
            return ['success' => false, 'message' => 'restart systemd-resolved falhou: ' . implode(' ', $out2)];
        }

        $msg = empty($ips)
            ? 'DNS limpo do resolved.conf — usando defaults do sistema.'
            : 'DNS do sistema atualizado via systemd-resolved: ' . implode(', ', $ips);
        return ['success' => true, 'message' => $msg];
    }

    /**
     * Caminho legacy: reescreve /etc/resolv.conf diretamente.
     */
    private function setSystemDNSFile(array $ips): array {
        $content = "# Gerado pelo Unbound Dashboard\n";
        foreach ($ips as $ip) {
            $content .= "nameserver $ip\n";
        }

        $tmpFile = dirname(__FILE__) . '/data/tmp/resolv_conf_new';
        @mkdir(dirname($tmpFile), 0775, true);
        file_put_contents($tmpFile, $content);
        $output = [];
        $returnVar = 0;
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, '/etc/resolv.conf'], $output, $returnVar, true);

        if ($returnVar === 0) {
            return ['success' => true, 'message' => 'DNS do sistema atualizado com sucesso.'];
        }
        return ['success' => false, 'message' => 'Erro ao aplicar DNS: ' . implode(" ", $output)];
    }

    /**
     * Lê a config de uma interface a partir do YAML do dashboard
     * (/etc/netplan/99-unbound-dashboard.yaml). Não tenta interpretar o
     * 50-cloud-init.yaml — só o nosso. Se a interface não estiver no nosso
     * YAML, retorna defaults (DHCP).
     */
    private function getInterfaceConfigNetplan(string $ifaceName): array {
        $config = [
            'mode' => 'dhcp', 'address' => '', 'gateway' => '', 'netmask' => '',
            'v6_enabled' => false, 'v6_mode' => 'static', 'v6_address' => '', 'v6_gateway' => '', 'v6_netmask' => ''
        ];

        if (!file_exists(self::NETPLAN_FILE) || !function_exists('yaml_parse_file')) {
            // Fallback: tenta ler como texto se php-yaml não estiver instalado.
            if (file_exists(self::NETPLAN_FILE)) {
                $data = $this->parseNetplanYamlFallback(self::NETPLAN_FILE);
            } else {
                return $config;
            }
        } else {
            $data = @yaml_parse_file(self::NETPLAN_FILE);
        }

        if (!is_array($data)) return $config;

        // network.ethernets.<ifaceName> — netplan padrão
        $eth = $data['network']['ethernets'][$ifaceName] ?? null;
        if (!is_array($eth)) return $config;

        // IPv4
        if (!empty($eth['dhcp4'])) {
            $config['mode'] = 'dhcp';
        } elseif (!empty($eth['addresses']) && is_array($eth['addresses'])) {
            $config['mode'] = 'static';
            foreach ($eth['addresses'] as $addr) {
                if (strpos($addr, ':') === false) {
                    // IPv4 CIDR (192.168.1.10/24)
                    if (preg_match('/^([0-9.]+)\/(\d+)$/', $addr, $m)) {
                        $config['address'] = $m[1];
                        $config['netmask'] = $m[2]; // CIDR
                    }
                }
            }
            // Gateway (routes ou gateway4 legacy)
            if (!empty($eth['routes']) && is_array($eth['routes'])) {
                foreach ($eth['routes'] as $r) {
                    if (($r['to'] ?? '') === 'default' && strpos($r['via'] ?? '', ':') === false) {
                        $config['gateway'] = $r['via'];
                    }
                }
            }
            if (empty($config['gateway']) && !empty($eth['gateway4'])) {
                $config['gateway'] = $eth['gateway4'];
            }
        }

        // IPv6
        $hasV6Static = false;
        if (!empty($eth['addresses']) && is_array($eth['addresses'])) {
            foreach ($eth['addresses'] as $addr) {
                if (strpos($addr, ':') !== false && preg_match('/^([0-9a-fA-F:]+)\/(\d+)$/', $addr, $m)) {
                    $config['v6_enabled'] = true;
                    $config['v6_mode'] = 'static';
                    $config['v6_address'] = $m[1];
                    $config['v6_netmask'] = $m[2];
                    $hasV6Static = true;
                }
            }
        }
        if (!$hasV6Static && !empty($eth['dhcp6'])) {
            $config['v6_enabled'] = true;
            $config['v6_mode'] = 'dhcp';
        } elseif (!$hasV6Static && !empty($eth['accept-ra'])) {
            $config['v6_enabled'] = true;
            $config['v6_mode'] = 'auto';
        }
        // Gateway v6
        if (!empty($eth['routes']) && is_array($eth['routes'])) {
            foreach ($eth['routes'] as $r) {
                if (($r['to'] ?? '') === 'default' && strpos($r['via'] ?? '', ':') !== false) {
                    $config['v6_gateway'] = $r['via'];
                }
            }
        }
        if (empty($config['v6_gateway']) && !empty($eth['gateway6'])) {
            $config['v6_gateway'] = $eth['gateway6'];
        }

        return $config;
    }

    /**
     * Parser textual mínimo do YAML do dashboard (último recurso quando
     * ext/yaml não está instalada). Suporta SOMENTE o formato que ESTE
     * NetworkManager gera — não é um parser genérico.
     */
    private function parseNetplanYamlFallback(string $path): array {
        $lines = @file($path) ?: [];
        $data = ['network' => ['ethernets' => []]];
        $currentIface = null;
        $currentKey = null; // addresses|routes
        foreach ($lines as $raw) {
            $indent = strlen($raw) - strlen(ltrim($raw, ' '));
            $line = trim($raw);
            if ($line === '' || $line[0] === '#') continue;
            // Detecta interface (indent 4, ex: "    eth0:")
            if ($indent === 4 && preg_match('/^([a-zA-Z0-9_.:-]+):$/', $line, $m)) {
                $currentIface = $m[1];
                $data['network']['ethernets'][$currentIface] = [];
                $currentKey = null;
                continue;
            }
            if ($currentIface === null) continue;
            // Detecta chave simples (indent 6, ex: "      dhcp4: true")
            if ($indent === 6 && preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $line, $m)) {
                $key = $m[1]; $val = trim($m[2]);
                $currentKey = $key;
                if ($val === '') {
                    $data['network']['ethernets'][$currentIface][$key] = [];
                } else {
                    if ($val === 'true') $val = true;
                    elseif ($val === 'false') $val = false;
                    $data['network']['ethernets'][$currentIface][$key] = $val;
                }
                continue;
            }
            // Item de lista (indent >= 8)
            if ($indent >= 8 && preg_match('/^-\s*(.*)$/', $line, $m) && $currentKey !== null) {
                $item = trim($m[1]);
                // Sub-objeto de route: "- to: default" → next-line "via: ..."
                if (preg_match('/^to:\s*(.*)$/', $item, $r)) {
                    $data['network']['ethernets'][$currentIface][$currentKey][] = ['to' => trim($r[1])];
                } else {
                    // Item escalar — strip quotes
                    $item = trim($item, '"\'');
                    $data['network']['ethernets'][$currentIface][$currentKey][] = $item;
                }
                continue;
            }
            // Chave de sub-objeto de route (indent 10, "via: 1.2.3.4")
            if ($indent >= 10 && preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $line, $m) && $currentKey === 'routes') {
                $list = $data['network']['ethernets'][$currentIface]['routes'] ?? [];
                $lastIdx = count($list) - 1;
                if ($lastIdx >= 0) {
                    $list[$lastIdx][$m[1]] = trim($m[2]);
                    $data['network']['ethernets'][$currentIface]['routes'] = $list;
                }
                continue;
            }
        }
        return $data;
    }

    /**
     * Gera o YAML e aplica via `netplan apply`. Faz backup do YAML
     * anterior em /var/backups/unbound-dashboard/netplan-99-<ts>.yaml
     * para permitir rollback manual via restoreLastNetplanBackup().
     *
     * CUIDADO: também derruba SSH em caso de mudança de IP/gateway.
     * Não há janela de auto-rollback — admin DEVE poder voltar via
     * console se perder conectividade.
     */
    private function applyNetplan(string $iface, string $mode, string $address, string $gateway, string $netmask, bool $v6_enabled, string $v6_mode, string $v6_address, string $v6_gateway, string $v6_netmask): array {
        // Carrega YAML atual (ou inicializa vazio)
        $data = [
            'network' => [
                'version' => 2,
                'renderer' => $this->detectNetplanRenderer(),
                'ethernets' => [],
            ],
        ];

        if (file_exists(self::NETPLAN_FILE)) {
            if (function_exists('yaml_parse_file')) {
                $existing = @yaml_parse_file(self::NETPLAN_FILE);
            } else {
                $existing = $this->parseNetplanYamlFallback(self::NETPLAN_FILE);
            }
            if (is_array($existing) && isset($existing['network'])) {
                $data['network'] = array_merge($data['network'], $existing['network']);
                if (!isset($data['network']['ethernets']) || !is_array($data['network']['ethernets'])) {
                    $data['network']['ethernets'] = [];
                }
            }
        }

        // Constrói o bloco da interface
        $eth = [];
        if ($mode === 'dhcp') {
            $eth['dhcp4'] = true;
        } else {
            $cidr = $netmask;
            // Se netmask veio como IP (255.255.255.0), converte pra CIDR
            if (filter_var($netmask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $cidr = self::netmaskToCidr($netmask);
            }
            if ($cidr === '' || !preg_match('/^\d+$/', (string) $cidr)) {
                $cidr = '24'; // fallback razoável
            }
            $addresses = ["$address/$cidr"];
            if ($v6_enabled && $v6_mode === 'static' && $v6_address !== '') {
                $v6cidr = $v6_netmask !== '' ? $v6_netmask : '64';
                $addresses[] = "$v6_address/$v6cidr";
            }
            $eth['addresses'] = $addresses;
            if (!empty($gateway) || ($v6_enabled && $v6_mode === 'static' && !empty($v6_gateway))) {
                $eth['routes'] = [];
                if (!empty($gateway)) {
                    $eth['routes'][] = ['to' => 'default', 'via' => $gateway];
                }
                if ($v6_enabled && $v6_mode === 'static' && !empty($v6_gateway)) {
                    $eth['routes'][] = ['to' => 'default', 'via' => $v6_gateway];
                }
            }
        }

        if ($v6_enabled) {
            if ($v6_mode === 'dhcp') {
                $eth['dhcp6'] = true;
            } elseif ($v6_mode === 'auto') {
                $eth['accept-ra'] = true;
            }
        } else {
            // Garante que IPv6 fica desligado se admin desmarcou
            $eth['accept-ra'] = false;
            $eth['dhcp6'] = false;
        }

        $data['network']['ethernets'][$iface] = $eth;

        // Serializa
        $yaml = self::dumpNetplanYaml($data);

        // Escreve em tmp dentro do data/tmp do projeto (matchea allowlist do sudoers)
        $tmpFile = dirname(__FILE__) . '/data/tmp/unbound-dashboard-netplan.yaml';
        @mkdir(dirname($tmpFile), 0775, true);
        if (@file_put_contents($tmpFile, $yaml) === false) {
            return ['success' => false, 'message' => "Erro ao escrever YAML temporário em $tmpFile"];
        }

        // Backup do YAML anterior (se existir) ANTES de sobrescrever
        if (file_exists(self::NETPLAN_FILE)) {
            $backupRes = $this->backupCurrentNetplan();
            if (!$backupRes['success']) {
                return ['success' => false, 'message' => 'Backup falhou (abortando apply): ' . $backupRes['message']];
            }
        }

        // Move o tmp pra /etc/netplan/99-unbound-dashboard.yaml
        $outMv = [];
        $retMv = 0;
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, self::NETPLAN_FILE], $outMv, $retMv, true);
        if ($retMv !== 0) {
            return ['success' => false, 'message' => 'Falha ao mover YAML para /etc/netplan/: ' . implode(' ', $outMv)];
        }

        // Aplica
        $outApply = [];
        $retApply = 0;
        \App\ShellHelper::exec('/usr/sbin/netplan', ['apply'], $outApply, $retApply, true);
        if ($retApply !== 0) {
            return [
                'success' => false,
                'message' => 'netplan apply falhou: ' . implode(' ', $outApply) . ' — use o botão "Reverter última mudança" se a conexão tiver caído.',
            ];
        }

        return [
            'success' => true,
            'message' => "Configuração de $iface aplicada via netplan. Backup do YAML anterior salvo em " . self::NETPLAN_BACKUP_DIR . " — caso a conexão caia, use o botão de rollback (precisa de console local).",
        ];
    }

    /**
     * Salva uma cópia datada do YAML atual em /var/backups/unbound-dashboard/.
     */
    private function backupCurrentNetplan(): array {
        @mkdir(self::NETPLAN_BACKUP_DIR, 0755, true);
        $ts = date('Ymd-His');
        $dest = self::NETPLAN_BACKUP_DIR . "/netplan-99-{$ts}.yaml";
        $out = []; $ret = 0;
        \App\ShellHelper::exec('/usr/bin/cp', [self::NETPLAN_FILE, $dest], $out, $ret, true);
        if ($ret !== 0) {
            return ['success' => false, 'message' => 'cp falhou: ' . implode(' ', $out)];
        }
        return ['success' => true, 'message' => "Backup em $dest", 'path' => $dest];
    }

    /**
     * Retorna o backup mais recente do YAML netplan (ou null).
     */
    public function getLastNetplanBackup(): ?string {
        $files = glob(self::NETPLAN_BACKUP_DIR . '/netplan-99-*.yaml') ?: [];
        if (empty($files)) return null;
        sort($files);
        return end($files);
    }

    /**
     * Restaura o backup mais recente e re-aplica. Permite o admin
     * desfazer a última mudança se ainda tiver acesso à UI.
     */
    public function restoreLastNetplanBackup(): array {
        if ($this->detectBackend() !== 'netplan') {
            return ['success' => false, 'message' => 'Backend ativo não é netplan — rollback indisponível.'];
        }
        return $this->_withCategoryLock('interfaces', function() {
            $last = $this->getLastNetplanBackup();
            if ($last === null) {
                return ['success' => false, 'message' => 'Nenhum backup encontrado em ' . self::NETPLAN_BACKUP_DIR];
            }
            $out = []; $ret = 0;
            \App\ShellHelper::exec('/usr/bin/cp', [$last, self::NETPLAN_FILE], $out, $ret, true);
            if ($ret !== 0) {
                return ['success' => false, 'message' => 'cp falhou: ' . implode(' ', $out)];
            }
            $outA = []; $retA = 0;
            \App\ShellHelper::exec('/usr/sbin/netplan', ['apply'], $outA, $retA, true);
            if ($retA !== 0) {
                return ['success' => false, 'message' => 'netplan apply pós-restore falhou: ' . implode(' ', $outA)];
            }
            return ['success' => true, 'message' => 'Rollback aplicado a partir de ' . basename($last)];
        });
    }

    /**
     * Converte máscara IPv4 (255.255.255.0) em CIDR (24).
     */
    private static function netmaskToCidr(string $mask): string {
        $long = ip2long($mask);
        if ($long === false) return '';
        $bin = decbin($long);
        return (string) substr_count($bin, '1');
    }

    /**
     * Serializa array PHP no formato YAML que o netplan espera.
     * Usa ext/yaml se disponível, senão um dumper textual mínimo
     * suficiente pro schema do dashboard.
     */
    private static function dumpNetplanYaml(array $data): string {
        if (function_exists('yaml_emit')) {
            return "# Gerado pelo Unbound Dashboard\n" . yaml_emit($data, YAML_UTF8_ENCODING);
        }
        // Dumper textual (suficiente pro nosso schema)
        $out = "# Gerado pelo Unbound Dashboard\n";
        $out .= "network:\n";
        $out .= "  version: " . ($data['network']['version'] ?? 2) . "\n";
        $out .= "  renderer: " . ($data['network']['renderer'] ?? 'networkd') . "\n";
        $out .= "  ethernets:\n";
        foreach ($data['network']['ethernets'] ?? [] as $name => $cfg) {
            $out .= "    $name:\n";
            foreach ($cfg as $k => $v) {
                if (is_bool($v)) {
                    $out .= "      $k: " . ($v ? 'true' : 'false') . "\n";
                } elseif (is_array($v) && $k === 'addresses') {
                    $out .= "      $k:\n";
                    foreach ($v as $a) {
                        $out .= "        - \"" . str_replace('"', '\"', (string)$a) . "\"\n";
                    }
                } elseif (is_array($v) && $k === 'routes') {
                    $out .= "      $k:\n";
                    foreach ($v as $r) {
                        $out .= "        - to: " . ($r['to'] ?? '') . "\n";
                        $out .= "          via: " . ($r['via'] ?? '') . "\n";
                    }
                } else {
                    $out .= "      $k: $v\n";
                }
            }
        }
        return $out;
    }

    public function getInterfaceConfig(string $ifaceName): array {
        // Despacha pra netplan se for o backend ativo
        if ($this->detectBackend() === 'netplan') {
            return $this->getInterfaceConfigNetplan($ifaceName);
        }

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
     * Atualiza a configuração de uma interface. Despacha pro backend
     * ativo (netplan ou ifupdown).
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
        return $this->_withCategoryLock('interfaces', function() use ($iface, $mode, $address, $gateway, $netmask, $v6_enabled, $v6_mode, $v6_address, $v6_gateway, $v6_netmask) {
            return $this->_doUpdateInterfaceConfig($iface, $mode, $address, $gateway, $netmask, $v6_enabled, $v6_mode, $v6_address, $v6_gateway, $v6_netmask);
        });
    }

    /**
     * Implementação interna do updateInterfaceConfig — chamada dentro do
     * lock. Mantém todo o código original do método público.
     */
    private function _doUpdateInterfaceConfig(string $iface, string $mode, string $address, string $gateway, string $netmask, bool $v6_enabled, string $v6_mode, string $v6_address, string $v6_gateway, string $v6_netmask): array {

        // Validações comuns aos dois backends
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

        // Despacho por backend
        if ($this->detectBackend() === 'netplan') {
            return $this->applyNetplan($iface, $mode, $address, $gateway, $netmask, $v6_enabled, $v6_mode, $v6_address, $v6_gateway, $v6_netmask);
        }

        // === ifupdown legacy abaixo ===
        if (!file_exists('/etc/network/interfaces')) {
            return ['success' => false, 'message' => 'Arquivo interfaces não encontrado.'];
        }

        // === ifupdown: edição do /etc/network/interfaces ===
        $ifacePattern = preg_quote($iface, '/');
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
     * Remove totalmente a config de uma interface do /etc/network/interfaces.
     * Útil pra apagar aliases tipo lo.1 sem precisar editar o arquivo na mão.
     *
     * Bloqueia a loopback raiz (`lo`) — sem o bloco dela o sistema quebra
     * pra tudo que depende de 127.0.0.1. Aliases (lo.1, lo:1) são OK.
     */
    public function removeInterfaceConfig(string $iface): array {
        $iface = trim($iface);
        if (!preg_match('/^[a-zA-Z0-9_.:-]+$/', $iface)) {
            return ['success' => false, 'message' => 'Nome de interface inválido.'];
        }
        if ($this->isLoopbackInterface($iface)) {
            return ['success' => false, 'message' => 'A loopback raiz (lo) não pode ser removida.'];
        }
        if ($this->detectBackend() === 'netplan') {
            return ['success' => false, 'message' => 'Remoção via netplan ainda não suportada — edite o YAML em /etc/netplan/ manualmente.'];
        }
        if (!file_exists('/etc/network/interfaces')) {
            return ['success' => false, 'message' => 'Arquivo /etc/network/interfaces não encontrado.'];
        }

        return $this->_withCategoryLock('interfaces', function() use ($iface) {
            return $this->_doRemoveInterfaceConfig($iface);
        });
    }

    private function _doRemoveInterfaceConfig(string $iface): array {
        $ifacePattern = preg_quote($iface, '/');
        $lines = file('/etc/network/interfaces');
        $newLines = [];
        $skip = false;
        $removed = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Pula linhas `auto <iface>` e `allow-hotplug <iface>` do alvo
            if (preg_match("/^auto\s+$ifacePattern$/", $trimmed) ||
                preg_match("/^allow-hotplug\s+$ifacePattern$/", $trimmed)) {
                $removed = true;
                continue;
            }

            // Início de bloco `iface <iface> inet[6]` do alvo
            if (preg_match("/^iface\s+$ifacePattern\s+inet/", $trimmed)) {
                $skip = true;
                $removed = true;
                continue;
            }

            // Próximo bloco encontra → para de pular
            if ($skip && (
                preg_match("/^iface\s+/", $trimmed) ||
                preg_match("/^auto\s+/", $trimmed) ||
                preg_match("/^allow-hotplug\s+/", $trimmed)
            )) {
                $skip = false;
            }

            if (!$skip) $newLines[] = $line;
        }

        if (!$removed) {
            return ['success' => false, 'message' => "Interface $iface não estava no /etc/network/interfaces."];
        }

        // Limpa linhas em branco no fim
        while (count($newLines) > 0 && trim(end($newLines)) === '') {
            array_pop($newLines);
        }
        $newLines[] = "\n";

        $newContent = implode("", $newLines);
        $tmpFile = dirname(__FILE__) . '/data/tmp/interfaces_new';
        file_put_contents($tmpFile, $newContent);
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, '/etc/network/interfaces'], $output, $returnVar, true);

        if ($returnVar !== 0) {
            return ['success' => false, 'message' => 'Erro ao salvar: ' . implode(" ", $output)];
        }

        // Best-effort: derruba a interface se estiver up. Falha silenciosa
        // (talvez nunca tenha sido instanciada).
        $dnOut = []; $dnRet = 0;
        \App\ShellHelper::exec('/usr/sbin/ifdown', [$iface], $dnOut, $dnRet, true);

        return ['success' => true, 'message' => "Interface $iface removida do /etc/network/interfaces."];
    }

    /**
     * Aplica as mudanças de rede reiniciando a interface.
     * Em netplan, o apply já aconteceu no updateInterfaceConfig() — esse
     * método é no-op nesse caso. Em ifupdown, dispara ifdown/ifup.
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

        // No netplan o apply é parte do update — não há nada a fazer aqui.
        if ($this->detectBackend() === 'netplan') {
            return ['success' => true, 'message' => "Interface $iface: netplan apply executado durante o save."];
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

    /**
     * Detecta o daemon NTP em uso, na ordem de preferência:
     * chrony (mais preciso) → ntpd (clássico) → systemd-timesyncd (default
     * em systemd modernos). Retorna 'chrony' | 'ntpd' | 'timesyncd' | 'none'.
     */
    public function detectNtpBackend(): string {
        static $cached = null;
        if ($cached !== null) return $cached;

        foreach (['chrony', 'chronyd'] as $svc) {
            $out = []; $ret = 0;
            \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', '--quiet', $svc], $out, $ret, false);
            if ($ret === 0) { $cached = 'chrony'; return $cached; }
        }
        foreach (['ntp', 'ntpd', 'ntpsec'] as $svc) {
            $out = []; $ret = 0;
            \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', '--quiet', $svc], $out, $ret, false);
            if ($ret === 0) { $cached = 'ntpd'; return $cached; }
        }
        $out = []; $ret = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', '--quiet', 'systemd-timesyncd'], $out, $ret, false);
        if ($ret === 0) { $cached = 'timesyncd'; return $cached; }

        $cached = 'none';
        return $cached;
    }

    public function getNtpServers(): string {
        switch ($this->detectNtpBackend()) {
            case 'chrony':    return $this->getNtpServersChrony();
            case 'ntpd':      return $this->getNtpServersNtpd();
            case 'timesyncd':
            default:          return $this->getNtpServersTimesyncd();
        }
    }

    private function getNtpServersTimesyncd(): string {
        if (file_exists('/etc/systemd/timesyncd.conf')) {
            foreach (file('/etc/systemd/timesyncd.conf') as $line) {
                if (preg_match('/^NTP=(.+)/', trim($line), $m)) return trim($m[1]);
            }
        }
        return '';
    }

    private function getNtpServersChrony(): string {
        // chrony pode ler de /etc/chrony/chrony.conf ou /etc/chrony.conf
        $paths = ['/etc/chrony/chrony.conf', '/etc/chrony.conf'];
        $servers = [];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                foreach (file($p) as $line) {
                    $line = trim($line);
                    // server <hostname/ip> [iburst] [...]  ou  pool <...>
                    if (preg_match('/^(?:server|pool)\s+(\S+)/', $line, $m)) {
                        $servers[] = $m[1];
                    }
                }
                break;
            }
        }
        return implode(' ', $servers);
    }

    private function getNtpServersNtpd(): string {
        $servers = [];
        if (file_exists('/etc/ntp.conf')) {
            foreach (file('/etc/ntp.conf') as $line) {
                $line = trim($line);
                if (preg_match('/^(?:server|pool)\s+(\S+)/', $line, $m)) {
                    $servers[] = $m[1];
                }
            }
        }
        return implode(' ', $servers);
    }

    public function setNtpServers($servers): array {
        $servers = $this->normalizeNtpServers($servers);
        return $this->_withCategoryLock('ntp', function() use ($servers) {
            switch ($this->detectNtpBackend()) {
                case 'chrony':    return $this->setNtpServersChrony($servers);
                case 'ntpd':      return $this->setNtpServersNtpd($servers);
                case 'timesyncd': return $this->setNtpServersTimesyncd($servers);
                case 'none':
                default:
                    return ['success' => false, 'message' => 'Nenhum daemon NTP ativo (chrony / ntpd / systemd-timesyncd). Instale e habilite um deles antes.'];
            }
        });
    }

    private function setNtpServersTimesyncd(string $servers): array {
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

        $tmpFile = dirname(__FILE__) . '/data/tmp/timesyncd.conf.new';
        @mkdir(dirname($tmpFile), 0775, true);
        file_put_contents($tmpFile, implode('', $newLines));
        $safeTmp = escapeshellarg($tmpFile);
        $safeFile = escapeshellarg($file);
        \App\ShellHelper::shell("sudo /usr/bin/mv $safeTmp $safeFile && sudo /usr/bin/systemctl restart systemd-timesyncd", $out, $ret);

        if ($ret === 0) return ['success' => true, 'message' => 'Servidores NTP (timesyncd) salvos e serviço reiniciado.'];
        return ['success' => false, 'message' => 'Erro ao salvar NTP timesyncd: ' . implode(' ', $out)];
    }

    /**
     * Edita /etc/chrony/chrony.conf (ou /etc/chrony.conf) substituindo todas
     * as linhas `server`/`pool` por entradas pros novos servidores. Mantém o
     * resto da conf intacta. Restart do daemon.
     */
    private function setNtpServersChrony(string $servers): array {
        $paths = ['/etc/chrony/chrony.conf', '/etc/chrony.conf'];
        $file = null;
        foreach ($paths as $p) { if (file_exists($p)) { $file = $p; break; } }
        if ($file === null) return ['success' => false, 'message' => 'chrony.conf não encontrado.'];

        $lines = file($file);
        $newLines = [];
        $foundServerBlock = false;
        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^(?:server|pool)\s+/', $trim)) {
                if (!$foundServerBlock) {
                    foreach (preg_split('/\s+/', trim($servers)) as $s) {
                        if ($s !== '') $newLines[] = "server $s iburst\n";
                    }
                    $foundServerBlock = true;
                }
                continue;
            }
            $newLines[] = $line;
        }
        if (!$foundServerBlock) {
            $newLines[] = "\n# Adicionado pelo Unbound Dashboard\n";
            foreach (preg_split('/\s+/', trim($servers)) as $s) {
                if ($s !== '') $newLines[] = "server $s iburst\n";
            }
        }

        $tmpFile = dirname(__FILE__) . '/data/tmp/chrony.conf.new';
        @mkdir(dirname($tmpFile), 0775, true);
        file_put_contents($tmpFile, implode('', $newLines));

        $out = []; $ret = 0;
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, $file], $out, $ret, true);
        if ($ret !== 0) return ['success' => false, 'message' => 'mv falhou: ' . implode(' ', $out)];

        // Detecta nome do serviço chrony (chrony em Debian, chronyd em Ubuntu/RHEL)
        $svc = 'chrony';
        $outA = []; $retA = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', '--quiet', 'chronyd'], $outA, $retA, false);
        if ($retA === 0) $svc = 'chronyd';

        $out2 = []; $ret2 = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', $svc], $out2, $ret2, true);
        if ($ret2 !== 0) return ['success' => false, 'message' => "restart $svc falhou: " . implode(' ', $out2)];

        return ['success' => true, 'message' => 'Servidores NTP (chrony) salvos e serviço reiniciado.'];
    }

    private function setNtpServersNtpd(string $servers): array {
        $file = '/etc/ntp.conf';
        if (!file_exists($file)) return ['success' => false, 'message' => 'ntp.conf não encontrado.'];

        $lines = file($file);
        $newLines = [];
        $foundBlock = false;
        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^(?:server|pool)\s+/', $trim)) {
                if (!$foundBlock) {
                    foreach (preg_split('/\s+/', trim($servers)) as $s) {
                        if ($s !== '') $newLines[] = "server $s iburst\n";
                    }
                    $foundBlock = true;
                }
                continue;
            }
            $newLines[] = $line;
        }
        if (!$foundBlock) {
            $newLines[] = "\n# Adicionado pelo Unbound Dashboard\n";
            foreach (preg_split('/\s+/', trim($servers)) as $s) {
                if ($s !== '') $newLines[] = "server $s iburst\n";
            }
        }

        $tmpFile = dirname(__FILE__) . '/data/tmp/ntp.conf.new';
        @mkdir(dirname($tmpFile), 0775, true);
        file_put_contents($tmpFile, implode('', $newLines));

        $out = []; $ret = 0;
        \App\ShellHelper::exec('/usr/bin/mv', [$tmpFile, $file], $out, $ret, true);
        if ($ret !== 0) return ['success' => false, 'message' => 'mv falhou: ' . implode(' ', $out)];

        // Detecta nome do serviço (ntp em Debian, ntpd em RHEL, ntpsec em Debian moderno)
        $svc = 'ntp';
        foreach (['ntpsec', 'ntpd', 'ntp'] as $cand) {
            $oA = []; $rA = 0;
            \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', '--quiet', $cand], $oA, $rA, false);
            if ($rA === 0) { $svc = $cand; break; }
        }

        $out2 = []; $ret2 = 0;
        \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', $svc], $out2, $ret2, true);
        if ($ret2 !== 0) return ['success' => false, 'message' => "restart $svc falhou: " . implode(' ', $out2)];

        return ['success' => true, 'message' => "Servidores NTP ($svc) salvos e serviço reiniciado."];
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
