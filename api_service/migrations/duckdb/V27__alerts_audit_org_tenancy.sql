-- V27: estende multi-tenant pra alerts + admin_audit.
--
-- Padrão idêntico ao V25 (managed_hosts):
-- - NULL = global, visível a todos.
-- - N    = pertence à org N; user com org_id=N ou system admin (NULL) vê.
--
-- alerts.org_id:
--   Maioria dos alerts é global (CPU/memória/auth/serviço). Quando o alert
--   tiver origem em recurso tenant (host gerenciado de outra org no futuro),
--   pode propagar via alert_repo.create_alert(org_id=...). Por ora todos
--   continuam NULL → comportamento atual preservado.
--
-- admin_audit.actor_org_id:
--   org_id do user que executou a ação no momento do log. Permite "ver
--   só auditoria da minha org" sem precisar re-resolver users.org_id
--   (que pode mudar). Resolvido em audit_service.log() lendo users.org_id.

ALTER TABLE alerts ADD COLUMN IF NOT EXISTS org_id INTEGER;
ALTER TABLE admin_audit ADD COLUMN IF NOT EXISTS actor_org_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_alerts_org_id ON alerts (org_id);
CREATE INDEX IF NOT EXISTS idx_admin_audit_actor_org_id ON admin_audit (actor_org_id);
