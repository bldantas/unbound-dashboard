<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

if (!Auth::isAdmin()) {
    header('Location: index.php?error=admin_only');
    exit;
}

$currentPage = 'sso.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>SSO/OIDC - Unbound DNS</title>
    <meta name="description" content="Configuração de Single Sign-On via OpenID Connect (Google Workspace, Microsoft Entra ID, Keycloak, etc).">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "SSO / OIDC";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <?= t('sso.title') ?>
                </h1>
                <p class="page-subtitle"><?= t('sso.subtitle') ?></p>
            </header>

            <div id="secretsBanner" class="hidden glass-panel mb-6 p-4 text-xs"></div>

            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Configuração OIDC</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Callback URL pra registrar no IdP: <code id="callbackUrl" class="font-mono text-sky-500">—</code></p>
                </div>
                <div class="p-6 space-y-4 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="sEnabled" class="w-4 h-4">
                        <span class="text-[11px] font-black uppercase tracking-widest">SSO habilitado</span>
                    </label>

                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1 block">Provider preset</span>
                        <div class="flex flex-wrap gap-2">
                            <select id="sPreset" class="glass-input font-mono flex-1 min-w-[240px]">
                                <option value="">— escolha um provider —</option>
                                <option value="google">Google Workspace / Cloud Identity</option>
                                <option value="entra">Microsoft Entra ID (Azure AD)</option>
                                <option value="okta">Okta</option>
                                <option value="auth0">Auth0</option>
                                <option value="keycloak">Keycloak</option>
                                <option value="authentik">Authentik</option>
                                <option value="zitadel">Zitadel</option>
                                <option value="custom">Custom (manual)</option>
                            </select>
                            <button type="button" id="btnApplyPreset" class="glass-btn !bg-slate-600 !text-white text-[10px] uppercase font-black">Aplicar preset</button>
                        </div>
                        <p id="presetHint" class="text-[10px] text-slate-500 mt-2"></p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Issuer URL</span>
                            <div class="flex gap-2">
                                <input type="text" id="sIssuer" placeholder="https://accounts.google.com" class="glass-input flex-1 font-mono">
                                <button type="button" id="btnProbe" class="glass-btn !bg-indigo-600 !text-white text-[10px] uppercase font-black whitespace-nowrap">Testar conectividade</button>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-1">Base do <code>/.well-known/openid-configuration</code>.</p>
                            <div id="probeResult" class="hidden mt-2 p-3 rounded-lg text-[11px] font-mono"></div>
                        </label>
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Client ID</span>
                            <input type="text" id="sClientId" class="glass-input w-full font-mono">
                        </label>
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Client Secret</span>
                            <input type="password" id="sClientSecret" placeholder="(deixe vazio pra preservar o atual)" class="glass-input w-full font-mono">
                            <p class="text-[10px] text-slate-500 mt-1" id="secretStatus">—</p>
                        </label>
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Scopes</span>
                            <input type="text" id="sScopes" value="openid email profile" class="glass-input w-full font-mono">
                            <p class="text-[10px] text-slate-500 mt-1">Sempre inclua <code>openid</code> e <code>email</code>.</p>
                        </label>
                    </div>

                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Domínios de email permitidos (CSV)</span>
                        <input type="text" id="sDomains" placeholder="empresa.com,contractor.com" class="glass-input w-full font-mono">
                        <p class="text-[10px] text-slate-500 mt-1">Vazio = qualquer domínio (não recomendado em IdP público como Google).</p>
                    </label>

                    <div class="border-t border-slate-900/10 dark:border-white/5 pt-4">
                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                            <input type="checkbox" id="sAutoCreate" class="w-4 h-4">
                            <span class="text-[11px] font-black uppercase tracking-widest">Auto-criar usuários no primeiro login</span>
                        </label>
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Role default (auto-create)</span>
                            <select id="sDefaultRole" class="glass-input w-48">
                                <option value="viewer">Viewer</option>
                                <option value="operator">Operator</option>
                                <option value="readonly_admin">Read-only Admin</option>
                                <option value="admin">Admin (perigoso)</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-wrap gap-2 justify-end">
                    <a href="/api/v1/auth/oidc/login" id="btnTest" target="_blank" class="glass-btn !bg-sky-600 !text-white text-[10px] uppercase font-black">Testar login SSO</a>
                    <button type="button" id="btnSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<?php include 'includes/custom_modals.php'; ?>

<script>
(function() {
    const jwtMeta = document.querySelector('meta[name="api-jwt"]');
    const JWT = jwtMeta ? jwtMeta.content : '';
    const H = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};
    const HJ = { ...H, 'Content-Type': 'application/json' };
    const $ = (id) => document.getElementById(id);

    // Mostra callback URL pro IdP
    $('callbackUrl').textContent = `${window.location.protocol}//${window.location.host}/api/v1/auth/oidc/callback`;

    async function load() {
        const r = await fetch('/api/v1/auth/oidc/config', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        $('sEnabled').checked = !!d.enabled;
        $('sIssuer').value = d.issuer_url || '';
        $('sClientId').value = d.client_id || '';
        $('sScopes').value = d.scopes || 'openid email profile';
        $('sDomains').value = d.allowed_email_domains || '';
        $('sAutoCreate').checked = !!d.auto_create_users;
        $('sDefaultRole').value = d.default_role || 'viewer';
        if (d.has_secret) {
            $('secretStatus').textContent = d.secret_encrypted
                ? '🔐 Secret cifrado (vazio no input = preserva).'
                : '🔓 Secret em texto plano (vazio = preserva). Re-salve pra cifrar.';
        } else {
            $('secretStatus').textContent = '⚠ Nenhum secret cadastrado.';
        }
    }

    async function loadSecretsBanner() {
        const r = await fetch('/api/v1/admin/secrets-store/status', { headers: H });
        if (!r.ok) return;
        const s = await r.json();
        const banner = $('secretsBanner');
        if (s.available) {
            banner.className = 'glass-panel border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/5 mb-6 p-4 text-xs';
            banner.innerHTML = `<p class="text-emerald-700 dark:text-emerald-300 font-black uppercase tracking-widest text-[10px] mb-1">🔐 Secrets store ativo</p>
                <p class="text-slate-600 dark:text-slate-400">Algoritmo: <code>${s.algorithm}</code>. Source: <code>${s.key_source}</code>.</p>`;
        } else {
            banner.className = 'glass-panel border-amber-200 dark:border-amber-500/30 bg-amber-50/40 dark:bg-amber-500/5 mb-6 p-4 text-xs';
            banner.innerHTML = `<p class="text-amber-700 dark:text-amber-300 font-black uppercase tracking-widest text-[10px] mb-1">⚠ Secrets store inativo</p>
                <p class="text-slate-600 dark:text-slate-400"><code>SECRETS_MASTER_KEY</code> não está configurada — client_secret será gravado em texto plano. Gere com <code>python -c 'from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())'</code> e adicione no <code>/etc/unbound-dashboard/api-v1.env</code>.</p>`;
        }
        banner.classList.remove('hidden');
    }

    // ============ Provider presets ============
    const PRESETS = {
        google: {
            issuer: 'https://accounts.google.com',
            scopes: 'openid email profile',
            hint: 'Registre em <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-sky-500 underline">Google Cloud Console → APIs & Services → Credentials</a> como "OAuth 2.0 Client ID" tipo Web. Adicione o callback URL acima nos "Authorized redirect URIs". Para restringir ao seu domínio Workspace, use o campo "Domínios de email permitidos".',
        },
        entra: {
            issuer: 'https://login.microsoftonline.com/{TENANT_ID}/v2.0',
            scopes: 'openid email profile',
            hint: 'Registre em <a href="https://entra.microsoft.com/" target="_blank" class="text-sky-500 underline">Microsoft Entra admin center → App registrations → New registration</a>. Substitua <code>{TENANT_ID}</code> pelo seu Directory (tenant) ID (use <code>common</code> pra multi-tenant). Adicione o callback URL acima como Web redirect URI.',
        },
        okta: {
            issuer: 'https://{YOUR_DOMAIN}.okta.com',
            scopes: 'openid email profile',
            hint: 'Substitua <code>{YOUR_DOMAIN}</code> pelo seu domínio Okta (ex: <code>dev-123456</code>). Em Okta admin → Applications → Create App Integration → OIDC → Web Application. Adicione o callback URL nos "Sign-in redirect URIs".',
        },
        auth0: {
            issuer: 'https://{YOUR_TENANT}.auth0.com',
            scopes: 'openid email profile',
            hint: 'Substitua <code>{YOUR_TENANT}</code> pelo seu tenant Auth0 (ex: <code>dev-abc123</code>). Em Auth0 dashboard → Applications → Create Application → Regular Web Application. Adicione o callback URL em "Allowed Callback URLs".',
        },
        keycloak: {
            issuer: 'https://{HOST}/realms/{REALM}',
            scopes: 'openid email profile',
            hint: 'Substitua <code>{HOST}</code> pelo hostname do seu Keycloak e <code>{REALM}</code> pelo nome do realm. Em Keycloak admin → Clients → Create → Client type=OpenID Connect → Client authentication=ON. Adicione o callback URL em "Valid redirect URIs".',
        },
        authentik: {
            issuer: 'https://{HOST}/application/o/{APP_SLUG}/',
            scopes: 'openid email profile',
            hint: 'Substitua <code>{HOST}</code> pelo hostname do Authentik e <code>{APP_SLUG}</code> pelo slug do provider OAuth2/OIDC. Em Authentik → Applications → Providers → Create → OAuth2/OpenID Provider. Use "Authorization Code" como grant type.',
        },
        zitadel: {
            issuer: 'https://{INSTANCE}.zitadel.cloud',
            scopes: 'openid email profile',
            hint: 'Substitua <code>{INSTANCE}</code> pelo seu instance ID Zitadel (ex: <code>acme-abc123</code>). Em Zitadel console → Projects → New Application → Web → Code grant. Adicione o callback URL em "Redirect URIs".',
        },
        custom: {
            issuer: '',
            scopes: 'openid email profile',
            hint: 'Configure manualmente. Use o botão "Testar conectividade" pra validar o issuer URL via <code>.well-known/openid-configuration</code>.',
        },
    };

    $('btnApplyPreset').addEventListener('click', () => {
        const k = $('sPreset').value;
        const p = PRESETS[k];
        if (!p) { $('presetHint').innerHTML = '<span class="text-amber-500">Escolha um provider primeiro.</span>'; return; }
        $('sIssuer').value = p.issuer;
        $('sScopes').value = p.scopes;
        $('presetHint').innerHTML = p.hint;
    });

    // ============ Probe (auto-discovery) ============
    $('btnProbe').addEventListener('click', async () => {
        const issuer = $('sIssuer').value.trim().replace(/\/+$/, '');
        const box = $('probeResult');
        if (!issuer) {
            box.className = 'mt-2 p-3 rounded-lg text-[11px] font-mono bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30';
            box.classList.remove('hidden');
            box.textContent = 'Preencha o Issuer URL primeiro.';
            return;
        }
        const btn = $('btnProbe');
        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = 'Probing...';
        box.className = 'mt-2 p-3 rounded-lg text-[11px] font-mono bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20';
        box.classList.remove('hidden');
        box.textContent = 'Fetch ' + issuer + '/.well-known/openid-configuration ...';
        try {
            const r = await fetch('/api/v1/auth/oidc/probe', {
                method: 'POST', headers: HJ, body: JSON.stringify({ issuer_url: issuer }),
            });
            const d = await r.json();
            renderProbeResult(d, r.ok);
        } catch (err) {
            box.className = 'mt-2 p-3 rounded-lg text-[11px] font-mono bg-red-500/15 text-red-700 dark:text-red-300 border border-red-500/30';
            box.textContent = 'Falha: ' + err.message;
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    });

    function renderProbeResult(d, httpOk) {
        const box = $('probeResult');
        if (!httpOk || !d.ok) {
            box.className = 'mt-2 p-3 rounded-lg text-[11px] font-mono bg-red-500/15 text-red-700 dark:text-red-300 border border-red-500/30';
            box.innerHTML = `<b>✗ Falhou.</b> ${escapeHtml(d.error || d.detail || 'erro desconhecido')}` +
                (d.http_status ? `<br><span class="text-[10px]">HTTP ${d.http_status}</span>` : '') +
                (d.discovery_url ? `<br><span class="text-[10px] opacity-70">${escapeHtml(d.discovery_url)}</span>` : '');
            return;
        }
        box.className = 'mt-2 p-3 rounded-lg text-[11px] font-mono bg-emerald-500/10 text-slate-700 dark:text-slate-300 border border-emerald-500/30';
        const issuerWarn = d.issuer_match === false
            ? `<div class="text-amber-600 dark:text-amber-400 mt-1"><b>⚠ Issuer divergente:</b> discovery diz <code>${escapeHtml(d.meta_issuer)}</code></div>`
            : '';
        const jwksLine = d.jwks_keys !== undefined
            ? `<div>jwks_uri: <span class="text-emerald-600 dark:text-emerald-400">${d.jwks_keys} chave(s)</span></div>`
            : (d.jwks_error ? `<div class="text-amber-600 dark:text-amber-400">jwks: ${escapeHtml(d.jwks_error)}</div>` : '');
        const scopesLine = Array.isArray(d.scopes_supported) ? d.scopes_supported.slice(0, 10).join(' ') + (d.scopes_supported.length > 10 ? ' …' : '') : '(não listado)';
        const algsLine = Array.isArray(d.id_token_signing_alg_values_supported) ? d.id_token_signing_alg_values_supported.join(', ') : '(não listado)';
        box.innerHTML = `
            <div><b class="text-emerald-600 dark:text-emerald-400">✓ Discovery OK</b></div>
            ${issuerWarn}
            <div class="mt-2 space-y-0.5">
                <div>authorization: <span class="opacity-80">${escapeHtml(d.authorization_endpoint || '—')}</span></div>
                <div>token: <span class="opacity-80">${escapeHtml(d.token_endpoint || '—')}</span></div>
                <div>userinfo: <span class="opacity-80">${escapeHtml(d.userinfo_endpoint || '—')}</span></div>
                ${jwksLine}
                <div class="text-[10px] mt-1 opacity-70">scopes_supported: ${escapeHtml(scopesLine)}</div>
                <div class="text-[10px] opacity-70">id_token_alg: ${escapeHtml(algsLine)}</div>
            </div>`;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    $('btnSave').addEventListener('click', async () => {
        const body = {
            enabled: $('sEnabled').checked,
            issuer_url: $('sIssuer').value.trim(),
            client_id: $('sClientId').value.trim(),
            scopes: $('sScopes').value.trim() || 'openid email profile',
            allowed_email_domains: $('sDomains').value.trim(),
            auto_create_users: $('sAutoCreate').checked,
            default_role: $('sDefaultRole').value,
        };
        const secret = $('sClientSecret').value;
        if (secret) body.client_secret = secret;

        const r = await fetch('/api/v1/auth/oidc/config', {
            method: 'PUT', headers: HJ, body: JSON.stringify(body),
        });
        const d = await r.json().catch(() => ({}));
        (window.customAlert || alert)(r.ok ? 'Salvo.' : `Erro: ${d.detail || r.statusText}`);
        if (r.ok) {
            $('sClientSecret').value = '';
            load();
        }
    });

    load();
    loadSecretsBanner();
})();
</script>

</body>
</html>
