<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'audit.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Auditoria & Compliance - Unbound DNS</title>
    <meta name="description" content="Trilha de auditoria de ações administrativas + LGPD report + updates históricos.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Auditoria & Compliance";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Auditoria & Compliance
                </h1>
                <p class="page-subtitle">Trilha imutável de ações administrativas + relatório LGPD + histórico de updates.</p>
            </header>

            <!-- Tabs -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-4">
                <div class="px-2 py-1 flex gap-1 border-b border-slate-900/10 dark:border-white/5">
                    <button type="button" data-tab="admin" class="tab-btn active glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Admin Audit</button>
                    <button type="button" data-tab="lgpd" class="tab-btn glass-btn text-[10px] uppercase font-black">LGPD Report</button>
                    <button type="button" data-tab="updates" class="tab-btn glass-btn text-[10px] uppercase font-black">Updates</button>
                </div>
            </div>

            <!-- Tab Admin Audit -->
            <div id="tabAdmin" class="tab-content">
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-4">
                    <div class="px-6 py-3 flex flex-wrap items-center gap-3 text-xs">
                        <label class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Categoria</span>
                            <select id="aCategory" class="glass-input">
                                <option value="">Todas</option>
                                <option value="auth">Auth</option>
                                <option value="config">Config</option>
                                <option value="blocklist">Blocklist</option>
                                <option value="user">User</option>
                                <option value="host">Host</option>
                                <option value="cert">Cert</option>
                                <option value="data_export">Data Export</option>
                                <option value="other">Other</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Action prefix</span>
                            <input type="text" id="aActionPrefix" placeholder="ex: login." class="glass-input w-40 font-mono">
                        </label>
                        <label class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Janela (h)</span>
                            <input type="number" id="aHours" value="168" min="1" max="8760" class="glass-input w-24 font-mono">
                        </label>
                        <button type="button" id="btnAFilter" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Filtrar</button>
                        <button type="button" id="btnAExport" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black ml-auto">Export CSV</button>
                        <button type="button" id="btnAExportPdf" class="glass-btn !bg-rose-600 !text-white text-[10px] uppercase font-black">Export PDF</button>
                    </div>
                </div>

                <div class="glass-panel border-slate-200 dark:border-white/5 mb-6 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Quando</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Ator</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">IP</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Categoria</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Ação</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Alvo</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Detalhes</th>
                                </tr>
                            </thead>
                            <tbody id="adminTbody" class="divide-y divide-slate-200 dark:divide-white/5">
                                <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500 italic">Carregando…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-3 border-t border-slate-900/10 dark:border-white/5 flex items-center justify-between text-xs">
                        <span id="adminPagerInfo" class="text-slate-500">—</span>
                        <div class="flex gap-2">
                            <button type="button" id="adminPrev" class="glass-btn text-[10px] uppercase font-black">‹ Anterior</button>
                            <button type="button" id="adminNext" class="glass-btn text-[10px] uppercase font-black">Próxima ›</button>
                        </div>
                    </div>
                </div>

                <!-- Retention -->
                <?php if ($isAdmin): ?>
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Retenção do Admin Audit</h3>
                        <p class="text-[10px] text-slate-500 mt-1">Worker <code>AuditPruner</code> apaga entries com mais de N dias. Default 1 ano. Mínimo 30, máximo 10 anos.</p>
                    </div>
                    <div class="p-6 flex flex-wrap items-end gap-4">
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Dias (30..3650)</span>
                            <input type="number" id="aRetDays" min="30" max="3650" class="glass-input w-32 font-mono">
                        </label>
                        <button type="button" id="aBtnRetSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                        <button type="button" id="aBtnRetPrune" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Rodar Prune Agora</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab LGPD -->
            <div id="tabLgpd" class="tab-content hidden">
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-4">
                    <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                        <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Relatório LGPD — Queries por IP cliente</h3>
                        <p class="text-[10px] text-slate-500 mt-1">Útil pra atender Art. 18 da LGPD ("quais dados meus você tem?"). Cada acesso é registrado no Admin Audit (categoria <code>data_export</code>).</p>
                    </div>
                    <div class="p-6 flex flex-wrap items-end gap-3">
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">IP cliente</span>
                            <input type="text" id="lIp" placeholder="192.168.1.10 ou 2804::1" class="glass-input w-56 font-mono">
                        </label>
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Janela (horas)</span>
                            <input type="number" id="lHours" value="24" min="1" max="720" class="glass-input w-24 font-mono">
                        </label>
                        <label class="flex flex-col">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Limite</span>
                            <input type="number" id="lLimit" value="5000" min="1" max="50000" class="glass-input w-28 font-mono">
                        </label>
                        <button type="button" id="btnLgpdRun" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Gerar JSON</button>
                        <button type="button" id="btnLgpdCsv" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">Download CSV</button>
                        <button type="button" id="btnLgpdPdf" class="glass-btn !bg-rose-600 !text-white text-[10px] uppercase font-black">Download PDF</button>
                    </div>
                </div>
                <div id="lgpdResult" class="glass-panel border-slate-200 dark:border-white/5 mb-6 hidden">
                    <div class="px-6 py-3 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                        <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200" id="lgpdResultTitle">—</h4>
                        <span id="lgpdResultMeta" class="text-[10px] text-slate-500">—</span>
                    </div>
                    <pre id="lgpdResultBody" class="p-4 text-[11px] font-mono overflow-x-auto max-h-[60vh] overflow-y-auto"></pre>
                </div>
            </div>

            <!-- Tab Updates -->
            <div id="tabUpdates" class="tab-content hidden">
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-6 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400">
                                <tr>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Quando</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">User</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Tipo</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">De → Pra</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Status</th>
                                    <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Duração</th>
                                </tr>
                            </thead>
                            <tbody id="updatesTbody" class="divide-y divide-slate-200 dark:divide-white/5">
                                <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500 italic">Carregando…</td></tr>
                            </tbody>
                        </table>
                    </div>
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
    const $ = (id) => document.getElementById(id);

    // === Tabs ===
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('!bg-cyan-600', '!text-white', 'active');
            });
            btn.classList.add('!bg-cyan-600', '!text-white', 'active');
            const tab = btn.dataset.tab;
            ['admin','lgpd','updates'].forEach(t => {
                $('tab' + t[0].toUpperCase() + t.slice(1)).classList.toggle('hidden', t !== tab);
            });
            if (tab === 'updates') loadUpdates();
            if (tab === 'admin') loadAdmin();
        });
    });

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function relTime(iso) {
        if (!iso) return '—';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return iso;
        const diff = Math.floor((Date.now() - t) / 1000);
        if (diff < 60) return diff + 's';
        if (diff < 3600) return Math.floor(diff/60) + 'min';
        if (diff < 86400) return Math.floor(diff/3600) + 'h';
        return Math.floor(diff/86400) + 'd';
    }
    function fmtIso(iso) { return (iso || '').replace('T', ' ').slice(0, 19); }

    // === Admin Audit ===
    let adminOffset = 0;
    let adminTotal = 0;
    const ADMIN_PAGE = 100;

    function buildAdminQuery(extra = {}) {
        const params = new URLSearchParams();
        const cat = $('aCategory').value;
        const ap = $('aActionPrefix').value.trim();
        const hours = parseInt($('aHours').value || '168', 10);
        if (cat) params.set('category', cat);
        if (ap) params.set('action_prefix', ap);
        if (hours > 0) params.set('from_ts', String(Math.floor(Date.now()/1000) - hours*3600));
        params.set('limit', String(ADMIN_PAGE));
        params.set('offset', String(adminOffset));
        for (const [k, v] of Object.entries(extra)) params.set(k, v);
        return params;
    }

    async function loadAdmin() {
        const r = await fetch('/api/v1/audit/admin/list?' + buildAdminQuery().toString(), { headers: H });
        if (!r.ok) {
            $('adminTbody').innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-red-500">Erro ${r.status}.</td></tr>`;
            return;
        }
        const d = await r.json();
        adminTotal = d.total || 0;
        const items = d.items || [];
        if (!items.length) {
            $('adminTbody').innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-slate-500 italic">Nenhuma entrada nos filtros.</td></tr>';
        } else {
            $('adminTbody').innerHTML = items.map(it => {
                const det = it.details ? `<details><summary class="cursor-pointer text-slate-500 text-[10px]">ver</summary><pre class="text-[10px] font-mono mt-1 whitespace-pre-wrap">${esc(JSON.stringify(it.details, null, 2))}</pre></details>` : '—';
                const tgt = (it.target_type || it.target_id) ? `${esc(it.target_type || '')}${it.target_id ? ' / ' + esc(it.target_id) : ''}` : '—';
                return `<tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                    <td class="px-3 py-2 font-mono text-[10px] text-slate-500" title="${esc(it.created_at)}">${relTime(it.created_at)}</td>
                    <td class="px-3 py-2">${esc(it.actor_username)}</td>
                    <td class="px-3 py-2 font-mono text-[10px]">${esc(it.actor_ip || '—')}</td>
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest bg-indigo-500/20 text-indigo-500">${esc(it.category)}</span></td>
                    <td class="px-3 py-2 font-mono">${esc(it.action)}</td>
                    <td class="px-3 py-2 font-mono text-[10px]">${tgt}</td>
                    <td class="px-3 py-2">${det}</td>
                </tr>`;
            }).join('');
        }
        $('adminPagerInfo').textContent = `${adminOffset+1}–${Math.min(adminOffset+items.length, adminTotal)} de ${adminTotal}`;
        $('adminPrev').disabled = adminOffset === 0;
        $('adminNext').disabled = adminOffset + ADMIN_PAGE >= adminTotal;
    }

    $('btnAFilter').addEventListener('click', () => { adminOffset = 0; loadAdmin(); });
    $('adminPrev').addEventListener('click', () => { if (adminOffset >= ADMIN_PAGE) { adminOffset -= ADMIN_PAGE; loadAdmin(); } });
    $('adminNext').addEventListener('click', () => { if (adminOffset + ADMIN_PAGE < adminTotal) { adminOffset += ADMIN_PAGE; loadAdmin(); } });
    async function downloadAdmin(endpoint, ext) {
        const url = endpoint + '?' + buildAdminQuery({limit: '10000', offset: '0'}).toString();
        const r = await fetch(url, { headers: H });
        if (!r.ok) { (window.customAlert || alert)('Erro ' + r.status); return; }
        const blob = await r.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'admin_audit.' + ext;
        a.click();
        URL.revokeObjectURL(a.href);
    }
    $('btnAExport').addEventListener('click', () => downloadAdmin('/api/v1/audit/admin/export-csv', 'csv'));
    $('btnAExportPdf').addEventListener('click', () => downloadAdmin('/api/v1/audit/admin/export-pdf', 'pdf'));

    // Retention
    if (IS_ADMIN && $('aRetDays')) {
        (async () => {
            const r = await fetch('/api/v1/audit/admin/retention/settings', { headers: H });
            if (r.ok) { const d = await r.json(); $('aRetDays').value = d.days || 365; }
        })();
        $('aBtnRetSave').addEventListener('click', async () => {
            const days = parseInt($('aRetDays').value || '365', 10);
            const r = await fetch('/api/v1/audit/admin/retention/settings', { method: 'PUT', headers: HJ, body: JSON.stringify({days}) });
            (window.customAlert || alert)(r.ok ? 'Salvo.' : 'Erro.');
        });
        $('aBtnRetPrune').addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Rodar prune agora? Entries antigas serão apagadas.') : Promise.resolve(confirm('Confirma?')));
            if (!ok) return;
            const r = await fetch('/api/v1/audit/admin/prune-now', { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            (window.customAlert || alert)(r.ok ? `${d.pruned} apagadas (retenção: ${d.days}d).` : 'Erro.');
            loadAdmin();
        });
    }

    // === LGPD Report ===
    function lgpdQuery() {
        const ip = $('lIp').value.trim();
        const hours = parseInt($('lHours').value || '24', 10);
        const limit = parseInt($('lLimit').value || '5000', 10);
        if (!ip) { (window.customAlert || alert)('Informe o IP cliente.'); return null; }
        return { ip, hours, limit, qs: new URLSearchParams({client_ip: ip, hours: String(hours), limit: String(limit)}).toString() };
    }
    $('btnLgpdRun').addEventListener('click', async () => {
        const q = lgpdQuery(); if (!q) return;
        const r = await fetch('/api/v1/compliance/lgpd-report?' + q.qs, { headers: H });
        if (!r.ok) { (window.customAlert || alert)('Erro ' + r.status); return; }
        const d = await r.json();
        $('lgpdResult').classList.remove('hidden');
        $('lgpdResultTitle').textContent = `Queries de ${d.client_ip} nas últimas ${d.hours}h`;
        $('lgpdResultMeta').textContent = `${d.total} resultados${d.truncated ? ' (truncado pelo limite)' : ''}`;
        $('lgpdResultBody').textContent = JSON.stringify(d.items, null, 2);
    });
    async function downloadLgpd(endpoint, ext) {
        const q = lgpdQuery(); if (!q) return;
        const r = await fetch(endpoint + '?' + q.qs, { headers: H });
        if (!r.ok) { (window.customAlert || alert)('Erro ' + r.status); return; }
        const blob = await r.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `lgpd_${q.ip.replace(/:/g, '_')}_${q.hours}h.${ext}`;
        a.click();
        URL.revokeObjectURL(a.href);
    }
    $('btnLgpdCsv').addEventListener('click', () => downloadLgpd('/api/v1/compliance/lgpd-report.csv', 'csv'));
    $('btnLgpdPdf').addEventListener('click', () => downloadLgpd('/api/v1/compliance/lgpd-report.pdf', 'pdf'));

    // === Updates ===
    async function loadUpdates() {
        const r = await fetch('/api/v1/audit/updates?limit=100', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const items = d.audit || [];
        if (!items.length) {
            $('updatesTbody').innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center text-slate-500 italic">Nenhum update aplicado ainda.</td></tr>';
            return;
        }
        const STATUS_CLS = {
            succeeded: 'text-emerald-500', failed: 'text-red-500',
            rolled_back: 'text-amber-500', rollback_failed: 'text-red-500',
            running: 'text-blue-500',
        };
        $('updatesTbody').innerHTML = items.map(it => {
            const startedIso = new Date((it.started_at || 0) * 1000).toISOString();
            const dur = it.duration_seconds != null ? `${it.duration_seconds}s` : '—';
            const cls = STATUS_CLS[it.status] || '';
            return `<tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                <td class="px-3 py-2 font-mono text-[10px] text-slate-500">${relTime(startedIso)}</td>
                <td class="px-3 py-2">${esc(it.username || '—')}</td>
                <td class="px-3 py-2"><span class="text-[10px] font-black uppercase tracking-widest">${esc(it.kind)}</span></td>
                <td class="px-3 py-2 font-mono text-[10px]">${esc(it.from_version || '?')} → ${esc(it.to_version || '?')}</td>
                <td class="px-3 py-2 font-black uppercase tracking-widest text-[10px] ${cls}">${esc(it.status)}</td>
                <td class="px-3 py-2 font-mono">${dur}</td>
            </tr>`;
        }).join('');
    }

    loadAdmin();
})();
</script>

</body>
</html>
