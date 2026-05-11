-- V2: rastreamento de last_login_at na tabela users.
-- Preenchido em auth_service.login_user() após verificação de senha bem-sucedida.
-- Exposto na UI (users.php) pra dar visibilidade sobre contas dormentes.

ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP;
