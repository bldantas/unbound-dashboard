-- V24: OIDC group/role mapping.
--
-- Permite mapear claims do id_token (groups, roles, etc) → roles locais.
--
-- group_claim: nome do claim que contém os grupos/roles do usuário no IdP
--   - Google Workspace: não há claim nativo (usa custom mapping)
--   - Microsoft Entra ID: "groups" (object IDs) ou "roles" (app roles)
--   - Keycloak: "groups" ou "realm_access.roles" (dot path)
--   - Authentik: "groups"
--
-- group_mappings: JSON dict {idp_group_name: local_role}. Match: primeiro
-- grupo do array de claims que bate em alguma chave do dict define a role.
-- Se nada bater → cai pra default_role (auto-create) ou mantém role atual.
--
-- sync_role_on_login: quando true, role é re-sincronizada a cada login.
-- false (default) → role só é setada no auto-create; mudanças no IdP não
-- propagam pra users já existentes.

ALTER TABLE oidc_config ADD COLUMN IF NOT EXISTS group_claim VARCHAR(100);
ALTER TABLE oidc_config ADD COLUMN IF NOT EXISTS group_mappings TEXT;
ALTER TABLE oidc_config ADD COLUMN IF NOT EXISTS sync_role_on_login BOOLEAN;

UPDATE oidc_config SET
    group_claim = COALESCE(group_claim, ''),
    group_mappings = COALESCE(group_mappings, '{}'),
    sync_role_on_login = COALESCE(sync_role_on_login, false)
WHERE id = 1;
