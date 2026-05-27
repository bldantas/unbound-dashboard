-- V26: per-user notification preferences + daily digest.
--
-- Cada user (não-API-token) pode controlar:
-- - severity_min: 'critical' | 'warning' | 'info' — filtro mínimo de severidade
-- - categories: JSON array de prefixos (vazio = todas).
--   Ex: '["alert", "anomaly_"]' — só alertas + anomalias; vazio = qualquer
-- - digest_enabled: liga digest diário por email
-- - digest_hour: hora local do servidor (0..23) em que o DigestSender envia
-- - last_digest_sent_at: timestamp do último envio (anti double-send)
--
-- Worker DigestSender roda a cada hora. Pra cada user com digest_enabled,
-- se digest_hour == hora atual e last_digest_sent_at < hoje, envia.

CREATE SEQUENCE IF NOT EXISTS user_notification_prefs_id_seq START 1;

CREATE TABLE IF NOT EXISTS user_notification_prefs (
    id                      INTEGER     PRIMARY KEY DEFAULT nextval('user_notification_prefs_id_seq'),
    user_id                 INTEGER     NOT NULL UNIQUE,
    severity_min            VARCHAR(20) NOT NULL DEFAULT 'warning',
    categories              TEXT        NOT NULL DEFAULT '[]',
    digest_enabled          BOOLEAN     NOT NULL DEFAULT false,
    digest_hour             INTEGER     NOT NULL DEFAULT 8,
    last_digest_sent_at     TIMESTAMP,
    updated_at              TIMESTAMP   NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_unp_user_id ON user_notification_prefs (user_id);
CREATE INDEX IF NOT EXISTS idx_unp_digest_hour ON user_notification_prefs (digest_hour, digest_enabled);
