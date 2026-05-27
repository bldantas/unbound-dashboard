<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'approvals.php';
$isAdmin = Auth::isAdmin();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Aprovações - Unbound DNS</title>
    <meta name="description" content="Workflow 2nd-approver pra ações sensíveis de configuração.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('approvals.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= t('approvals.title') ?> (2nd-Approver)
                </h1>
                <p class="page-subtitle"><?= t('approvals.subtitle') ?></p>
            </header>

            <?php if ($isAdmin): ?>
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('approvals.section_config') ?></h3>
                    <p class="text-[10px] text-slate-500 mt-1">Toggle global + CSV de actions que exigem aprovação (ex: <code>dns_security.apply,doh_inbound.gen_cert,ha.failover</code>). NÃO há wire automático nesta versão — operador habilita e re-executa manualmente após aprovação.</p>
                </div>
                <div class="p-6 space-y-3 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="cEnabled" class="w-4 h-4">
                        <span class="text-[11px] font-black uppercase tracking-widest">Workflow habilitado</span>
                    </label>
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Actions (CSV)</span>
                        <input type="text" id="cActions" placeholder="dns_security.apply,doh_inbound.gen_cert,ha.failover" class="glass-input w-full font-mono">
                    </label>
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">TTL do pending (horas, 1..168)</span>
                        <input type="number" id="cTtl" min="1" max="168" class="glass-input w-32 font-mono">
                    </label>
                    <div class="flex justify-end">
                        <button type="button" id="btnCfgSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6 overflow-hidden">
                <div class="px-6 py-3 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('approvals.section_requests') ?> <span id="pendingCount" class="text-amber-500"></span></h3>
                    <div class="flex gap-2">
                        <button type="button" data-tab="pending" class="tab-btn active glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Pending</button>
                        <button type="button" data-tab="all" class="tab-btn glass-btn text-[10px] uppercase font-black">Histórico</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Quando</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Requester</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Ação</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Descrição</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Status</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Expira</th>
                                <th class="px-3 py-2 text-right font-black uppercase tracking-widest text-[10px]">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody" class="divide-y divide-slate-200 dark:divide-white/5">
                            <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500 italic">Carregando…</td></tr>
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
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const ME_ID = <?= $currentUserId ?>;
    const $ = (id) => document.getElementById(id);
    let currentTab = 'pending';

    const STATUS_CLS = {
        pending: 'text-amber-500',
        approved: 'text-emerald-500',
        rejected: 'text-red-500',
        executed: 'text-blue-500',
        expired: 'text-slate-500',
    };

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function relTime(iso) {
        if (!iso) return '—';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return iso;
        const diff = Math.floor((Date.now() - t) / 1000);
        const past = diff >= 0;
        const abs = Math.abs(diff);
        const fmt = (abs < 60) ? (abs + 's') : (abs < 3600) ? (Math.floor(abs/60) + 'min') : (abs < 86400) ? (Math.floor(abs/3600) + 'h') : (Math.floor(abs/86400) + 'd');
        return past ? `${fmt} atrás` : `em ${fmt}`;
    }

    async function load() {
        const ep = currentTab === 'pending' ? '/api/v1/approvals/pending' : '/api/v1/approvals/list';
        const r = await fetch(ep, { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const items = d.items || [];
        if (currentTab === 'pending') {
            $('pendingCount').textContent = items.length > 0 ? `(${items.length} pending)` : '';
        } else {
            $('pendingCount').textContent = '';
        }
        if (!items.length) {
            $('tbody').innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-slate-500 italic">Nenhum request ${currentTab === 'pending' ? 'pendente' : ''}.</td></tr>`;
            return;
        }
        $('tbody').innerHTML = items.map(it => {
            const stCls = STATUS_CLS[it.status] || '';
            const isOwn = it.requester_id === ME_ID;
            const canApprove = IS_ADMIN && it.status === 'pending' && !isOwn;
            const canExecute = IS_ADMIN && it.status === 'approved' && HANDLERS.has(it.action);
            let actions = '';
            if (canApprove) {
                actions = `<button data-id="${it.id}" class="approveBtn glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">Aprovar</button>
                           <button data-id="${it.id}" class="rejectBtn glass-btn !bg-red-600 !text-white text-[10px] uppercase font-black">Rejeitar</button>`;
            } else if (canExecute) {
                actions = `<button data-id="${it.id}" class="execBtn glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black" title="Replay automático via handler registrado">Executar</button>`;
            } else if (isOwn && it.status === 'pending') {
                actions = '<span class="text-[10px] text-slate-500 italic">(seu pedido)</span>';
            } else if (it.status === 'approved' && !HANDLERS.has(it.action)) {
                actions = '<span class="text-[10px] text-amber-500 italic">execute manual</span>';
            }
            return `<tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                <td class="px-3 py-2 font-mono text-[10px] text-slate-500" title="${esc(it.created_at)}">${relTime(it.created_at)}</td>
                <td class="px-3 py-2">${esc(it.requester_username)} <span class="text-[10px] text-slate-500">(${esc(it.requester_ip || '—')})</span></td>
                <td class="px-3 py-2 font-mono">${esc(it.action)}</td>
                <td class="px-3 py-2 text-slate-700 dark:text-slate-300 max-w-xs">${esc(it.description) || '—'}</td>
                <td class="px-3 py-2 font-black uppercase tracking-widest text-[10px] ${stCls}">${esc(it.status)}</td>
                <td class="px-3 py-2 font-mono text-[10px]">${relTime(it.expires_at)}</td>
                <td class="px-3 py-2 text-right">${actions}</td>
            </tr>`;
        }).join('');
        document.querySelectorAll('.approveBtn').forEach(b => b.addEventListener('click', () => act(b.dataset.id, 'approve')));
        document.querySelectorAll('.rejectBtn').forEach(b => b.addEventListener('click', () => act(b.dataset.id, 'reject')));
        document.querySelectorAll('.execBtn').forEach(b => b.addEventListener('click', () => execNow(b.dataset.id)));
    }

    let HANDLERS = new Set();
    async function loadHandlers() {
        if (!IS_ADMIN) return;
        const r = await fetch('/api/v1/approvals/handlers', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        HANDLERS = new Set(d.actions || []);
    }

    async function execNow(id) {
        const ok = await (window.customConfirm ? customConfirm(`Executar request #${id} agora? O handler registrado replay-a a action automaticamente.`) : Promise.resolve(confirm('Confirma?')));
        if (!ok) return;
        const r = await fetch(`/api/v1/approvals/${id}/execute`, { method: 'POST', headers: HJ, body: '{}' });
        const d = await r.json().catch(() => ({}));
        if (r.ok && d.ok) {
            (window.customAlert || alert)(`Executado #${id}.`);
        } else {
            (window.customAlert || alert)(`Falha: ${d.detail?.error || d.error || d.detail || r.statusText}`);
        }
        load();
    }

    async function act(id, kind) {
        let body = {};
        if (kind === 'reject') {
            const reason = window.prompt('Motivo da rejeição (opcional):') || '';
            body = { reason };
        } else {
            const ok = await (window.customConfirm ? customConfirm(`Aprovar request #${id}? O requester continua sendo quem executa a ação.`) : Promise.resolve(confirm('Confirma?')));
            if (!ok) return;
        }
        const r = await fetch(`/api/v1/approvals/${id}/${kind}`, { method: 'POST', headers: HJ, body: JSON.stringify(body) });
        const d = await r.json().catch(() => ({}));
        (window.customAlert || alert)(r.ok ? `${kind === 'approve' ? 'Aprovado' : 'Rejeitado'} #${id}.` : `Falha: ${d.detail?.error || d.detail || r.statusText}`);
        load();
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('!bg-cyan-600', '!text-white', 'active'));
            btn.classList.add('!bg-cyan-600', '!text-white', 'active');
            currentTab = btn.dataset.tab;
            load();
        });
    });

    if (IS_ADMIN) {
        (async () => {
            const r = await fetch('/api/v1/approvals/config', { headers: H });
            if (r.ok) {
                const d = await r.json();
                $('cEnabled').checked = !!d.enabled;
                $('cActions').value = d.actions || '';
                $('cTtl').value = d.ttl_hours || 24;
            }
        })();
        $('btnCfgSave').addEventListener('click', async () => {
            const body = {
                enabled: $('cEnabled').checked,
                actions: $('cActions').value.trim(),
                ttl_hours: parseInt($('cTtl').value || '24', 10),
            };
            const r = await fetch('/api/v1/approvals/config', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            (window.customAlert || alert)(r.ok ? 'Salvo.' : 'Erro.');
        });
    }

    loadHandlers().then(load);
    setInterval(load, 30000);
})();
</script>

</body>
</html>
