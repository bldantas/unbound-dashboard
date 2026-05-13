-- V4: 2FA TOTP opt-in por usuário.
--
-- Cada user pode habilitar TOTP (RFC 6238) opcionalmente. Login flow:
--   1. POST /auth/login com user+pass → valida credenciais
--   2. Se totp_enabled=false: retorna JWT (path atual)
--   3. Se totp_enabled=true: retorna {requires_totp: true, challenge_token}
--      em vez de JWT. PHP redireciona pra /login_2fa.php que pede o 6-digit
--      code. POST /auth/login/2fa-verify com challenge+code → JWT real.
--
-- Sem backup codes nesta versão (admin reseta se user perder celular).
-- Secret em plaintext (escopo interno; mesmo nível do SMTP password).
--
-- DuckDB 1.x não suporta "ADD COLUMN ... NOT NULL DEFAULT" via ALTER —
-- então totp_enabled é NULLABLE; código trata NULL como false.

ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled BOOLEAN;
UPDATE users SET totp_enabled = false WHERE totp_enabled IS NULL;
