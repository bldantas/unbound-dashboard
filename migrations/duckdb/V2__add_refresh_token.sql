-- Migração V2: suporte a refresh token JWT
ALTER TABLE users ADD COLUMN IF NOT EXISTS refresh_token_hash VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS refresh_expires    TIMESTAMP;
