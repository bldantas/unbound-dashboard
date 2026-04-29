<?php

namespace App;

require_once __DIR__ . '/Database.php';

use PDO;
use Exception;

session_start();

class Auth {

    public static function login(string $user, string $pass): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id, password_hash, role, is_active, failed_logins, locked_until FROM users WHERE username = ?');
        $stmt->execute([$user]);
        $row = $stmt->fetch();
        
        if (!$row) return ['success' => false, 'message' => 'Usuário ou senha inválidos.'];
        
        if ($row['is_active'] == 0) return ['success' => false, 'message' => 'Usuário desativado pelo administrador.'];
        
        if (!empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Muitas tentativas falhas. Conta bloqueada temporariamente.'];
        }
        
        if (password_verify($pass, $row['password_hash'])) {
            $db->prepare("UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?")->execute([$row['id']]);
            
            session_regenerate_id(true);

            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            return ['success' => true];
        } else {
            $failed = (int)$row['failed_logins'] + 1;
            if ($failed >= 5) {
                $db->prepare("UPDATE users SET failed_logins = ?, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?")->execute([$failed, $row['id']]);
                return ['success' => false, 'message' => 'Muitas tentativas falhas. Conta bloqueada por 15 minutos.'];
            } else {
                $db->prepare("UPDATE users SET failed_logins = ? WHERE id = ?")->execute([$failed, $row['id']]);
                return ['success' => false, 'message' => 'Usuário ou senha inválidos. (Tentativa ' . $failed . ' de 5)'];
            }
        }
    }

    public static function updatePassword(string $username, string $oldPass, string $newPass): array {
        $db = Database::getInstance();
        
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        
        if (!$row || !password_verify($oldPass, $row['password_hash'])) {
            return ['success' => false, 'message' => 'Senha atual incorreta.'];
        }

        if (strlen($newPass) < 6) {
            return ['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.'];
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $update = $db->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
        
        try {
            if ($update->execute([$newHash, $username])) {
                return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro estrutural no banco de dados.'];
        }

        return ['success' => false, 'message' => 'Erro ao salvar a senha no banco de dados.'];
    }

    public static function getAllUsers(): array {
        $db = Database::getInstance();
        return $db->query("SELECT id, username, email, role, is_active, failed_logins, locked_until, created_at FROM users ORDER BY id ASC")->fetchAll();
    }

    public static function addUser(string $username, string $password, string $role = 'viewer', ?string $email = null): array {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if (strlen($password) < 6) return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'];
        if (empty(trim($username))) return ['success' => false, 'message' => 'Nome de usuário inválido.'];
        
        $db = Database::getInstance();
        
        $check = $db->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) return ['success' => false, 'message' => 'Nome de usuário ou email indisponível.'];
        
        if ($email) {
            $checkEmail = $db->prepare('SELECT id FROM users WHERE email = ?');
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) return ['success' => false, 'message' => 'Nome de usuário ou email indisponível.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $db->prepare('INSERT INTO users (username, password_hash, role, email) VALUES (?, ?, ?, ?)');
        if ($insert->execute([$username, $hash, $role, $email])) {
            return ['success' => true, 'message' => 'Usuário criado com sucesso.'];
        }
        return ['success' => false, 'message' => 'Erro ao criar usuário.'];
    }

    public static function updateEmail(string $username, string $newEmail): array {
        $db = Database::getInstance();
        $check = $db->prepare('SELECT id FROM users WHERE email = ? AND username != ?');
        $check->execute([$newEmail, $username]);
        if ($check->fetch()) return ['success' => false, 'message' => 'Este email já está em uso.'];
        
        $update = $db->prepare('UPDATE users SET email = ? WHERE username = ?');
        if ($update->execute([$newEmail, $username])) {
            return ['success' => true, 'message' => 'Email atualizado com sucesso.'];
        }
        return ['success' => false, 'message' => 'Erro ao atualizar email.'];
    }

    public static function requestPasswordReset(string $email): array {
        if (empty(trim($email))) return ['success' => false];
        
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $db->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?")->execute([$tokenHash, $user['id']]);
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetLink = "$protocol://$domain/reset.php?token=" . $token;
            
            $to = $email;
            $subject = 'Recuperação de Senha - UnboundDNS';
            $message = "Você solicitou a recuperação de senha.\n\n" .
                       "Acesse o link abaixo para criar uma nova senha. Este link expira em 10 minutos:\n" .
                       "$resetLink\n\n" .
                       "Se você não solicitou, ignore este email.";
            $headers = "From: admin@$domain\r\nReply-To: admin@$domain\r\nX-Mailer: PHP/" . phpversion();
            
            @mail($to, $subject, $message, $headers);
        }
        
        return ['success' => true, 'message' => 'Caso o seu email esteja inserido corretamente, você receberá um email contendo um link para gerar a nova senha.'];
    }

    public static function resetPassword(string $token, string $newPassword): array {
        if (strlen($newPassword) < 6) return ['success' => false, 'message' => 'Senha deve ter no mínimo 6 caracteres.'];
        
        $db = Database::getInstance();
        $tokenHash = hash('sha256', $token);
        
        $stmt = $db->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1');
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Link de recuperação inválido ou expirado.'];
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, failed_logins = 0, locked_until = NULL WHERE id = ?")->execute([$newHash, $user['id']]);
        
        return ['success' => true, 'message' => 'Sua senha foi alterada com sucesso! Você já pode fazer login.'];
    }

    public static function toggleUserStatus(int $id): array {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if ($_SESSION['user_id'] == $id) return ['success' => false, 'message' => 'Você não pode desativar a si mesmo.'];
        
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?');
        if ($stmt->execute([$id])) {
            return ['success' => true, 'message' => 'Status do usuário alterado com sucesso.'];
        }
        return ['success' => false, 'message' => 'Erro ao alterar status.'];
    }

    public static function deleteUser(int $id): array {
        if (!self::isAdmin()) return ['success' => false, 'message' => 'Permissão negada.'];
        if ($_SESSION['user_id'] == $id) return ['success' => false, 'message' => 'Você não pode excluir a si mesmo.'];
        
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        if ($stmt->execute([$id])) {
            return ['success' => true, 'message' => 'Usuário removido.'];
        }
        return ['success' => false, 'message' => 'Erro ao remover usuário.'];
    }

    public static function hasUsers(): bool {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT COUNT(*) FROM users');
        return $stmt->fetchColumn() > 0;
    }

    public static function check(): void {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            header('Location: login.php');
            exit;
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function isAdmin(): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function logout(): void {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
}
