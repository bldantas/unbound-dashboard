-- V28: multi-tenant em client_policies.
--
-- Mesma regra de hosts/alerts/audit:
--   NULL = global (visível a system admins, NULL viewer)
--   N    = pertence à org N (visível a NULL viewer + viewers com org_id = N)
--
-- client_policies.org_id:
--   Política de DNS por CIDR (split-horizon). Faz sentido natural ser
--   tenant — uma rede de filial X tem políticas próprias que não
--   interferem na rede Y. Child tables (ranges/blocks/allows) herdam
--   indiretamente via policy_id.
--
-- blocklist_sources e blocklist_entries continuam globais — fontes
-- externas (Steven Black etc) são compartilhadas, infra-level.
--
-- blocklist_exceptions tem `domain` como PK e semântica de allowlist
-- global (sobrescreve qualquer bloqueio do resolver). Tornar tenant
-- requer schema rework (composto domain+org_id ou tabela separada).
-- TODO numa V29 quando justificar a complexidade.

ALTER TABLE client_policies ADD COLUMN IF NOT EXISTS org_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_client_policies_org_id ON client_policies (org_id);
