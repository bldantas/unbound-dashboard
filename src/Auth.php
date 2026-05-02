<?php

namespace App;

require_once __DIR__ . '/Environment.php';
require_once __DIR__ . '/ApiClient.php';

use Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth
{
    public static function login(string $user, string $pass): array
    {
        $api = new ApiClient();
        try {
            $resp = $api->login($user, $pass);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '401')) {
                return ['success' => false, 'message' => 'Usuário ou senha inválidos.'];
            }
            if (str_contains($msg, '403')) {
                return ['success' => false, 'message' => 'Conta bloqueada ou desativada.'];
            }
            return ['success' => false, 'message' => 'Serviço indisponível. Tente novamente.'];
        }

        $token = $resp['access_token'] ?? null;
        if (empty($token)) {
            return ['success' => false, 'message' => 'Resposta inválida do servidor.'];
        }

        $api->setJwt($token);
        try {
            $me = $api->me();
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao carregar perfil do usuário.'];
        }

        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $me['username'] ?? $user;
        $_SESSION['user_id'] = $me['id'] ?? 0;
        $_SESSION['role'] = $me['role'] ?? 'viewer';
        $_SESSION['email'] = $me['email'] ?? '';
        $_SESSION['api_jwt'] = $token;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return ['success' => true];
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['api_jwt'])) {
            try {
                $api = new ApiClient();
                $api->logout();
            } catch (Exception $e) {
            }
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: login.php');
        exit;
    }

    public static function check(): void
    {
        $isApiRequest = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            if ($isApiRequest) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'error' => 'Não autenticado']);
                exit;
            }
            header('Location: login.php');
            exit;
        }

        if (self::isJwtExpired($_SESSION['api_jwt'] ?? null)) {
            $_SESSION = [];
            session_destroy();
            if ($isApiRequest) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'error' => 'Sessão expirada. Faça login novamente.']);
                exit;
            }
            header('Location: login.php');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Verifica se o JWT está expirado decodificando o payload (sem verificar assinatura).
     * Retorna true se expirado ou inválido.
     */
    public static function isJwtExpired(?string $jwt): bool
    {
        if (empty($jwt)) {
            return true;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return true;
        }
        try {
            $padded  = str_pad(strtr($parts[1], '-_', '+/'), (int) ceil(strlen($parts[1]) / 4) * 4, '=');
            $payload = json_decode(base64_decode($padded), true);
            if (!is_array($payload) || !isset($payload['exp'])) {
                return false; // sem claim exp → trata como válido
            }
            return time() > (int) $payload['exp'];
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function updatePassword(string $username, string $oldPass, string $newPass): array
    {
        if (strlen($newPass) < 6) {
            return ['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.'];
        }

        $api = new ApiClient();
        try {
            $api->put('/api/v2/auth/me/password', [
                'old_password' => $oldPass,
                'new_password' => $newPass,
            ]);
            return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '401') || str_contains($msg, '400')) {
                return ['success' => false, 'message' => 'Senha atual incorreta.'];
            }
            return ['success' => false, 'message' => 'Erro ao alterar senha.'];
        }
    }

    public static function updateEmail(string $username, string $newEmail): array
    {
        $api = new ApiClient();
        try {
            $api->put('/api/v2/auth/me/email', ['email' => $newEmail]);
            $_SESSION['email'] = $newEmail;
            return ['success' => true, 'message' => 'Email atualizado com sucesso.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao atualizar email.'];
        }
    }

    public static function getAllUsers(): array
    {
        $api = new ApiClient();
        try {
            return $api->get('/api/v2/users') ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public static function addUser(string $username, string $password, string $role = 'viewer', ?string $email = null): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (strlen($password) < 6) return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'];
        if (empty(trim($username))) return ['success' => false, 'message' => 'Nome de usuário inválido.'];

        $api = new ApiClient();
        try {
            $api->post('/api/v2/users', array_filter([
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'email' => $email,
            ]));
            return ['success' => true, 'message' => 'Usuário criado com sucesso.'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '409') || str_contains($msg, 'already exists')) {
                return ['success' => false, 'message' => 'Nome de usuário ou email indisponível.'];
            }
            return ['success' => false, 'message' => 'Erro ao criar usuário.'];
        }
    }

    public static function toggleUserStatus(int $id): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
            return ['success' => false, 'message' => 'Você não pode desativar a si mesmo.'];
        }

        $api = new ApiClient();
        try {
            $api->post("/api/v2/users/{$id}/toggle-status", []);
            return ['success' => true, 'message' => 'Status do usuário alterado com sucesso.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao alterar status.'];
        }
    }

    public static function deleteUser(int $id): array
    {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
            return ['success' => false, 'message' => 'Você não pode excluir a si mesmo.'];
        }

        $api = new ApiClient();
        try {
            $api->delete("/api/v2/users/{$id}");
            return ['success' => true, 'message' => 'Usuário removido.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao remover usuário.'];
        }
    }

    public static function hasUsers(): bool
    {
        $api = new ApiClient();
        try {
            return !empty($api->get('/api/v2/users'));
        } catch (Exception $e) {
            return false;
        }
    }

    public static function requestPasswordReset(string $email): array
    {
        if (empty(trim($email))) return ['success' => false];

        $api = new ApiClient();
        try {
            $api->post('/api/v2/auth/password-reset/request', ['email' => $email], false);
        } catch (Exception $e) {
        }

        return ['success' => true, 'message' => 'Caso o seu email esteja inserido corretamente, você receberá um email contendo um link para gerar a nova senha.'];
    }

    public static function resetPassword(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'];
        }

        $api = new ApiClient();
        try {
            $api->post('/api/v2/auth/password-reset/confirm', [
                'token' => $token,
                'new_password' => $newPassword,
            ], false);
            return ['success' => true, 'message' => 'Sua senha foi alterada com sucesso! Você já pode fazer login.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Link de recuperação inválido ou expirado.'];
        }
    }
}
