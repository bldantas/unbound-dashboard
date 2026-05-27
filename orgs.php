<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

if (!Auth::isAdmin()) {
    header('Location: index.php?error=admin_only');
    exit;
}

$currentPage = 'orgs.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Organizações - Unbound DNS</title>
    <meta name="description" content="Multi-tenant: CRUD de organizações + atribuição de usuários.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Organizações";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Organizações
                </h1>
                <p class="page-subtitle">Multi-tenant infrastructure (v2.80). Esta release entrega CRUD de orgs + assign de usuários. Particionamento de dados (audit, hosts, etc) por org fica como TODO da próxima iteração.</p>
            </header>

            <div class="glass-panel border-amber-200 dark:border-amber-500/30 bg-amber-50/40 dark:bg-amber-500/5 mb-6 p-4 text-xs">
                <p class="text-amber-700 dark:text-amber-300 font-black uppercase tracking-widest text-[10px] mb-1">⚠ Limitações conhecidas</p>
                <ul class="text-slate-600 dark:text-slate-400 list-disc list-inside space-y-0.5">
                    <li>Listings de hosts/audit/blocklists ainda são globais — não filtram por org_id.</li>
                    <li>Usuários sem org (org_id NULL) seguem sendo "system admins" — comportamento atual preservado.</li>
                    <li>RBAC per-org (admin gerencia só sua org) ainda não — admin global pode tudo.</li>
                </ul>
            </div>

            <!-- Tabela de orgs -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6 overflow-hidden">
                <div class="px-6 py-3 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Organizações <span id="orgsCount" class="text-slate-500"></span></h3>
                    <button type="button" id="btnAdd" class="glass-btn !bg-pink-600 !text-white text-[10px] uppercase font-black">+ Adicionar</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Name</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Slug</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Descrição</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Status</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Users</th>
                                <th class="px-3 py-2 text-right font-black uppercase tracking-widest text-[10px]">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody" class="divide-y divide-slate-200 dark:divide-white/5">
                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500 italic">Carregando…</td></tr>
                        </tbody>
                    </table>
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

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    async function load() {
        const r = await fetch('/api/v1/organizations/', { headers: H });
        if (!r.ok) {
            $('tbody').innerHTML = `<tr><td colspan="6" class="px-3 py-6 text-center text-red-500">Erro ${r.status}.</td></tr>`;
            return;
        }
        const d = await r.json();
        const items = d.items || [];
        $('orgsCount').textContent = items.length ? `(${items.length})` : '';
        if (!items.length) {
            $('tbody').innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center text-slate-500 italic">Nenhuma org cadastrada. Clique <strong>+ Adicionar</strong>.</td></tr>';
            return;
        }
        $('tbody').innerHTML = items.map(it => {
            return `<tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                <td class="px-3 py-2"><strong>${esc(it.name)}</strong></td>
                <td class="px-3 py-2 font-mono text-[10px]">${esc(it.slug)}</td>
                <td class="px-3 py-2 text-slate-700 dark:text-slate-300 max-w-md truncate">${esc(it.description) || '—'}</td>
                <td class="px-3 py-2 font-black uppercase tracking-widest text-[10px] ${it.is_active ? 'text-emerald-500' : 'text-slate-500'}">${it.is_active ? 'ativo' : 'inativo'}</td>
                <td class="px-3 py-2 font-mono tabular-nums">${it.user_count}</td>
                <td class="px-3 py-2 text-right">
                    <button data-id="${it.id}" data-active="${it.is_active ? '1' : '0'}" class="toggleBtn glass-btn text-[10px] uppercase font-black">${it.is_active ? 'Desativar' : 'Ativar'}</button>
                    <button data-id="${it.id}" class="delBtn glass-btn !bg-red-600 !text-white text-[10px] uppercase font-black">Excluir</button>
                </td>
            </tr>`;
        }).join('');
        document.querySelectorAll('.toggleBtn').forEach(b => b.addEventListener('click', () => toggle(b.dataset.id, b.dataset.active !== '1')));
        document.querySelectorAll('.delBtn').forEach(b => b.addEventListener('click', () => delOrg(b.dataset.id)));
    }

    async function toggle(id, makeActive) {
        const r = await fetch(`/api/v1/organizations/${id}`, { method: 'PUT', headers: HJ, body: JSON.stringify({is_active: makeActive}) });
        (window.customAlert || alert)(r.ok ? (makeActive ? 'Ativada.' : 'Desativada.') : t('js.error_generic'));
        load();
    }

    async function delOrg(id) {
        const ok = await (window.customConfirm ? customConfirm('Excluir esta org? Bloqueia se houver usuários vinculados.') : Promise.resolve(confirm('Excluir?')));
        if (!ok) return;
        const r = await fetch(`/api/v1/organizations/${id}`, { method: 'DELETE', headers: H });
        const d = await r.json().catch(() => ({}));
        if (r.ok || r.status === 204) load();
        else (window.customAlert || alert)(`Falha: ${d.detail?.error || d.detail || r.statusText}`);
    }

    $('btnAdd').addEventListener('click', async () => {
        const name = window.prompt('Nome da organização:');
        if (!name) return;
        const slug = window.prompt('Slug (a-z 0-9 hífen, max 80, ex: empresa-acme):', name.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 80));
        if (!slug) return;
        const description = window.prompt('Descrição (opcional):') || '';
        const r = await fetch('/api/v1/organizations/', { method: 'POST', headers: HJ, body: JSON.stringify({name, slug, description}) });
        const d = await r.json().catch(() => ({}));
        if (r.ok || r.status === 201) {
            (window.customAlert || alert)(`Org "${d.name}" criada.`);
            load();
        } else {
            (window.customAlert || alert)(`Falha: ${d.detail || r.statusText}`);
        }
    });

    load();
})();
</script>

</body>
</html>
