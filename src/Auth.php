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
            session_regenerate_id(true);
            $_SESSION['logged_in']  = true;
            $_SESSION['username']   = $user;
            $_SESSION['role']       = $result['role'] ?? 'viewer';
            $_SESSION['api_jwt']    = $result['token'];
            $_SESSION['jwt_expires_at'] = self::_extractJwtExp($result['token']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            // user_id: descobre via /api/v1/auth/me (precisa pra POSTs de toggle/delete que comparam self)
            $me = ApiClient::get('/api/v1/auth/me', $result['token']);
            if ($me['ok'] && isset($me['data']['id'])) {
                $_SESSION['user_id'] = (int) $me['data']['id'];
                $_SESSION['email']   = $me['data']['email'] ?? null;
            }
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
        $resp = self::_unauthedGet('/api/v1/users/exists');
        return is_array($resp) && !empty($resp['exists']);
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
        $resp = self::_unauthedGet('/api/v1/users/exists');
        if (is_array($resp)) {
            return !empty($resp['exists']);
        }
        // API não respondeu — confia na flag local de instalação completa.
        return file_exists(__DIR__ . '/../data/.installed');
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
