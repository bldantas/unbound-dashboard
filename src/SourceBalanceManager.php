<?php

namespace App;

require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/ShellHelper.php';

class SourceBalanceManager {
    private string $unboundConfDir = '/etc/unbound';
    private string $nftablesDir = '/etc/nftables.d';
    private string $systemdDir = '/lib/systemd/system';

    public function getSettings(): array {
        $jwt = $_SESSION['api_jwt'] ?? '';
        $resp = ApiClient::get('/api/v1/exports/settings', $jwt);
        $results = [];
        if ($resp['ok'] && is_array($resp['data'])) {
            foreach ($resp['data'] as $row) {
                $key = $row['setting_key'] ?? '';
                if (str_starts_with($key, 'source_balance_')) {
                    $results[$key] = $row['setting_value'] ?? '';
                }
            }
        }

        return [
            'enabled' => ($results['source_balance_enabled'] ?? 'no') === 'yes',
            'instances' => (int)($results['source_balance_instances'] ?? 4),
            'anycast_ipv4' => $results['source_balance_anycast_ipv4'] ?? '4.2.2.5, 4.2.2.6',
            'anycast_ipv6' => $results['source_balance_anycast_ipv6'] ?? '2620:119:35::35, 2620:119:53::53'
        ];
    }

    private function sanitizeIpList(?string $ipList): string {
        if (empty($ipList)) {
            return '';
        }
        $ips = explode(',', $ipList);
        $sanitizedIps = [];
        foreach ($ips as $ip) {
            $trimmedIp = trim($ip);
            if (filter_var($trimmedIp, FILTER_VALIDATE_IP)) {
                $sanitizedIps[] = $trimmedIp;
            }
        }
        return implode(', ', $sanitizedIps);
    }

    public function saveSettings(array $settings): void {
        $entries = [
            ['setting_key' => 'source_balance_enabled',      'setting_value' => !empty($settings['enabled']) ? 'yes' : 'no'],
            ['setting_key' => 'source_balance_instances',    'setting_value' => (string)((int)($settings['instances'] ?? 4))],
            ['setting_key' => 'source_balance_anycast_ipv4', 'setting_value' => $this->sanitizeIpList($settings['anycast_ipv4'] ?? '')],
            ['setting_key' => 'source_balance_anycast_ipv6', 'setting_value' => $this->sanitizeIpList($settings['anycast_ipv6'] ?? '')],
        ];
        $jwt = $_SESSION['api_jwt'] ?? '';
        ApiClient::post('/api/v1/exports/settings/bulk', $jwt, $entries);
    }

    public function apply(): array {
        $settings = $this->getSettings();
        
        if ($settings['enabled']) {
            return $this->enableSourceBalance($settings);
        } else {
            return $this->disableSourceBalance();
        }
    }

    private function enableSourceBalance(array $settings): array {
        try {
            // 1. Sysctl
            $this->applySysctl();

            // 2. Stop default unbound
            \App\ShellHelper::exec('/usr/bin/systemctl', ['stop', 'unbound'], $tmpOutput, $tmpRet, true);
            \App\ShellHelper::exec('/usr/bin/systemctl', ['disable', 'unbound'], $tmpOutput, $tmpRet, true);

            // 3. Create instances
            $instances = $settings['instances'];
            $ipv4List = [];
            $ipv6List = [];

            for ($i = 1; $i <= $instances; $i++) {
                $id = str_pad($i, 2, '0', STR_PAD_LEFT);
                $ip4 = "100.127.255.1" . $id;
                $ip6 = "2001:db8:ffff:ffff:100:127:255:1" . $id;
                
                $ipv4List[] = "unbound$id;$ip4";
                $ipv6List[] = "unbound$id;$ip6";

                $this->createUnboundConfig($id, $ip4, $ip6);
                $this->createServiceUnit($id, $ip4, $ip6);
                
                \App\ShellHelper::exec('/usr/bin/systemctl', ['daemon-reload'], $tmpOutput, $tmpRet, true);
                \App\ShellHelper::exec('/usr/bin/systemctl', ['enable', "unbound{$id}"], $tmpOutput, $tmpRet, true);
                \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', "unbound{$id}"], $tmpOutput, $tmpRet, true);
            }

            // 4. Nftables
            $this->applyNftables($settings, $ipv4List, $ipv6List);

            return ['success' => true, 'message' => 'Source Balance ativado com sucesso!'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao ativar Source Balance: ' . $e->getMessage()];
        }
    }

    private function disableSourceBalance(): array {
        try {
            // 1. Stop and disable all instances
            $files = glob($this->unboundConfDir . "/unbound[0-9][0-9].conf");
            foreach ($files as $file) {
                if (preg_match('/unbound(\d+)\.conf/', $file, $m)) {
                    $id = $m[1];
                    \App\ShellHelper::exec('/usr/bin/systemctl', ['stop', "unbound{$id}"], $tmpOutput, $tmpRet, true);
                    \App\ShellHelper::exec('/usr/bin/systemctl', ['disable', "unbound{$id}"], $tmpOutput, $tmpRet, true);
                    \App\ShellHelper::exec('/usr/bin/rm', ['-f', "/lib/systemd/system/unbound{$id}.service"], $tmpOutput, $tmpRet, true);
                    \App\ShellHelper::exec('/usr/bin/rm', ['-f', $file], $tmpOutput, $tmpRet, true);
                }
            }
            \App\ShellHelper::exec('/usr/bin/systemctl', ['daemon-reload'], $tmpOutput, $tmpRet, true);

            // 2. Clear nftables and restore base config
            $cleanNft = "#!/usr/sbin/nft -f\nflush ruleset\ntable inet filter {\n    chain input { type filter hook input priority filter; policy accept; }\n    chain forward { type filter hook forward priority filter; policy accept; }\n    chain output { type filter hook output priority filter; policy accept; }\n}\n";
            $tmpFile = dirname(__FILE__) . '/data/tmp/nftables.conf';
            file_put_contents($tmpFile, $cleanNft);
            \App\ShellHelper::exec('/usr/bin/cp', [$tmpFile, '/etc/nftables.conf'], $tmpOutput, $tmpRet, true);
            \App\ShellHelper::exec('/usr/sbin/nft', ['-f', '/etc/nftables.conf'], $tmpOutput, $tmpRet, true);
            \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', 'nftables'], $tmpOutput, $tmpRet, true);

            // 3. Clean up interfaces.conf
            $intfFile = $this->unboundConfDir . "/includes/interfaces.conf";
            if (file_exists($intfFile)) {
                \App\ShellHelper::exec('/usr/bin/sed', ['-i', '/interface: 100.127.255./d', $intfFile], $tmpOutput, $tmpRet, true);
                \App\ShellHelper::exec('/usr/bin/sed', ['-i', '/interface: 2001:db8:ffff:ffff:100:127:255:/d', $intfFile], $tmpOutput, $tmpRet, true);
            }

            // 3.1. Remove interface dummy usada pelo Source Balance (se existir)
            \App\ShellHelper::exec('/usr/sbin/ip', ['link', 'del', 'unbound-sb'], $tmpOutput, $tmpRet, true);

            // 4. Start default unbound
            \App\ShellHelper::exec('/usr/bin/systemctl', ['enable', 'unbound'], $tmpOutput, $tmpRet, true);
            \App\ShellHelper::exec('/usr/bin/systemctl', ['start', 'unbound'], $tmpOutput, $tmpRet, true);

            return ['success' => true, 'message' => 'Source Balance desativado. Sistema restaurado para modo padrão.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao desativar Source Balance: ' . $e->getMessage()];
        }
    }

    private function applySysctl(): void {
        $content = "net.core.rmem_default=31457280
net.core.wmem_default=31457280
net.core.rmem_max=134217728
net.core.wmem_max=134217728
net.core.netdev_max_backlog=250000
net.core.optmem_max=33554432
net.core.default_qdisc=fq
net.core.somaxconn=4096
net.ipv4.ip_local_port_range=1024 65535
net.nf_conntrack_max=8000000
net.netfilter.nf_conntrack_buckets=262144
";
        $tmpFile = dirname(__FILE__) . '/data/tmp/99-unbound-performance.conf';
        file_put_contents($tmpFile, $content);
        \App\ShellHelper::exec('/usr/bin/cp', [$tmpFile, '/etc/sysctl.d/99-unbound-performance.conf'], $tmpOutput, $tmpRet, true);
        \App\ShellHelper::exec('/usr/sbin/sysctl', ['--system'], $tmpOutput, $tmpRet, true);
    }

    private function createUnboundConfig(string $id, string $ip4, string $ip6): void {
        $config = "
server:
    verbosity: 1
    statistics-interval: 20
    extended-statistics: yes
    num-threads: 1

    interface: $ip4
    interface: $ip6

    outgoing-range: 8192
    num-queries-per-thread: 4096

    msg-cache-size: 512m
    rrset-cache-size: 1024m

    msg-cache-slabs: 4
    rrset-cache-slabs: 4

    cache-max-ttl: 3600
    infra-host-ttl: 60
    
    do-ip4: yes
    do-ip6: yes
    do-udp: yes
    do-tcp: yes
    
    access-control: 0.0.0.0/0 allow
    access-control: ::/0 allow

    username: \"unbound\"
    directory: \"/etc/unbound\"
    chroot: \"\"
    pidfile: \"/var/run/unbound$id.pid\"
    
    # Include default includes (except conflicting ones)
    include: \"/etc/unbound/includes/performance.conf\"
    include: \"/etc/unbound/includes/optimization.conf\"
    include: \"/etc/unbound/includes/security.conf\"
    include: \"/etc/unbound/includes/general.conf\"
    include: \"/etc/unbound/includes/blocked_domains.conf\"
    include: \"/etc/unbound/includes/forwarders.conf\"

remote-control:
    control-enable: yes
    control-interface: 127.0.0.1
    control-port: 89" . $id . "
    control-use-cert: yes
    server-key-file: \"/etc/unbound/unbound_server.key\"
    server-cert-file: \"/etc/unbound/unbound_server.pem\"
    control-key-file: \"/etc/unbound/unbound_control.key\"
    control-cert-file: \"/etc/unbound/unbound_control.pem\"
";
        $tmpFile = dirname(__FILE__) . '/data/tmp/unbound' . $id . '.conf';
        file_put_contents($tmpFile, $config);
        \App\ShellHelper::exec('/usr/bin/cp', [$tmpFile, $this->unboundConfDir . "/unbound{$id}.conf"], $tmpOutput, $tmpRet, true);
    }

    private function createServiceUnit(string $id, string $ip4, string $ip6): void {
        $unit = "[Unit]
Description=Unbound DNS server instance $id
Documentation=man:unbound(8)
After=network.target
Before=nss-lookup.target
Wants=nss-lookup.target

[Service]
Type=notify
Restart=always
EnvironmentFile=-/etc/default/unbound
ExecStartPre=-/usr/sbin/ip link add unbound-sb type dummy
ExecStartPre=-/usr/sbin/ip link set unbound-sb up
ExecStartPre=-/usr/sbin/ip addr add $ip4/32 dev unbound-sb
ExecStartPre=-/usr/sbin/ip addr add $ip6/128 dev unbound-sb
ExecStartPre=-/usr/libexec/unbound-helper chroot_setup
ExecStartPre=-/usr/libexec/unbound-helper root_trust_anchor_update
ExecStart=/usr/sbin/unbound -d -c /etc/unbound/unbound$id.conf -p \$DAEMON_OPTS
ExecStopPost=-/usr/libexec/unbound-helper chroot_teardown
ExecStopPost=-/usr/sbin/ip addr del $ip4/32 dev unbound-sb
ExecStopPost=-/usr/sbin/ip addr del $ip6/128 dev unbound-sb
ExecReload=+/bin/kill -HUP \$MAINPID

[Install]
WantedBy=multi-user.target
";
        $tmpFile = dirname(__FILE__) . '/data/tmp/unbound' . $id . '.service';
        file_put_contents($tmpFile, $unit);
        \App\ShellHelper::exec('/usr/bin/cp', [$tmpFile, $this->systemdDir . "/unbound{$id}.service"], $tmpOutput, $tmpRet, true);
    }
    private function applyNftables(array $settings, array $v4Servers, array $v6Servers): void {
        \App\ShellHelper::exec('/usr/bin/mkdir', ['-p', $this->nftablesDir], $tmpOutput, $tmpRet, true);
        \App\ShellHelper::exec('/usr/bin/rm', ['-rf', $this->nftablesDir], $tmpOutput, $tmpRet, true);
        \App\ShellHelper::exec('/usr/bin/mkdir', ['-p', $this->nftablesDir], $tmpOutput, $tmpRet, true);
        
        $mainConf = "#!/usr/sbin/nft -f\nflush ruleset\ninclude \"$this->nftablesDir/rules.nft\"\n";
        $tmpFile = dirname(__FILE__) . '/data/tmp/nftables.conf';
        file_put_contents($tmpFile, $mainConf);
        \App\ShellHelper::exec('/usr/bin/cp', [$tmpFile, '/etc/nftables.conf'], $tmpOutput, $tmpRet, true);

        $nft = "table ip nat {\n";
        
        // IPv4 Sets
        foreach ($v4Servers as $reg) {
            list($procname, $procaddr) = explode(';', $reg);
            $nft .= "    set ipv4_users_{$procname} { type ipv4_addr; size 65535; flags dynamic,timeout; timeout 20m; }\n";
        }

        // IPv4 Helper Chains (Action chains)
        foreach ($v4Servers as $reg) {
            list($procname, $procaddr) = explode(';', $reg);
            foreach (['udp', 'tcp'] as $proto) {
                $chain = "ipv4_dns_{$proto}_{$procname}";
                $nft .= "    chain $chain {\n";
                $nft .= "        add @ipv4_users_{$procname} { ip saddr } counter\n";
                $nft .= "        $proto dport 53 counter dnat to $procaddr:53\n";
                $nft .= "    }\n";
            }
        }

        // IPv4 Entry Chains
        foreach (['udp', 'tcp'] as $proto) {
            $chain = "ipv4_{$proto}_dns";
            $nft .= "    chain $chain {\n";
            // Sticky logic
            foreach ($v4Servers as $reg) {
                list($procname, $procaddr) = explode(';', $reg);
                $nft .= "        ip saddr @ipv4_users_{$procname} counter jump ipv4_dns_{$proto}_{$procname}\n";
            }
            // LB logic
            $num = count($v4Servers);
            foreach ($v4Servers as $reg) {
                list($procname, $procaddr) = explode(';', $reg);
                $nft .= "        numgen inc mod $num 0 counter jump ipv4_dns_{$proto}_{$procname}\n";
                $num--;
            }
            $nft .= "    }\n";
        }

        // IPv4 PREROUTING
        $anyV4 = $settings['anycast_ipv4'];
        $nft .= "    chain PREROUTING {\n";
        $nft .= "        type nat hook prerouting priority dstnat; policy accept;\n";
        $nft .= "        ip daddr { $anyV4 } udp dport 53 counter jump ipv4_udp_dns\n";
        $nft .= "        ip daddr { $anyV4 } tcp dport 53 counter jump ipv4_tcp_dns\n";
        $nft .= "    }\n";
        $nft .= "}\n";

        // IPv6 Setup
        $nft .= "table ip6 nat {\n";
        foreach ($v6Servers as $reg) {
            list($procname, $procaddr) = explode(';', $reg);
            $nft .= "    set ipv6_users_{$procname} { type ipv6_addr; size 65535; flags dynamic,timeout; timeout 20m; }\n";
        }

        foreach ($v6Servers as $reg) {
            list($procname, $procaddr) = explode(';', $reg);
            foreach (['udp', 'tcp'] as $proto) {
                $chain = "ipv6_dns_{$proto}_{$procname}";
                $nft .= "    chain $chain {\n";
                $nft .= "        add @ipv6_users_{$procname} { ip6 saddr } counter\n";
                $nft .= "        $proto dport 53 counter dnat to $procaddr:53\n";
                $nft .= "    }\n";
            }
        }

        foreach (['udp', 'tcp'] as $proto) {
            $chain = "ipv6_{$proto}_dns";
            $nft .= "    chain $chain {\n";
            foreach ($v6Servers as $reg) {
                list($procname, $procaddr) = explode(';', $reg);
                $nft .= "        ip6 saddr @ipv6_users_{$procname} counter jump ipv6_dns_{$proto}_{$procname}\n";
            }
            $num = count($v6Servers);
            foreach ($v6Servers as $reg) {
                list($procname, $procaddr) = explode(';', $reg);
                $nft .= "        numgen inc mod $num 0 counter jump ipv6_dns_{$proto}_{$procname}\n";
                $num--;
            }
            $nft .= "    }\n";
        }

        $anyV6 = $settings['anycast_ipv6'];
        $nft .= "    chain PREROUTING {\n";
        $nft .= "        type nat hook prerouting priority dstnat; policy accept;\n";
        $nft .= "        ip6 daddr { $anyV6 } udp dport 53 counter jump ipv6_udp_dns\n";
        $nft .= "        ip6 daddr { $anyV6 } tcp dport 53 counter jump ipv6_tcp_dns\n";
        $nft .= "    }\n";
        $nft .= "}\n";

        $this->writeFile('rules.nft', $nft);

        \App\ShellHelper::exec('/usr/sbin/nft', ['-f', '/etc/nftables.conf'], $tmpOutput, $tmpRet, true);
        \App\ShellHelper::exec('/usr/bin/systemctl', ['enable', 'nftables'], $tmpOutput, $tmpRet, true);
        \App\ShellHelper::exec('/usr/bin/systemctl', ['restart', 'nftables'], $tmpOutput, $tmpRet, true);
    }


    private function writeFile(string $filename, string $content): void {
        $tmpDir = dirname(__FILE__) . '/data/tmp/';
        $tmp = $tmpDir . $filename;
        file_put_contents($tmp, $content);
        \App\ShellHelper::exec('/usr/bin/cp', [$tmp, $this->nftablesDir . '/' . $filename], $tmpOutput, $tmpRet, true);
    }
}
