<?php

namespace App;

require_once __DIR__ . '/ApiClient.php';
// Safety net transicional: alguns arquivos PHP (ex: health.php) dependem do
// auto-load de Database.php via Auth.php. Manter até MariaDB tear-down completo.
require_once __DIR__ . '/Database.php';

session_start();

/**
 * Auth — agora 100% via FastAPI (api_service em 127.0.0.1:8001).
 *
 * Migração 2026-05-04: removidas todas as queries PDO/MariaDB. Todos os
 * métodos passam a chamar ApiClient (que conversa com api_service consumindo
 * DuckDB). Isso destrava o tear-down do MariaDB.
 *
 * Sessão PHP continua sendo a fonte de "está logado" (usada pelo Apache + páginas
 * PHP). FastAPI emite JWT no login que vai pra $_SESSION['api_jwt'] e é usado
 * pelas demais chamadas que exigem auth (Bearer).
 */
class Auth
{
    public static function login(string $user, string $pass): array
    {
        $result = ApiClient::login($user, $pass);
        if ($result['ok']) {
            // Caso 2FA: API ainda não emitiu JWT — guarda challenge e
            // sinaliza pro login.php redirecionar pra login_2fa.php.
            if (!empty($result['requires_totp'])) {
                $_SESSION['totp_challenge'] = $result['challenge_token'];
                $_SESSION['totp_username']  = $user;
                $_SESSION['totp_started_at']= time();
                return ['success' => true, 'requires_totp' => true];
            }
            self::_finalizeLogin($user, $result['token'], $result['role'] ?? 'viewer');
            return ['success' => true];
        }

        // Translation entre HTTP status do FastAPI e mensagens UI legadas
        $reason = $result['reason'] ?? '';
        if (str_contains($reason, '429')) {
            return ['success' => false, 'message' => 'Muitas tentativas falhas. Conta bloqueada por 15 minutos.'];
        }
        if (str_contains($reason, '401')) {
            return ['success' => false, 'message' => 'Usuário ou senha inválidos.'];
        }
        return ['success' => false, 'message' => 'Serviço indisponível. Tente novamente em instantes.'];
    }

    /**
     * Segundo passo do 2FA: recebe code 6-dígitos, troca o challenge por JWT.
     * Usado por login_2fa.php após login() retornar requires_totp.
     */
    public static function login2faSubmit(string $code): array
    {
        $challenge = $_SESSION['totp_challenge'] ?? '';
        $username  = $_SESSION['totp_username'] ?? '';
        if ($challenge === '' || $username === '') {
            return ['success' => false, 'message' => 'Sessão de login expirou — refaça login.'];
        }
        $result = ApiClient::login2faVerify($challenge, $code);
        if ($result['ok']) {
            unset($_SESSION['totp_challenge'], $_SESSION['totp_username'], $_SESSION['totp_started_at']);
            self::_finalizeLogin($username, $result['token'], $result['role'] ?? 'viewer');
            return ['success' => true];
        }
        $reason = $result['reason'] ?? '';
        if (str_contains($reason, '401')) {
            return ['success' => false, 'message' => 'Código inválido ou sessão expirada.'];
        }
        return ['success' => false, 'message' => 'Falha ao verificar 2FA. Tente novamente.'];
    }

    /**
     * Inicia setup de 2FA: pede um secret novo + URI ao backend.
     * Retorna ['success' => bool, 'secret' => str, 'provisioning_uri' => str].
     */
    public static function setup2fa(): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];
        $res = ApiClient::post('/api/v1/auth/2fa/setup', $jwt);
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Falha ao iniciar 2FA: ' . ($res['reason'] ?? '?')];
        }
        return [
            'success'          => true,
            'secret'           => (string) ($res['data']['secret'] ?? ''),
            'provisioning_uri' => (string) ($res['data']['provisioning_uri'] ?? ''),
        ];
    }

    /**
     * Confirma 2FA com secret + code, persistindo no backend.
     */
    public static function confirm2fa(string $secret, string $code): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];
        $res = ApiClient::post('/api/v1/auth/2fa/confirm', $jwt, ['secret' => $secret, 'code' => $code]);
        if ($res['ok']) {
            $_SESSION['totp_enabled'] = true;
            return ['success' => true, 'message' => '2FA ativado com sucesso.'];
        }
        return ['success' => false, 'message' => 'Código inválido — confira o relógio do dispositivo.'];
    }

    /**
     * Self-disable 2FA — exige code TOTP atual.
     */
    public static function disable2fa(string $code): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];
        $res = ApiClient::post('/api/v1/auth/2fa/disable', $jwt, ['code' => $code]);
        if ($res['ok']) {
            $_SESSION['totp_enabled'] = false;
            return ['success' => true, 'message' => '2FA desativado.'];
        }
        return ['success' => false, 'message' => 'Código 2FA inválido.'];
    }

    /**
     * Admin zera 2FA de outro user (caso de celular perdido).
     */
    public static function adminReset2fa(int $userId): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];
        $res = ApiClient::post('/api/v1/auth/2fa/admin-reset/' . $userId, $jwt);
        if ($res['ok']) {
            return ['success' => true, 'message' => '2FA do usuário foi resetado.'];
        }
        return ['success' => false, 'message' => 'Falha ao resetar 2FA: ' . ($res['reason'] ?? '?')];
    }

    /**
     * Helper compartilhado entre login() e login2faSubmit() — popula
     * a sessão final após autenticação bem-sucedida.
     */
    private static function _finalizeLogin(string $username, string $jwt, string $role): void
    {
        session_regenerate_id(true);
        $_SESSION['logged_in']     = true;
        $_SESSION['username']      = $username;
        $_SESSION['role']          = $role;
        $_SESSION['api_jwt']       = $jwt;
        $_SESSION['jwt_expires_at']= self::_extractJwtExp($jwt);
        $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
        $me = ApiClient::get('/api/v1/auth/me', $jwt);
        if ($me['ok'] && isset($me['data']['id'])) {
            $_SESSION['user_id']      = (int) $me['data']['id'];
            $_SESSION['email']        = $me['data']['email'] ?? null;
            $_SESSION['totp_enabled'] = !empty($me['data']['totp_enabled']);
        }
    }

    public static function updatePassword(string $username, string $oldPass, string $newPass): array
    {
        if (strlen($newPass) < 6) {
            return ['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.'];
        }
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') {
            return ['success' => false, 'message' => 'Sessão expirada. Faça login novamente.'];
        }
        $result = ApiClient::changePassword($jwt, $oldPass, $newPass);
        if ($result['ok']) {
            return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
        }
        return ['success' => false, 'message' => 'Senha atual incorreta.'];
    }

    public static function getAllUsers(): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return [];
        $result = ApiClient::get('/api/v1/users', $jwt);
        if (!$result['ok'] || !is_array($result['data'])) return [];
        return $result['data'];
    }

    public static function addUser(string $username, string $password, string $role = 'viewer', ?string $email = null): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (strlen($password) < 6) return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'];
        if (empty(trim($username))) return ['success' => false, 'message' => 'Nome de usuário inválido.'];

        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];

        $result = ApiClient::post('/api/v1/users', $jwt, [
            'username' => $username,
            'password' => $password,
            'role'     => $role,
            'email'    => $email,
        ]);
        if ($result['ok']) {
            return ['success' => true, 'message' => 'Usuário criado com sucesso.'];
        }
        $http = $result['http'] ?? 0;
        if ($http === 409) return ['success' => false, 'message' => 'Nome de usuário ou email indisponível.'];
        if ($http === 400) return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'];
        return ['success' => false, 'message' => 'Erro ao criar usuário.'];
    }

    public static function updateEmail(string $username, string $newEmail): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];

        // Lookup user_id pelo username (FastAPI usa id, não username)
        $userId = self::_findUserIdByUsername($username);
        if ($userId === null) return ['success' => false, 'message' => 'Usuário não encontrado.'];

        $result = ApiClient::put("/api/v1/users/{$userId}/email", $jwt, ['email' => $newEmail]);
        if ($result['ok']) {
            return ['success' => true, 'message' => 'Email atualizado com sucesso.'];
        }
        $http = $result['http'] ?? 0;
        if ($http === 409) return ['success' => false, 'message' => 'Este email já está em uso.'];
        if ($http === 403) return ['success' => false, 'message' => 'Permissão negada.'];
        return ['success' => false, 'message' => 'Erro ao atualizar email.'];
    }

    public static function requestPasswordReset(string $email): array
    {
        if (empty(trim($email))) return ['success' => false];

        // FastAPI gera token + grava em DuckDB; PHP entrega via Mailer (SMTP ou
        // mail() fallback) + sempre grava no log file pra debug/recuperação manual.
        $resp  = self::_unauthedPost('/api/v1/auth/password-reset/request', ['email' => $email]);
        $token = is_array($resp) ? ($resp['token'] ?? null) : null;

        if ($token) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $domain   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetLink = "$protocol://$domain/reset.php?token=" . $token;
            $subject = 'Recuperação de Senha - UnboundDNS';
            $message = "Você solicitou a recuperação de senha.\n\n" .
                       "Acesse o link abaixo para criar uma nova senha. Este link expira em 10 minutos:\n" .
                       "$resetLink\n\n" .
                       "Se você não solicitou, ignore este email.";

            // 1) Tenta enviar via Mailer (SMTP configurado em /config?tab=email,
            //    ou fallback pra mail() PHP).
            require_once __DIR__ . '/Mailer.php';
            $mailer = new \App\Mailer();
            $mailResult = $mailer->send($email, $subject, $message);
            $mailSent = !empty($mailResult['success']);

            // 2) SEMPRE grava no log local — admin pode recuperar via SSH se
            // o envio falhou. Path: src/data/password-recovery.log (640 www-data).
            $logFile = __DIR__ . '/data/password-recovery.log';
            $logLine = sprintf(
                "[%s] email=%s mail_sent=%s via=%s remote_ip=%s\n  link=%s\n",
                date('Y-m-d H:i:s'),
                $email,
                $mailSent ? 'true' : 'false',
                $mailer->isConfigured() ? 'smtp' : 'php-mail',
                $_SERVER['REMOTE_ADDR'] ?? '?',
                $resetLink
            );
            @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
            @chmod($logFile, 0640);
        }

        // Mensagem genérica (timing-safe — não revela se email existe).
        return [
            'success' => true,
            'message' => 'Caso o seu email esteja cadastrado, você receberá instruções para criar uma nova senha. O link expira em 10 minutos.',
        ];
    }

    public static function resetPassword(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'];
        }
        $resp = self::_unauthedPost('/api/v1/auth/password-reset/confirm', [
            'token'        => $token,
            'new_password' => $newPassword,
        ]);
        if ($resp === null) {
            return ['success' => false, 'message' => 'Link de recuperação inválido ou expirado.'];
        }
        return ['success' => true, 'message' => 'Sua senha foi alterada com sucesso! Você já pode fazer login.'];
    }

    public static function toggleUserStatus(int $id): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (($_SESSION['user_id'] ?? 0) == $id) return ['success' => false, 'message' => 'Você não pode desativar a si mesmo.'];

        $jwt = $_SESSION['api_jwt'] ?? '';
        $result = ApiClient::put("/api/v1/users/{$id}/active", $jwt);
        if ($result['ok']) {
            return ['success' => true, 'message' => 'Status do usuário alterado com sucesso.'];
        }
        $http = $result['http'] ?? 0;
        if ($http === 404) return ['success' => false, 'message' => 'Usuário não encontrado.'];
        return ['success' => false, 'message' => 'Erro ao alterar status.'];
    }

    public static function deleteUser(int $id): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (($_SESSION['user_id'] ?? 0) == $id) return ['success' => false, 'message' => 'Você não pode excluir a si mesmo.'];

        $jwt = $_SESSION['api_jwt'] ?? '';
        $result = ApiClient::delete("/api/v1/users/{$id}", $jwt);
        if ($result['ok']) {
            return ['success' => true, 'message' => 'Usuário removido.'];
        }
        return ['success' => false, 'message' => 'Erro ao remover usuário.'];
    }

    public static function updateRole(int $id, string $newRole): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (($_SESSION['user_id'] ?? 0) == $id) {
            return ['success' => false, 'message' => 'Você não pode mudar seu próprio role. Peça pra outro admin.'];
        }
        $jwt = $_SESSION['api_jwt'] ?? '';
        $result = ApiClient::put("/api/v1/users/{$id}/role", $jwt, ['role' => $newRole]);
        if (!empty($result['ok'])) {
            return ['success' => true, 'message' => 'Role atualizado para ' . $newRole . '.'];
        }
        $reason = $result['reason'] ?? '';
        if ($reason === 'http_400') return ['success' => false, 'message' => 'Role inválido ou self-target.'];
        if ($reason === 'http_404') return ['success' => false, 'message' => 'Usuário não encontrado.'];
        return ['success' => false, 'message' => 'Erro ao atualizar role (' . $reason . ').'];
    }

    /**
     * Lista sessões ativas do usuário corrente (Redis tracking).
     * Inclui IP, user-agent, login_at, last_seen, token_hash.
     */
    public static function listMySessions(): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return [];
        $result = ApiClient::get('/api/v1/auth/sessions', $jwt);
        if (!empty($result['ok']) && isset($result['data']['sessions'])) {
            return $result['data']['sessions'];
        }
        return [];
    }

    /**
     * Revoga uma sessão específica do user corrente. Adiciona o token_hash
     * ao denylist Redis — próxima request com aquele token recebe 401.
     */
    public static function revokeMySession(string $tokenHash): array
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return ['success' => false, 'message' => 'Sessão expirada.'];
        // ApiClient::delete não aceita corpo — token_hash vai no path
        $tokenHash = preg_replace('/[^a-f0-9]/', '', strtolower($tokenHash));
        if ($tokenHash === '') return ['success' => false, 'message' => 'Hash inválido.'];
        $result = ApiClient::delete("/api/v1/auth/sessions/{$tokenHash}", $jwt);
        if (!empty($result['ok'])) {
            return ['success' => true, 'message' => 'Sessão encerrada.'];
        }
        $reason = $result['reason'] ?? '';
        if ($reason === 'http_404') return ['success' => false, 'message' => 'Sessão não encontrada (talvez já expirada).'];
        return ['success' => false, 'message' => 'Falha ao encerrar sessão (' . $reason . ').'];
    }

    /**
     * Admin gera senha temporária pra outro user. Retorna a senha em texto
     * pra ser exibida UMA VEZ na UI; admin entrega manualmente.
     */
    public static function adminResetPassword(int $id): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        $jwt = $_SESSION['api_jwt'] ?? '';
        $result = ApiClient::post("/api/v1/users/{$id}/password-reset", $jwt, []);
        if (!empty($result['ok']) && isset($result['data']['temporary_password'])) {
            return [
                'success' => true,
                'message' => 'Senha temporária gerada com sucesso.',
                'temporary_password' => $result['data']['temporary_password'],
            ];
        }
        $reason = $result['reason'] ?? '';
        if ($reason === 'http_404') return ['success' => false, 'message' => 'Usuário não encontrado.'];
        return ['success' => false, 'message' => 'Erro ao resetar senha (' . $reason . ').'];
    }

    /**
     * Retorna true se há ao menos um user no DuckDB. Estritamente
     * baseado na resposta da API. Use `hasUsersOrApiDown()` em
     * páginas públicas que NÃO devem redirecionar quando a API
     * está só temporariamente offline.
     */
    public static function hasUsers(): bool
    {
        // Cache positivo (existe usuário) por 5min: admin não é deletado
        // em loop, então é seguro cachear. Cache negativo NÃO é gravado
        // pra que o wizard de instalação destrave assim que admin for criado.
        $cached = self::_readUsersExistsCache();
        if ($cached === true) return true;
        $resp = self::_unauthedGet('/api/v1/users/exists');
        if (is_array($resp) && !empty($resp['exists'])) {
            self::_writeUsersExistsCache(true);
            return true;
        }
        return false;
    }

    /**
     * Verificação tolerante usada por index.php: retorna `true` se
     * existem users OU se a API está offline mas existe a flag
     * data/.installed (sistema foi instalado mas api_service não
     * está respondendo agora). Evita o cenário "redireciono pro
     * wizard de instalação só porque o api_service caiu por 5s".
     *
     * Casos:
     *   - API respondeu `{exists: true}` → true (caminho feliz)
     *   - API respondeu `{exists: false}` → false (sistema cru, mostra wizard)
     *   - API não respondeu mas `data/.installed` existe → true (transiente)
     *   - API não respondeu E `data/.installed` ausente → false (instalação incompleta)
     */
    public static function hasUsersOrApiDown(): bool
    {
        // Mesma estratégia de cache que hasUsers() — economiza ~1000 chamadas/dia
        $cached = self::_readUsersExistsCache();
        if ($cached === true) return true;
        $resp = self::_unauthedGet('/api/v1/users/exists');
        if (is_array($resp)) {
            $exists = !empty($resp['exists']);
            if ($exists) self::_writeUsersExistsCache(true);
            return $exists;
        }
        // API não respondeu — confia na flag local de instalação completa.
        return file_exists(__DIR__ . '/../data/.installed');
    }

    /** Lê cache file de "tem usuários?". TTL 5min. Retorna true/false/null (miss). */
    private static function _readUsersExistsCache(): ?bool
    {
        $path = __DIR__ . '/../data/.users_exists_cache';
        if (!is_file($path)) return null;
        $mtime = @filemtime($path);
        if ($mtime === false || (time() - $mtime) > 300) return null;
        $content = @file_get_contents($path);
        if ($content === false) return null;
        return trim($content) === '1' ? true : null;
    }

    /** Grava cache file. Falha silenciosa (ex: permissão). */
    private static function _writeUsersExistsCache(bool $exists): void
    {
        if (!$exists) return;  // não cachear negativos (ver hasUsers docstring)
        @file_put_contents(__DIR__ . '/../data/.users_exists_cache', '1');
    }

    public static function check(): void
    {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: login.php');
            exit;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // -- Sliding session via /api/v1/auth/refresh --
        // Se o JWT está prestes a expirar (≤5min), tenta renovar
        // silenciosamente. Se já expirou (sem grace), força logout
        // pra evitar sessões "zumbi" onde sessão PHP é válida mas
        // chamadas FastAPI retornam 401 silenciosamente.
        $expiresAt = (int) ($_SESSION['jwt_expires_at'] ?? 0);
        if ($expiresAt > 0) {
            $remaining = $expiresAt - time();
            if ($remaining <= 0) {
                // Já expirou totalmente — tenta refresh (FastAPI aceita até 10min de grace).
                if (!self::refreshJwt()) {
                    self::logoutWithReason('jwt_expired');
                }
            } elseif ($remaining <= 300) {
                // Próximo de expirar (≤5min) — refresh silencioso pra estender sessão.
                self::refreshJwt(); // falha silenciosa — próxima request tenta de novo
            }
        }
    }

    /**
     * Chama POST /api/v1/auth/refresh com o JWT atual e atualiza a sessão
     * se sucesso. Retorna true se renovou; false em qualquer falha
     * (chamador decide o que fazer — geralmente forçar logout se já expirou).
     */
    public static function refreshJwt(): bool
    {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt === '') return false;
        $result = ApiClient::post('/api/v1/auth/refresh', $jwt, []);
        if (!empty($result['ok']) && isset($result['data']['access_token'])) {
            $_SESSION['api_jwt'] = $result['data']['access_token'];
            $_SESSION['jwt_expires_at'] = self::_extractJwtExp($result['data']['access_token']);
            if (!empty($result['data']['role'])) {
                $_SESSION['role'] = $result['data']['role'];
            }
            return true;
        }
        return false;
    }

    /**
     * Logout forçado com motivo passado pra UI (login.php exibe banner).
     */
    public static function logoutWithReason(string $reason): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?reason=' . urlencode($reason));
        exit;
    }

    /**
     * Extrai o claim `exp` (epoch seconds) de um JWT sem validar a
     * assinatura. Não é segurança — só decodifica base64 do payload
     * pra saber quando expira. Validação real fica no FastAPI.
     */
    private static function _extractJwtExp(string $jwt): int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) return 0;
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) return 0;
        $data = json_decode($payload, true);
        return (int) ($data['exp'] ?? 0);
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * RBAC granular — verifica se o role atual tem a capability.
     * Espelha api_service/app/core/rbac.py — manter sincronizado.
     */
    public static function can(string $capability): bool
    {
        $role = $_SESSION['role'] ?? null;
        if (!$role) return false;
        static $caps = [
            'config.write'           => ['admin'],
            'users.manage'           => ['admin'],
            'webhooks.manage'        => ['admin'],
            'smtp.manage'            => ['admin'],
            'alerts.resolve'         => ['admin', 'operator'],
            'blocklist.write'        => ['admin', 'operator'],
            'alerts.read'            => ['admin', 'readonly_admin', 'operator'],
            'blocklist.read'         => ['admin', 'readonly_admin', 'operator'],
            'users.read'             => ['admin', 'readonly_admin'],
            'config.read_sensitive'  => ['admin', 'readonly_admin'],
            'dashboard.read'         => ['admin', 'readonly_admin', 'operator', 'viewer'],
        ];
        return isset($caps[$capability]) && in_array($role, $caps[$capability], true);
    }

    /**
     * Roles válidos no sistema. Espelha rbac.py VALID_ROLES.
     * Label = texto humano (pt-BR) usado em dropdowns.
     */
    public static function rolesCatalog(): array
    {
        return [
            'admin'          => ['label' => 'Admin',          'desc' => 'Acesso total: configuração, usuários, alertas, SMTP/webhooks.'],
            'readonly_admin' => ['label' => 'Admin somente-leitura', 'desc' => 'Vê tudo (inclui SMTP/webhooks/users) mas não modifica.'],
            'operator'       => ['label' => 'Operador (NOC)', 'desc' => 'Resolve alertas, mantém blocklist. Não vê SMTP/webhooks/users.'],
            'viewer'         => ['label' => 'Viewer',         'desc' => 'Read-only: dashboard, histórico, ameaças.'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // ---------------------------------------------------------------------
    // Helpers privados — chamadas HTTP públicas (sem JWT) à FastAPI
    // ---------------------------------------------------------------------

    private static function _findUserIdByUsername(string $username): ?int
    {
        $users = self::getAllUsers();
        foreach ($users as $u) {
            if (($u['username'] ?? '') === $username) {
                return (int) $u['id'];
            }
        }
        return null;
    }

    private static function _unauthedGet(string $path): ?array
    {
        $ch = curl_init('http://127.0.0.1:8001' . $path);
        if ($ch === false) return null;
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 3,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $http !== 200) return null;
        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function _unauthedPost(string $path, array $payload): ?array
    {
        $body = json_encode($payload);
        if ($body === false) return null;
        $ch = curl_init('http://127.0.0.1:8001' . $path);
        if ($ch === false) return null;
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 3,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) return null;
        if ($http >= 200 && $http < 300) {
            $decoded = $resp !== '' ? json_decode($resp, true) : [];
            return is_array($decoded) ? $decoded : [];
        }
        return null;
    }
}
