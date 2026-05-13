-- V3: persistência de sessões ativas em DuckDB.
--
-- Antes, sessões viviam só no Redis (udash:session:<user>:<hash>) — restart
-- do Redis perdia todo o histórico. Agora dual-write: Redis primário (fast
-- path, TTL automático), DuckDB autoritativo (sobrevive restart).
--
-- Estratégia (ver app/services/sessions.py):
--   - Cada request autenticada chama track() — atualiza Redis SEMPRE,
--     atualiza DuckDB com throttle (≥30s desde último persist).
--   - list_*() faz union Redis ∪ DuckDB, dedupado por token_hash, preferindo
--     last_seen mais recente.
--   - Startup chama bootstrap_from_duckdb() — rehidrata Redis com sessões
--     não-expiradas, evita janela vazia após restart.
--   - revoke_token_hash() seta revoked_at no row + adiciona ao denylist Redis.
--
-- PK = token_hash (16 chars, SHA256 truncado). Colisão entre tokens reais
-- é negligenciável; pra simplificar joins, mantemos como PK simples.

CREATE TABLE IF NOT EXISTS auth_sessions (
    token_hash     VARCHAR(32)  PRIMARY KEY,
    user_id        INTEGER      NOT NULL,
    ip             VARCHAR(64),
    user_agent     VARCHAR(255),
    iat            INTEGER      NOT NULL,    -- unix epoch (issued at)
    exp            INTEGER      NOT NULL,    -- unix epoch (expira em)
    login_at       INTEGER      NOT NULL,    -- unix epoch primeiro track
    last_seen      INTEGER      NOT NULL,    -- unix epoch último track
    revoked_at     INTEGER                   -- unix epoch revogação (NULL = ativa)
);
