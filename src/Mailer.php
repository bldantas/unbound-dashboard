<?php

namespace App;

require_once __DIR__ . '/ApiClient.php';

/**
 * Cliente SMTP minimalista em PHP puro (sem dependências composer).
 *
 * Suporta:
 *   - Conexão simples (porta 25, sem encriptação)
 *   - STARTTLS (porta 587, comum em Gmail/Outlook/SES)
 *   - SMTPS (porta 465, conexão TLS desde o início)
 *   - Autenticação AUTH LOGIN (compatível com qualquer SMTP padrão)
 *
 * Config persistida em settings (DuckDB) via /api/v1/exports/settings/bulk.
 * Mascara a senha na UI mas guarda plaintext no DB (escopo interno).
 *
 * Fallback: se SMTP não configurado, cai pra mail() do PHP.
 */
class Mailer
{
    private const SETTINGS = [
        'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
        'smtp_user', 'smtp_password', 'smtp_from', 'smtp_from_name',
    ];

    private array $config = [];
    private array $log = [];

    public function __construct()
    {
        $this->config = self::loadConfig();
    }

    /**
     * Carrega settings de SMTP do DuckDB via /api/v1/exports/settings.
     * Retorna array com defaults seguros se não estiver configurado.
     */
    public static function loadConfig(): array
    {
        $defaults = [
            'smtp_enabled'    => false,
            'smtp_host'       => '',
            'smtp_port'       => 587,
            'smtp_encryption' => 'tls',  // none | tls | ssl
            'smtp_user'       => '',
            'smtp_password'   => '',
            'smtp_from'       => '',
            'smtp_from_name'  => 'Unbound Dashboard',
        ];

        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return $defaults;
        $resp = ApiClient::get('/api/v1/exports/settings', $jwt);
        if (!($resp['ok'] ?? false) || !is_array($resp['data'])) return $defaults;

        $config = $defaults;
        foreach ($resp['data'] as $row) {
            $key = $row['setting_key'] ?? '';
            if (in_array($key, self::SETTINGS, true)) {
                $val = $row['setting_value'] ?? '';
                if ($key === 'smtp_enabled') {
                    $config[$key] = in_array(strtolower(trim((string)$val)), ['1', 'true', 'yes', 'on'], true);
                } elseif ($key === 'smtp_port') {
                    $config[$key] = max(1, min(65535, (int)$val ?: 587));
                } else {
                    $config[$key] = (string)$val;
                }
            }
        }
        return $config;
    }

    /**
     * Persiste config via /api/v1/exports/settings/bulk (UPSERT).
     */
    public static function saveConfig(array $entries): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];

        $payload = [];
        foreach ($entries as $key => $value) {
            if (!in_array($key, self::SETTINGS, true)) continue;
            $payload[] = ['setting_key' => $key, 'setting_value' => (string)$value];
        }
        if (empty($payload)) return ['success' => false, 'message' => 'Nenhuma configuração válida.'];

        $resp = ApiClient::post('/api/v1/exports/settings/bulk', $jwt, $payload);
        if ($resp['ok'] ?? false) {
            return ['success' => true, 'message' => 'Configurações SMTP salvas.'];
        }
        return ['success' => false, 'message' => 'Falha ao salvar (' . ($resp['reason'] ?? '?') . ').'];
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['smtp_enabled'])
            && !empty($this->config['smtp_host'])
            && !empty($this->config['smtp_from']);
    }

    public function getLog(): array
    {
        return $this->log;
    }

    public function getFromName(): string
    {
        return $this->config['smtp_from_name'] ?: 'Unbound Dashboard';
    }

    /**
     * Envia email. Retorna ['success' => bool, 'message' => string].
     *
     * Se SMTP estiver configurado, usa-o. Senão, fallback pra mail().
     */
    public function send(string $to, string $subject, string $body, array $extraHeaders = []): array
    {
        if ($this->isConfigured()) {
            return $this->sendViaSmtp($to, $subject, $body, $extraHeaders);
        }
        return $this->sendViaPhpMail($to, $subject, $body, $extraHeaders);
    }

    private function sendViaPhpMail(string $to, string $subject, string $body, array $extraHeaders): array
    {
        $from = $this->config['smtp_from'] ?: ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $name = $this->getFromName();
        $headers = "From: $name <$from>\r\nReply-To: $from\r\nX-Mailer: PHP/" . phpversion();
        foreach ($extraHeaders as $h) $headers .= "\r\n" . $h;
        $ok = @mail($to, $subject, $body, $headers);
        $this->log[] = $ok ? "[php-mail] enviado pra $to" : "[php-mail] FALHA enviando pra $to (MTA local indisponível?)";
        return [
            'success' => (bool)$ok,
            'message' => $ok ? 'Enviado via mail() PHP.' : 'mail() PHP falhou — configure SMTP pra entrega confiável.',
        ];
    }

    /**
     * Implementação SMTP direta via fsockopen. Suporta STARTTLS e SMTPS.
     */
    private function sendViaSmtp(string $to, string $subject, string $body, array $extraHeaders): array
    {
        $host = $this->config['smtp_host'];
        $port = (int) $this->config['smtp_port'];
        $enc  = strtolower($this->config['smtp_encryption']);
        $user = $this->config['smtp_user'];
        $pass = $this->config['smtp_password'];
        $from = $this->config['smtp_from'];
        $name = $this->getFromName();

        // SMTPS (porta 465): conexão TLS desde o socket
        $socketPrefix = ($enc === 'ssl') ? 'tls://' : '';
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client(
            "{$socketPrefix}{$host}:{$port}",
            $errno, $errstr,
            10,
            STREAM_CLIENT_CONNECT
        );
        if (!$fp) {
            $this->log[] = "[smtp] FALHA conectar em {$host}:{$port} — $errstr ($errno)";
            return ['success' => false, 'message' => "Não foi possível conectar em {$host}:{$port}: $errstr"];
        }
        stream_set_timeout($fp, 10);

        $expect = function (int $code) use ($fp): array {
            $line = '';
            while (($l = fgets($fp, 1024)) !== false) {
                $line .= $l;
                if (preg_match('/^\d{3} /', $l)) break;
            }
            $actualCode = (int) substr(trim($line), 0, 3);
            return [
                'ok' => $actualCode === $code,
                'code' => $actualCode,
                'line' => trim($line),
            ];
        };

        $cmd = function (string $command, bool $hide = false) use ($fp): void {
            fwrite($fp, $command . "\r\n");
            $this->log[] = "[smtp] >> " . ($hide ? '***' : $command);
        };

        try {
            // Greeting
            $r = $expect(220);
            $this->log[] = "[smtp] << " . $r['line'];
            if (!$r['ok']) throw new \RuntimeException("Greeting inesperado: " . $r['line']);

            // EHLO
            $localHost = $_SERVER['HTTP_HOST'] ?? gethostname() ?: 'localhost';
            $cmd("EHLO {$localHost}");
            $r = $expect(250);
            $this->log[] = "[smtp] << " . $r['line'];
            if (!$r['ok']) throw new \RuntimeException("EHLO falhou: " . $r['line']);

            // STARTTLS (porta 587 comum)
            if ($enc === 'tls') {
                $cmd("STARTTLS");
                $r = $expect(220);
                $this->log[] = "[smtp] << " . $r['line'];
                if (!$r['ok']) throw new \RuntimeException("STARTTLS falhou: " . $r['line']);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException("Negociação TLS falhou.");
                }
                $this->log[] = "[smtp] -- crypto enabled (TLS)";
                // Re-EHLO após STARTTLS
                $cmd("EHLO {$localHost}");
                $r = $expect(250);
                $this->log[] = "[smtp] << " . $r['line'];
            }

            // AUTH LOGIN se user/pass setados
            if ($user !== '' && $pass !== '') {
                $cmd("AUTH LOGIN");
                $r = $expect(334);
                $this->log[] = "[smtp] << " . $r['line'];
                if (!$r['ok']) throw new \RuntimeException("AUTH LOGIN falhou: " . $r['line']);

                $cmd(base64_encode($user));
                $r = $expect(334);
                $this->log[] = "[smtp] << " . $r['line'];
                if (!$r['ok']) throw new \RuntimeException("Username inválido: " . $r['line']);

                $cmd(base64_encode($pass), true);
                $r = $expect(235);
                $this->log[] = "[smtp] << " . $r['line'];
                if (!$r['ok']) throw new \RuntimeException("Senha inválida: " . $r['line']);
            }

            // MAIL FROM
            $cmd("MAIL FROM:<{$from}>");
            $r = $expect(250);
            $this->log[] = "[smtp] << " . $r['line'];
            if (!$r['ok']) throw new \RuntimeException("MAIL FROM rejeitado: " . $r['line']);

            // RCPT TO
            $cmd("RCPT TO:<{$to}>");
            $r = $expect(250);
            $this->log[] = "[smtp] << " . $r['line'];
            if (!$r['ok']) throw new \RuntimeException("RCPT TO rejeitado: " . $r['line']);

            // DATA
            $cmd("DATA");
            $r = $expect(354);
            $this->log[] = "[smtp] << " . $r['line'];
            if (!$r['ok']) throw new \RuntimeException("DATA rejeitado: " . $r['line']);

            // Headers + body
            $headers = "From: {$name} <{$from}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: " . self::encodeHeader($subject) . "\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";
            $headers .= "X-Mailer: UnboundDashboard-Mailer/1.0\r\n";
            foreach ($extraHeaders as $h) $headers .= $h . "\r\n";

            // Dot-stuffing: linhas começando com "." viram ".."
            $dotStuffed = preg_replace('/(^|\r\n)\./', '$1..', $body);
            $payload = $headers . "\r\n" . $dotStuffed . "\r\n.";
            fwrite($fp, $payload . "\r\n");
            $r = $expect(250);
            $this->log[] = "[smtp] << " . $r['line'];
            if (!$r['ok']) throw new \RuntimeException("Envio rejeitado: " . $r['line']);

            // QUIT
            $cmd("QUIT");
            @fclose($fp);
            return ['success' => true, 'message' => "Email enviado pra {$to} via {$host}:{$port}."];
        } catch (\Throwable $e) {
            @fclose($fp);
            $msg = $e->getMessage();
            $this->log[] = "[smtp] EXCEPTION: $msg";
            $hint = self::interpretSmtpError($msg);
            return [
                'success' => false,
                'message' => "SMTP: $msg",
                'hint'    => $hint,
            ];
        }
    }

    /**
     * Mapeia respostas SMTP comuns pra dicas acionáveis. Foca nos códigos
     * 4xx/5xx + texto chave do server. Retorna string vazia se não
     * reconheceu — UI mostra só o erro cru nesse caso.
     */
    private static function interpretSmtpError(string $serverMessage): string
    {
        // Extrai o código SMTP de 3 dígitos no início da última linha do erro
        $code = 0;
        if (preg_match('/\b([45]\d{2})\b/', $serverMessage, $m)) {
            $code = (int) $m[1];
        }
        $lower = strtolower($serverMessage);

        // Sinais textuais (mais específicos que o código sozinho)
        $mentionsSender = str_contains($lower, 'sender') || str_contains($lower, 'remetente');
        $mentionsNotAllowed = str_contains($lower, 'not authorized')
            || str_contains($lower, 'not allowed')
            || str_contains($lower, 'permission')
            || str_contains($lower, 'permissao para enviar')
            || str_contains($lower, 'não tem permissão')
            || str_contains($lower, 'nao tem permissao')
            || str_contains($lower, 'address rejected')
            || str_contains($lower, 'not owned')
            || str_contains($lower, 'unverified');
        if ($mentionsSender && $mentionsNotAllowed) {
            return "Remetente não autorizado no provedor. O endereço em 'From' precisa ser verificado/aprovado no painel do seu provedor SMTP (Mailgun, SES, SendGrid, smptlw, etc). Ou use um 'From' que já esteja na lista de remetentes liberados — geralmente o próprio endereço usado no campo 'Usuário (auth)'.";
        }
        if (str_contains($lower, 'spf') || str_contains($lower, 'dkim') || str_contains($lower, 'dmarc')) {
            return "Falha de autenticação de domínio (SPF/DKIM/DMARC). Configure registros DNS do domínio do remetente apontando pro provedor SMTP — sem isso, mensagens são rejeitadas como spoofing.";
        }
        if (str_contains($lower, 'authentication') || str_contains($lower, 'auth fail') || str_contains($lower, 'senha inválida') || str_contains($lower, 'username invál')) {
            return "Autenticação falhou. Confira usuário/senha. Para Gmail use App Password (não a senha normal). Para SendGrid, user = literal 'apikey'.";
        }
        if (str_contains($lower, 'starttls') || str_contains($lower, 'must issue') || str_contains($lower, 'crypto') || str_contains($lower, 'tls negotiation')) {
            return "Encriptação exigida ou negociação TLS falhou. Verifique se a porta (587 = STARTTLS, 465 = SMTPS) bate com a 'Encriptação' configurada. Alguns servidores exigem TLS 1.2+ — se o servidor for muito antigo pode haver incompatibilidade.";
        }
        if (str_contains($lower, 'relay') && (str_contains($lower, 'denied') || str_contains($lower, 'access'))) {
            return "Relay negado. O servidor SMTP só aceita envio se autenticado (configure usuário/senha) OU se você estiver numa rede liberada (IP allowlist).";
        }
        if (str_contains($lower, 'connection') && (str_contains($lower, 'refused') || str_contains($lower, 'timed out'))) {
            return "Conexão recusada ou expirou. Confira host/porta. Verifique se o firewall do servidor permite saída pra essa porta (587/465/25 podem estar bloqueadas pelo provedor de cloud).";
        }
        if (str_contains($lower, 'mailbox') && (str_contains($lower, 'unavailable') || str_contains($lower, 'does not exist'))) {
            return "Destinatário não existe ou caixa indisponível. Confira o endereço de email digitado no teste.";
        }
        if (str_contains($lower, 'over quota') || str_contains($lower, 'storage')) {
            return "Limite de envio do provedor atingido. Espere alguns minutos ou veja a quota da sua conta no painel.";
        }
        if (str_contains($lower, 'blacklist') || str_contains($lower, 'block')) {
            return "Email ou IP em blacklist. Verifique se o domínio do remetente não está em listas de spam (mxtoolbox.com/blacklists).";
        }

        // Fallback por código
        switch ($code) {
            case 421: return "Servidor temporariamente indisponível (421). Tente de novo em alguns minutos.";
            case 451: return "Erro temporário do servidor (451). Pode ser greylisting — tente de novo após alguns minutos.";
            case 452: return "Espaço insuficiente no servidor (452).";
            case 521: case 554:
                return "Servidor rejeitou a transação. Pode ser spam-blocker do destinatário OU regra do provedor — confira o painel SMTP.";
            case 525: return "Conta desabilitada ou remetente não autorizado (525). Verifique no painel do provedor.";
            case 530: return "Autenticação obrigatória (530). Configure usuário/senha SMTP — sem auth o servidor não aceita.";
            case 535: return "Credenciais SMTP inválidas (535). Senha errada ou conta bloqueada. Para Gmail, gere App Password.";
            case 550: return "Endereço rejeitado (550). Pode ser destinatário inexistente, remetente não autorizado, ou conteúdo classificado como spam.";
            case 553: return "Nome de remetente ou destinatário inválido (553). Confira o formato dos endereços.";
        }
        return '';
    }

    /**
     * Codifica header não-ASCII (subject) em UTF-8 base64 (RFC 2047).
     */
    private static function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7e]*$/', $value)) return $value;
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
