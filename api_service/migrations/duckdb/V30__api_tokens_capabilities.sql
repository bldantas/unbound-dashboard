-- V30: capabilities granulares por API token (v2.110).
--
-- Hoje API tokens dão role=admin global a quem os usa. Pra integrações
-- externas (SDK Python/JS, scripts, dashboards de terceiros) isso é
-- excessivo — vai contra o princípio do menor privilégio.
--
-- Solução: coluna `capabilities` (JSON array de strings).
--   NULL ou []      → admin global (backward-compat com tokens existentes)
--   ["cap1", ...]   → token tem APENAS essas caps (sem fallback pra admin)
--
-- Caps válidas são as mesmas do RBAC (rbac.py CAPABILITIES). Backend
-- valida na criação + no check de cada request.
--
-- Use cases:
--   ["dashboard.read", "blocklist.read"]                → read-only API
--   ["blocklist.read", "blocklist.write"]               → bot que gerencia allowlist
--   ["alerts.read", "alerts.resolve"]                   → integração com PagerDuty
--   ["dashboard.read", "alerts.read", "blocklist.read"] → SDK genérico read

ALTER TABLE api_tokens ADD COLUMN IF NOT EXISTS capabilities JSON;
