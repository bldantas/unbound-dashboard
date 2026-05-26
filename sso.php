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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Issuer URL</span>
                            <input type="text" id="sIssuer" placeholder="https://accounts.google.com" class="glass-input w-full font-mono">
                            <p class="text-[10px] text-slate-500 mt-1">Base do <code>/.well-known/openid-configuration</code>.</p>
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
