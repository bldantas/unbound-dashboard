<?php

namespace App;

require_once __DIR__ . '/ShellHelper.php';

/**
 * Gerencia o par de certificados gerenciados pelo dashboard usados pra
 * habilitar DoT/DoH no Unbound.
 *
 * Salva os arquivos em `/etc/unbound/certs/` (criado pelo install.sh ou
 * via sudo mkdir). Os paths são fixos: `dashboard.crt` + `dashboard.key`,
 * de modo que conseguimos detectar "está sendo gerenciado por mim?"
 * sem precisar de marcador adicional.
 *
 * Os campos texto livres "Caminho do Certificado" / "Caminho da Chave"
 * na UI continuam apontando pra esses paths (auto-preenchidos quando
 * o user gera ou faz upload), mas o usuário pode mudar pra um path
 * externo (ex: /etc/letsencrypt/live/...) se preferir. Nesse caso
 * removeCert() não toca em nada de externo.
 */
class TlsCertManager
{
    const MANAGED_DIR = '/etc/unbound/certs';
    const MANAGED_CRT = '/etc/unbound/certs/dashboard.crt';
    const MANAGED_KEY = '/etc/unbound/certs/dashboard.key';

    // Arquivos temporários em src/data/tmp/ — sudoers permite install daqui.
    private string $tmpCrt;
    private string $tmpKey;

    public function __construct()
    {
        $tmpDir = __DIR__ . '/data/tmp/';
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
        $this->tmpCrt = $tmpDir . 'unbound_dashboard_cert.crt';
        $this->tmpKey = $tmpDir . 'unbound_dashboard_cert.key';
    }

    /**
     * Retorna {present, managed_by_dashboard, expires_at, subject, sans}.
     * - present: ambos os arquivos managed existem.
     * - managed_by_dashboard: ambos existem nos paths managed.
     * - expires_at: timestamp Unix do `notAfter`, ou null se erro.
     * - subject: CN ou primeira parte do subject.
     * - sans: array de SANs (DNS:..., IP:...).
     */
    public function getStatus(): array
    {
        $crtExists = is_file(self::MANAGED_CRT) && is_readable(self::MANAGED_CRT);
        $keyExists = is_file(self::MANAGED_KEY);
        $managed = $crtExists && $keyExists;

        $expiresAt = null;
        $subject = null;
        $issuer = null;
        $isLetsEncrypt = false;
        $sans = [];

        if ($crtExists) {
            $info = $this->_readCertInfo(self::MANAGED_CRT);
            $expiresAt = $info['expires_at'] ?? null;
            $subject = $info['subject'] ?? null;
            $issuer = $info['issuer'] ?? null;
            $isLetsEncrypt = $info['is_letsencrypt'] ?? false;
            $sans = $info['sans'] ?? [];
        }

        return [
            'present'              => $crtExists,
            'managed_by_dashboard' => $managed,
            'expires_at'           => $expiresAt,
            'subject'              => $subject,
            'issuer'               => $issuer,
            'is_letsencrypt'       => $isLetsEncrypt,
            'sans'                 => $sans,
            'crt_path'             => self::MANAGED_CRT,
            'key_path'             => self::MANAGED_KEY,
        ];
    }

    /**
     * Gera um par self-signed via openssl. CN obrigatório; SAN opcional
     * (array de strings tipo "dns1.example.com", IPs, etc).
     * Validade default 825 dias (limite aceito por iOS/Safari).
     */
    public function generateSelfSigned(string $cn, array $sans = [], int $days = 825): array
    {
        $cn = trim($cn);
        if ($cn === '' || strlen($cn) > 100) {
            return ['success' => false, 'message' => 'CN (Common Name) inválido.'];
        }
        if ($days < 1 || $days > 3650) {
            return ['success' => false, 'message' => 'Validade fora do intervalo permitido (1–3650 dias).'];
        }

        // Normaliza SANs — aceita "dns1.com", "192.168.1.1", " 2001:db8::1 "
        $sanLines = [];
        $i = 1;
        $dnsList = [$cn];  // CN entra como primeiro SAN DNS (compat moderna)
        foreach ($sans as $s) {
            $s = trim((string) $s);
            if ($s === '') continue;
            $dnsList[] = $s;
        }
        $dnsList = array_unique($dnsList);

        foreach ($dnsList as $s) {
            // SANs aceitam IPs literais, não CIDR — strip /N se vier (caso de uso
            // comum: usuário copia /etc/network/interfaces ou listas com máscara).
            if (str_contains($s, '/')) {
                $s = explode('/', $s, 2)[0];
            }
            if (filter_var($s, FILTER_VALIDATE_IP)) {
                $sanLines[] = 'IP.' . $i++ . ' = ' . $s;
            } elseif (preg_match('/^[a-zA-Z0-9._-]+$/', $s) && strlen($s) <= 253) {
                $sanLines[] = 'DNS.' . $i++ . ' = ' . $s;
            }
        }
        if (empty($sanLines)) {
            return ['success' => false, 'message' => 'Nenhum SAN/CN válido — use hostname FQDN ou IP.'];
        }

        // Config OpenSSL inline (extensions v3_req)
        $cnSafe = preg_replace('/[^a-zA-Z0-9._-]/', '', $cn) ?: 'dashboard';
        $opensslCnf = "[req]\nprompt = no\ndistinguished_name = dn\nreq_extensions = v3_req\n"
                    . "[dn]\nCN = {$cn}\n"
                    . "[v3_req]\nbasicConstraints = critical, CA:FALSE\n"
                    . "keyUsage = critical, digitalSignature, keyEncipherment\n"
                    . "extendedKeyUsage = serverAuth\n"
                    . "subjectAltName = @san\n"
                    . "[san]\n" . implode("\n", $sanLines) . "\n";

        $cnfFile = __DIR__ . '/data/tmp/unbound_cert.cnf';
        file_put_contents($cnfFile, $opensslCnf);

        // Gera key + self-signed cert direto (openssl req -x509)
        $out = []; $ret = 0;
        ShellHelper::exec(
            '/usr/bin/openssl',
            [
                'req', '-x509', '-nodes', '-newkey', 'rsa:2048',
                '-keyout', $this->tmpKey,
                '-out',    $this->tmpCrt,
                '-days',   (string) $days,
                '-config', $cnfFile,
                '-extensions', 'v3_req',
            ],
            $out, $ret, false
        );
        @unlink($cnfFile);

        if ($ret !== 0) {
            return ['success' => false, 'message' => "openssl falhou: " . implode("\n", $out)];
        }

        return $this->_installFromTmp("Certificado self-signed gerado (CN={$cn}, {$days} dias).");
    }

    /**
     * Recebe cert e key em formato PEM (via paste ou upload) e instala.
     * Valida que cert+key combinam (modulus match).
     */
    public function uploadCert(string $certPem, string $keyPem): array
    {
        $certPem = trim($certPem);
        $keyPem = trim($keyPem);
        if (!str_starts_with($certPem, '-----BEGIN CERTIFICATE-----')) {
            return ['success' => false, 'message' => 'Certificado PEM inválido (precisa começar com "-----BEGIN CERTIFICATE-----").'];
        }
        if (!preg_match('/^-----BEGIN (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----/', $keyPem)) {
            return ['success' => false, 'message' => 'Chave privada PEM inválida.'];
        }

        file_put_contents($this->tmpCrt, $certPem . "\n");
        file_put_contents($this->tmpKey, $keyPem . "\n");

        // Valida cert
        $out = []; $ret = 0;
        ShellHelper::exec('/usr/bin/openssl', ['x509', '-in', $this->tmpCrt, '-noout'], $out, $ret, false);
        if ($ret !== 0) {
            @unlink($this->tmpCrt);
            @unlink($this->tmpKey);
            return ['success' => false, 'message' => "Cert inválido: " . implode(" ", $out)];
        }

        // Valida key
        $out2 = []; $ret2 = 0;
        ShellHelper::exec('/usr/bin/openssl', ['pkey', '-in', $this->tmpKey, '-noout'], $out2, $ret2, false);
        if ($ret2 !== 0) {
            @unlink($this->tmpCrt);
            @unlink($this->tmpKey);
            return ['success' => false, 'message' => "Key inválida: " . implode(" ", $out2)];
        }

        // Valida match: modulus do cert e da key precisam coincidir (RSA)
        // Pra EC keys é mais complexo; pular validação cross se openssl pkey
        // não der modulus.
        $mc = []; $mk = [];
        ShellHelper::exec('/usr/bin/openssl', ['x509', '-in', $this->tmpCrt, '-noout', '-modulus'], $mc, $r, false);
        ShellHelper::exec('/usr/bin/openssl', ['rsa', '-in', $this->tmpKey, '-noout', '-modulus'], $mk, $r2, false);
        if (!empty($mc) && !empty($mk) && trim(implode('', $mc)) !== trim(implode('', $mk))) {
            @unlink($this->tmpCrt);
            @unlink($this->tmpKey);
            return ['success' => false, 'message' => 'Certificado e chave NÃO combinam (modulus diferentes).'];
        }

        return $this->_installFromTmp("Certificado enviado e instalado.");
    }

    /**
     * Remove os arquivos managed (não toca em paths externos tipo /etc/letsencrypt).
     */
    public function removeCert(): array
    {
        $rmCrt = []; $rmKey = []; $r1 = 0; $r2 = 0;
        if (is_file(self::MANAGED_CRT)) {
            ShellHelper::exec('/usr/bin/rm', [self::MANAGED_CRT], $rmCrt, $r1, true);
        }
        if (is_file(self::MANAGED_KEY)) {
            ShellHelper::exec('/usr/bin/rm', [self::MANAGED_KEY], $rmKey, $r2, true);
        }
        if ($r1 !== 0 || $r2 !== 0) {
            return ['success' => false, 'message' => 'Falha ao remover: ' . implode(' ', array_merge($rmCrt, $rmKey))];
        }
        return ['success' => true, 'message' => 'Certificado gerenciado removido. Lembre-se de apagar os caminhos em Configurações.'];
    }

    // ------------------------------------------------------------------

    private function _installFromTmp(string $okMessage): array
    {
        // Garante dir
        $mkOut = []; $mkRet = 0;
        ShellHelper::exec('/usr/bin/mkdir', ['-p', self::MANAGED_DIR], $mkOut, $mkRet, true);
        if ($mkRet !== 0) {
            return ['success' => false, 'message' => 'Falha ao criar /etc/unbound/certs: ' . implode(" ", $mkOut)];
        }

        // Install (cp + chown + chmod numa só) — sudoers tem entries exatas
        $iOut = []; $iRet = 0;
        ShellHelper::exec(
            '/usr/bin/install',
            ['-o', 'unbound', '-g', 'unbound', '-m', '0644', $this->tmpCrt, self::MANAGED_CRT],
            $iOut, $iRet, true
        );
        if ($iRet !== 0) {
            return ['success' => false, 'message' => 'Falha install cert: ' . implode(" ", $iOut)];
        }

        $iOut2 = []; $iRet2 = 0;
        ShellHelper::exec(
            '/usr/bin/install',
            ['-o', 'unbound', '-g', 'unbound', '-m', '0640', $this->tmpKey, self::MANAGED_KEY],
            $iOut2, $iRet2, true
        );
        if ($iRet2 !== 0) {
            return ['success' => false, 'message' => 'Falha install key: ' . implode(" ", $iOut2)];
        }

        @unlink($this->tmpCrt);
        @unlink($this->tmpKey);
        return ['success' => true, 'message' => $okMessage, 'crt_path' => self::MANAGED_CRT, 'key_path' => self::MANAGED_KEY];
    }

    /**
     * Snapshot do estado real do servidor DoT/DoH.
     *
     * `$testIps` = lista de IPs onde tentar o handshake. Se vazia, defaults
     * pra 127.0.0.1 (raramente útil, porque o Unbound só gera
     * `interface:<ip>@porta` pra non-loopback). Passe os IPs reais lidos
     * de `ip addr` ou da config Unbound.
     */
    public function getServiceStatus(
        int $dotPort = 853,
        int $dohPort = 443,
        string $certPath = '',
        string $keyPath = '',
        array $testIps = []
    ): array
    {
        $out = [
            'dot_port'             => $dotPort,
            'doh_port'             => $dohPort,
            'dot_listening'        => false,
            'doh_listening'        => false,
            'dot_handshake_ok'     => false,
            'doh_handshake_ok'     => false,
            'cert_path'            => $certPath,
            'key_path'             => $keyPath,
            'cert_present'         => false,
            'cert_subject'         => null,
            'cert_expires_at'      => null,
            'cert_days_remaining'  => null,
            'cert_sans'            => [],
            'warnings'             => [],
        ];

        $listening = $this->_listeningTcpPorts();
        if ($dotPort > 0) $out['dot_listening'] = in_array($dotPort, $listening, true);
        if ($dohPort > 0) $out['doh_listening'] = in_array($dohPort, $listening, true);

        // Certificado configurado (path do form, não necessariamente o managed).
        // Usamos file_exists em vez de is_readable porque o key normalmente
        // tem perms 0640 (só owner+grupo unbound), e o PHP-FPM (www-data) não
        // está no grupo unbound — daí is_readable retornava false mesmo o
        // arquivo existindo e o Unbound conseguindo ler. file_exists só checa
        // se existe, sem precisar permissão de leitura.
        if ($certPath !== '' && file_exists($certPath)) {
            // Cert é world-readable por design (perms 0644), então o PHP pode
            // abrir openssl pra extrair subject/expires/SANs.
            $info = is_readable($certPath) ? $this->_readCertInfo($certPath) : [];
            $out['cert_present']    = true;
            $out['cert_subject']    = $info['subject'] ?? null;
            $out['cert_expires_at'] = $info['expires_at'] ?? null;
            $out['cert_sans']       = $info['sans'] ?? [];
            if ($out['cert_expires_at']) {
                $out['cert_days_remaining'] = (int) floor(($out['cert_expires_at'] - time()) / 86400);
                if ($out['cert_days_remaining'] < 0) {
                    $out['warnings'][] = 'Certificado expirado em ' . date('d/m/Y', $out['cert_expires_at']);
                } elseif ($out['cert_days_remaining'] < 30) {
                    $out['warnings'][] = 'Certificado expira em ' . $out['cert_days_remaining'] . ' dias';
                }
            }
        } elseif ($certPath !== '') {
            $out['warnings'][] = 'Caminho do certificado configurado mas arquivo não encontrado: ' . $certPath;
        }

        if ($keyPath !== '' && !file_exists($keyPath)) {
            $out['warnings'][] = 'Caminho da chave configurado mas arquivo não encontrado: ' . $keyPath;
        }

        // Handshake TLS — tenta em cada IP da lista até um funcionar. Unbound
        // só listen nos IPs que estão como `interface: X@porta`, então 127.0.0.1
        // sozinho geralmente falha. Caller passa os IPs detectados.
        $ipsToTry = !empty($testIps) ? $testIps : ['127.0.0.1'];
        $out['handshake_tested_ip'] = null;

        if ($out['dot_listening']) {
            foreach ($ipsToTry as $ip) {
                if ($this->_testTlsHandshake($ip, $dotPort, 'dot')) {
                    $out['dot_handshake_ok'] = true;
                    $out['handshake_tested_ip'] = $ip;
                    break;
                }
            }
            if (!$out['dot_handshake_ok']) {
                $out['warnings'][] = 'Porta DoT (' . $dotPort . ') está aberta mas handshake TLS falhou em ' . implode(', ', $ipsToTry) . ' — cert/key incorretos ou unbound não recarregou.';
            }
        }
        if ($out['doh_listening']) {
            foreach ($ipsToTry as $ip) {
                if ($this->_testTlsHandshake($ip, $dohPort, 'doh')) {
                    $out['doh_handshake_ok'] = true;
                    if (!$out['handshake_tested_ip']) $out['handshake_tested_ip'] = $ip;
                    break;
                }
            }
            if (!$out['doh_handshake_ok']) {
                $out['warnings'][] = 'Porta DoH (' . $dohPort . ') está aberta mas handshake TLS falhou em ' . implode(', ', $ipsToTry) . ' — cert/key incorretos ou unbound não recarregou.';
            }
        }

        return $out;
    }

    /**
     * Retorna a lista de portas TCP em LISTEN (qualquer interface).
     * Usa `ss -ltn` (não-sudo, lê dump de sockets do kernel).
     */
    private function _listeningTcpPorts(): array
    {
        $out = []; $ret = 0;
        ShellHelper::exec('/usr/bin/ss', ['-ltn'], $out, $ret, false);
        if ($ret !== 0) return [];
        $ports = [];
        foreach ($out as $line) {
            // Formato típico: "LISTEN 0      4096   127.0.0.1:53   0.0.0.0:*"
            // ou IPv6:        "LISTEN 0      4096   [::]:853       [::]:*"
            if (preg_match('/[:.](\d+)\s+[\d\.\*\[\]:]+\s*$/', $line, $m)
                || preg_match('/[:.](\d+)\s/', $line, $m)) {
                $ports[] = (int) $m[1];
            }
        }
        return array_values(array_unique($ports));
    }

    /**
     * Conecta em $host:$port via TLS (ignora cert verification — só queremos
     * saber se o handshake completa). Timeout 3s.
     */
    private function _testTlsHandshake(string $host, int $port, string $proto = 'dot'): bool
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client(
            "tls://{$host}:{$port}",
            $errno, $errstr,
            3.0,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!$sock) return false;
        @fclose($sock);
        return true;
    }

    private function _readCertInfo(string $path): array
    {
        $out = []; $ret = 0;
        ShellHelper::exec(
            '/usr/bin/openssl',
            ['x509', '-in', $path, '-noout', '-enddate', '-subject', '-issuer', '-ext', 'subjectAltName'],
            $out, $ret, false
        );
        if ($ret !== 0) return [];

        $info = ['expires_at' => null, 'subject' => null, 'issuer' => null, 'sans' => [], 'is_letsencrypt' => false];
        foreach ($out as $line) {
            if (str_starts_with($line, 'notAfter=')) {
                $info['expires_at'] = strtotime(substr($line, 9)) ?: null;
            } elseif (str_starts_with($line, 'subject=')) {
                if (preg_match('/CN\s*=\s*([^,\/]+)/', $line, $m)) {
                    $info['subject'] = trim($m[1]);
                } else {
                    $info['subject'] = trim(substr($line, 8));
                }
            } elseif (str_starts_with($line, 'issuer=')) {
                $info['issuer'] = trim(substr($line, 7));
                // Detecta Let's Encrypt (CN=R3, R10, R11, R12, R13… ou contém "Let's Encrypt")
                if (stripos($info['issuer'], "let's encrypt") !== false
                    || stripos($info['issuer'], 'letsencrypt') !== false) {
                    $info['is_letsencrypt'] = true;
                }
            } elseif (preg_match('/^\s*(DNS|IP Address):/', $line)) {
                foreach (preg_split('/,\s*/', trim($line)) as $token) {
                    $info['sans'][] = trim($token);
                }
            }
        }
        return $info;
    }
}
