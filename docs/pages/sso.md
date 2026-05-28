# `/sso.php` — OIDC Single Sign-On

Página de configuração do **OIDC SSO** com PKCE S256 (RFC 7636) e mapeamento de grupos do IdP pra roles locais.

**Disponível desde:** v2.16+ (OIDC básico); v2.88 (group mapping); v2.93 (role rank); v2.101.0 (PKCE S256).

---

## Quando usar

Quando você quer que o login do dashboard delegue pra um provedor externo (Microsoft Entra ID, Google Workspace, Keycloak, Authentik, Okta, etc) em vez de gerenciar senhas localmente.

---

## Setup em 4 passos

1. **No IdP**: crie uma app/client com:
   - Redirect URI: `https://<seu-dashboard>/api/v1/auth/oidc/callback`
   - Scopes: `openid email profile` (+ `groups` ou equivalente, se for mapear roles)
   - Auth code flow (não implicit)
2. **No dashboard** (`/sso.php`):
   - Issuer URL (ex: `https://login.microsoftonline.com/<tenant-id>/v2.0`)
   - Client ID + Client Secret (do IdP)
   - Allowed email domains (CSV — vazio = qualquer)
   - Auto-create users (cria conta local na primeira vez que o user faz SSO)
   - Default role pros auto-criados (geralmente `viewer`)
3. **Group mapping** (opcional, recomendado):
   - **Group claim**: nome do claim no id_token que carrega os grupos. Suporta dot-path (ex: `realm_access.roles` pro Keycloak).
   - **Mappings**: JSON `{idp_group: local_role}`. Ex: `{"DnsAdmins": "admin", "DnsOps": "operator", "DnsViewers": "viewer"}`.
   - **Sync role on login**: se marcado, re-aplica o mapping a cada login (override de role manual).
   - **Role rank** (v2.93): se o user pertence a múltiplos grupos mapeados, o de **maior privilégio** vence (admin > readonly_admin > operator > viewer). Antes era "primeira match na ordem do claim" — podia derrubar admin pra viewer.
4. Salvar + clicar **"Testar fluxo"** → redireciona pro IdP. Se voltar com sucesso e a role bater, está pronto.

---

## PKCE — sempre ativo

Desde v2.101.0 o fluxo usa **PKCE S256**:
- `code_verifier` random 43-char base64url
- `code_challenge` = `SHA256(verifier)` base64url-no-padding
- `code_challenge_method=S256` enviado no auth request
- Verifier persiste junto do state, incluído no token exchange

IdPs sem suporte ignoram os parâmetros extras — backwards-compatible. IdPs que **exigem** PKCE (Entra ID moderno) passam.

---

## Secret cifrado em DB

Desde v2.101.0, se `SECRETS_MASTER_KEY` está configurada no env (`/etc/unbound-dashboard/api-v1.env`), o `client_secret` é cifrado via Fernet antes de gravar em `oidc_config.client_secret_encrypted`. Sem master key, fallback plaintext com warning no log (não recomendado em prod).

Pra cifrar secrets já gravados em plaintext (legacy), basta configurar a master key e reiniciar — o `secrets_migrator` roda no startup e cifra automaticamente. Idempotente.

---

## Troubleshooting

| Sintoma | Causa provável |
|---|---|
| "Issuer URL inválido" no salvar | URL não bate com `.well-known/openid-configuration`. Cheque trailing slash. |
| Login funciona mas role vira sempre `viewer` | Group claim errado (verifique o id_token decodificado) ou mappings não inclui os grupos do user. |
| Login bate IdP, volta com `?error=...` | Logs do api_service em `/journalctl -u unbound-dashboard-api | grep oidc` mostram o motivo. Comum: redirect URI no IdP não bate exatamente. |
| Role muda manualmente e na próxima login volta pro mapping | `sync_role_on_login=true` está ativo. Desmarque se quiser overrides manuais. |
| `client_secret` zerado depois de configurar master key | Não foi zerado — foi movido pra `client_secret_encrypted`. Se aparece vazio na UI, é só a UI mostrando o legacy field. Saved secret está cifrado. |

---

## Endpoints relacionados

| Endpoint | Função |
|---|---|
| `GET /api/v1/auth/oidc/login` | Inicia fluxo (redireciona pro IdP com state + PKCE) |
| `GET /api/v1/auth/oidc/callback` | Recebe code, troca por id_token, valida, emite JWT local |
| `GET /api/v1/auth/oidc/config` | Lê config (com client_secret mascarado) |
| `PUT /api/v1/auth/oidc/config` | Atualiza config (admin) |

---

## Limitações conhecidas

- Sem logout federado (logout local apaga session JWT mas não chama o IdP)
- Sem refresh tokens (cada login é fresh — usa sliding JWT local pra renovar sessão dentro do dashboard)
- Group claim suporta dot-path mas não JMESPath/expressões
- Mapping é `idp_group → local_role` 1:1 — sem composição custom

---

## Memórias relacionadas

- [Sessão 2026-05-28 gap](../../.claude/projects/-var-www-html-unbound-dashboard/memory/sessao_2026-05-28_gap.md) — contexto da v2.101.0 (PKCE + master key)
