<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'notifications.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Notificações - Unbound DNS</title>
    <meta name="description" content="Feed completo de notificações (alertas + anomalias) com filtros, marcar como lida e retenção.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Notificações";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Notificações
                </h1>
                <p class="page-subtitle">Feed completo de alertas e anomalias. Push em tempo real via WebSocket; fallback polling a cada 60s.</p>
            </header>

            <!-- KPIs -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total</p>
                    <p id="kpiTotal" class="text-3xl font-black text-slate-800 dark:text-slate-100 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Ativos não-lidos</p>
                    <p id="kpiActiveUnread" class="text-3xl font-black text-blue-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Critical / 7d</p>
                    <p id="kpiCritical" class="text-3xl font-black text-red-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">WebSocket</p>
                    <p id="kpiWs" class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-2">—</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-4">
                <div class="px-6 py-3 flex flex-wrap items-center gap-3 text-xs">
                    <label class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Severidade</span>
                        <select id="fSeverity" class="glass-input">
                            <option value="">Todas</option>
                            <option value="critical">Critical</option>
                            <option value="warning">Warning</option>
                            <option value="info">Info</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Categoria</span>
                        <select id="fCategory" class="glass-input">
                            <option value="">Todas</option>
                            <option value="alert">Alertas</option>
                            <option value="anomaly_">Anomalias</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Resolvidos</span>
                        <select id="fResolved" class="glass-input">
                            <option value="">Todos</option>
                            <option value="false">Apenas ativos</option>
                            <option value="true">Apenas resolvidos</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Lidos</span>
                        <select id="fDismissed" class="glass-input">
                            <option value="">Todos</option>
                            <option value="false">Não lidos</option>
                            <option value="true">Lidos</option>
                        </select>
                    </label>
                    <button type="button" id="btnFilter" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Filtrar</button>
                    <?php if ($isAdmin): ?>
                    <button type="button" id="btnDismissAll" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black ml-auto">Marcar Todas Lidas</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabela -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Sev</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Tipo</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Mensagem</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Início</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Status</th>
                                <th class="px-3 py-2 text-right font-black uppercase tracking-widest text-[10px]">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody" class="divide-y divide-slate-200 dark:divide-white/5">
                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500 italic">Carregando…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="pager" class="px-6 py-3 border-t border-slate-900/10 dark:border-white/5 flex items-center justify-between text-xs">
                    <span id="pagerInfo" class="text-slate-500">—</span>
                    <div class="flex gap-2">
                        <button type="button" id="btnPrev" class="glass-btn text-[10px] uppercase font-black">‹ Anterior</button>
                        <button type="button" id="btnNext" class="glass-btn text-[10px] uppercase font-black">Próxima ›</button>
                    </div>
                </div>
            </div>

            <!-- Retention -->
            <?php if ($isAdmin): ?>
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Retenção</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Worker <code>NotificationPruner</code> apaga 1x/dia notificações resolvidas ou lidas há mais de N dias. Ativos não-lidos nunca são apagados.</p>
                </div>
                <div class="p-6 flex flex-wrap items-end gap-4">
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Dias de retenção (1..365)</span>
                        <input type="number" id="retDays" min="1" max="365" class="glass-input w-32 font-mono">
                    </label>
                    <button type="button" id="btnRetSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    <button type="button" id="btnRetPrune" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black">Rodar Agora</button>
                    <span id="retInfo" class="text-[10px] text-slate-500 italic ml-auto">—</span>
                </div>
            </div>
            <?php endif; ?>

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
    const PAGE_SIZE = 50;
    const SEV_COLORS = {
        'critical': 'bg-red-500/20 text-red-500 border-red-500/30',
        'warning':  'bg-amber-500/20 text-amber-500 border-amber-500/30',
        'info':     'bg-blue-500/20 text-blue-500 border-blue-500/30',
    };

    let offset = 0;
    let total = 0;
    let currentFilters = {};

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function relTime(iso) {
        if (!iso) return '—';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return '—';
        const diff = Math.floor((Date.now() - t) / 1000);
        if (diff < 60) return diff + 's';
        if (diff < 3600) return Math.floor(diff/60) + 'min';
        if (diff < 86400) return Math.floor(diff/3600) + 'h';
        return Math.floor(diff/86400) + 'd';
    }

    function buildQuery(extra = {}) {
        const f = {...currentFilters, ...extra, limit: PAGE_SIZE, offset};
        const qs = new URLSearchParams();
        for (const [k, v] of Object.entries(f)) {
            if (v !== '' && v !== null && v !== undefined) qs.set(k, v);
        }
        return qs.toString();
    }

    async function loadList() {
        const r = await fetch('/api/v1/notifications/list?' + buildQuery(), { headers: H });
        if (!r.ok) {
            $('tbody').innerHTML = `<tr><td colspan="6" class="px-3 py-6 text-center text-red-500">Erro ${r.status}.</td></tr>`;
            return;
        }
        const d = await r.json();
        total = d.total || 0;
        const items = d.items || [];
        if (!items.length) {
            $('tbody').innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center text-slate-500 italic">Nenhuma notificação corresponde aos filtros.</td></tr>';
        } else {
            $('tbody').innerHTML = items.map(it => {
                const sevCls = SEV_COLORS[(it.severity || 'info').toLowerCase()] || SEV_COLORS.info;
                const statusBadge = it.resolved_at
                    ? '<span class="text-emerald-500 text-[10px] font-black uppercase tracking-widest">resolvido</span>'
                    : (it.is_dismissed
                        ? '<span class="text-slate-500 text-[10px] font-black uppercase tracking-widest">lido</span>'
                        : '<span class="text-blue-500 text-[10px] font-black uppercase tracking-widest">● ativo</span>');
                const dismissBtn = (!it.is_dismissed && IS_ADMIN)
                    ? `<button data-id="${it.id}" class="dismissBtn glass-btn text-[10px] uppercase font-black">Marcar Lida</button>`
                    : '';
                return `<tr class="${it.is_dismissed ? 'opacity-60' : ''} hover:bg-slate-50 dark:hover:bg-white/5">
                    <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest border ${sevCls}">${esc(it.severity)}</span></td>
                    <td class="px-3 py-2 font-mono">${esc(it.type)}</td>
                    <td class="px-3 py-2 text-slate-700 dark:text-slate-300 max-w-md truncate" title="${esc(it.message)}">${esc(it.message) || '—'}</td>
                    <td class="px-3 py-2 font-mono text-[11px] text-slate-500" title="${esc(it.started_at)}">${relTime(it.started_at)}</td>
                    <td class="px-3 py-2">${statusBadge}</td>
                    <td class="px-3 py-2 text-right">
                        ${dismissBtn}
                        <a href="${esc(it.url)}" class="glass-btn text-[10px] uppercase font-black">Abrir</a>
                    </td>
                </tr>`;
            }).join('');
            document.querySelectorAll('.dismissBtn').forEach(btn => {
                btn.addEventListener('click', () => dismiss(btn.dataset.id));
            });
        }
        $('pagerInfo').textContent = `${offset+1}–${Math.min(offset+items.length, total)} de ${total}`;
        $('btnPrev').disabled = offset === 0;
        $('btnNext').disabled = offset + PAGE_SIZE >= total;
        await loadKpis();
    }

    async function loadKpis() {
        const totalR = await fetch('/api/v1/notifications/list?limit=1&offset=0', { headers: H });
        if (totalR.ok) $('kpiTotal').textContent = (await totalR.json()).total.toLocaleString('pt-BR');
        const activeR = await fetch('/api/v1/notifications/list?resolved=false&dismissed=false&limit=1&offset=0', { headers: H });
        if (activeR.ok) $('kpiActiveUnread').textContent = (await activeR.json()).total.toLocaleString('pt-BR');
        const critR = await fetch('/api/v1/notifications/list?severity=critical&limit=1&offset=0', { headers: H });
        if (critR.ok) $('kpiCritical').textContent = (await critR.json()).total.toLocaleString('pt-BR');
    }

    async function dismiss(id) {
        const r = await fetch(`/api/v1/notifications/${id}/dismiss`, { method: 'POST', headers: H });
        if (r.ok || r.status === 204) loadList();
        else (window.customAlert || alert)('Erro ao marcar como lida.');
    }

    function readFilters() {
        currentFilters = {
            severity: $('fSeverity').value,
            type_prefix: $('fCategory').value,
            resolved: $('fResolved').value,
            dismissed: $('fDismissed').value,
        };
    }

    $('btnFilter').addEventListener('click', () => { readFilters(); offset = 0; loadList(); });
    $('btnPrev').addEventListener('click', () => { if (offset >= PAGE_SIZE) { offset -= PAGE_SIZE; loadList(); } });
    $('btnNext').addEventListener('click', () => { if (offset + PAGE_SIZE < total) { offset += PAGE_SIZE; loadList(); } });

    if (IS_ADMIN) {
        $('btnDismissAll')?.addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Marcar TODAS as notificações como lidas?') : Promise.resolve(confirm('Confirma?')));
            if (!ok) return;
            const r = await fetch('/api/v1/notifications/dismiss-all', { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            (window.customAlert || alert)(r.ok ? `${d.dismissed} marcadas como lidas.` : 'Erro.');
            loadList();
        });
    }

    // Retention
    async function loadRetention() {
        const r = await fetch('/api/v1/notifications/retention/settings', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        if ($('retDays')) $('retDays').value = d.days || 30;
    }
    if (IS_ADMIN) {
        $('btnRetSave')?.addEventListener('click', async () => {
            const days = parseInt($('retDays').value || '30', 10);
            const r = await fetch('/api/v1/notifications/retention/settings', { method: 'PUT', headers: HJ, body: JSON.stringify({ days }) });
            (window.customAlert || alert)(r.ok ? 'Salvo.' : 'Erro.');
        });
        $('btnRetPrune')?.addEventListener('click', async () => {
            const ok = await (window.customConfirm ? customConfirm('Rodar prune agora? Notificações antigas (resolvidas/lidas) serão apagadas.') : Promise.resolve(confirm('Confirma?')));
            if (!ok) return;
            const r = await fetch('/api/v1/notifications/prune-now', { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            (window.customAlert || alert)(r.ok ? `${d.pruned} apagadas (retenção: ${d.days} dias).` : 'Erro.');
            loadList();
        });
        loadRetention();
    }

    // WebSocket real-time
    let ws = null;
    function connectWs() {
        try {
            const proto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            ws = new WebSocket(`${proto}//${window.location.host}/api/v1/ws/notifications?token=${encodeURIComponent(JWT)}`);
            ws.onopen = () => { $('kpiWs').innerHTML = '<span class="text-emerald-500">● conectado</span>'; };
            ws.onclose = () => { $('kpiWs').innerHTML = '<span class="text-slate-500">○ desconectado</span>'; setTimeout(connectWs, 5000); };
            ws.onerror = () => { $('kpiWs').innerHTML = '<span class="text-red-500">⚠ erro</span>'; };
            ws.onmessage = (ev) => {
                try {
                    const m = JSON.parse(ev.data);
                    if (m.type === 'alert') loadList();
                } catch {}
            };
        } catch (e) {
            $('kpiWs').innerHTML = '<span class="text-red-500">⚠ falhou</span>';
        }
    }
    connectWs();

    loadList();
})();
</script>

</body>
</html>
