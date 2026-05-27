-- V25: torna managed_hosts org-aware.
--
-- Política:
-- - org_id = NULL → host "global", visível a todos os admins.
-- - org_id = N    → host pertence à org N; viewer com org_id = N ou viewer
--                   sem org (NULL = system admin global) vê.
--
-- Quem cria escolhe (default NULL no UI quando o user é global; user com
-- org pré-set fixa pra sua própria org). Backfill: hosts já existentes
-- ficam NULL/global — comportamento atual preservado.

ALTER TABLE managed_hosts ADD COLUMN IF NOT EXISTS org_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_managed_hosts_org_id ON managed_hosts (org_id);
