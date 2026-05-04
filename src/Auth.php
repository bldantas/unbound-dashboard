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

        // FastAPI gera token + grava em DuckDB; PHP envia o email com o link
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
            $headers = "From: admin@$domain\r\nReply-To: admin@$domain\r\nX-Mailer: PHP/" . phpversion();
            @mail($email, $subject, $message, $headers);
        }

        return ['success' => true, 'message' => 'Caso o seu email esteja inserido corretamente, você receberá um email contendo um link para gerar a nova senha.'];
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

    public static function hasUsers(): bool
    {
        $resp = self::_unauthedGet('/api/v1/users/exists');
        return is_array($resp) && !empty($resp['exists']);
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
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
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
