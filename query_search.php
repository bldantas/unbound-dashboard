<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'query_search.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Busca em Queries - Unbound DNS</title>
    <meta name="description" content="Busca avançada em query_logs com filtros combinados (cliente, domínio, tipo, ação) e export CSV.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Busca em Queries";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <div class="glass-panel border-l-4 border-cyan-500 mb-6 border-slate-200 dark:border-white/5">
                <p class="text-[10px] font-black text-cyan-600 dark:text-cyan-400 uppercase tracking-widest mb-1">
                    Busca avançada em query_logs
                </p>
                <p class="text-sm font-bold text-slate-900 dark:text-white">Filtros combinados sobre 20M+ queries indexadas no DuckDB</p>
                <p class="text-[11px] text-slate-500 mt-1">
                    Cliente IP, domínio, tipo (A/AAAA/HTTPS/...), ação (resolved/blocked/cached/nxdomain). Janela ajustável.
                    Export CSV até 100k linhas por busca.
                </p>
            </div>

            <!-- Filtros -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Janela</label>
                        <select id="fWindow" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                            <option value="1h">Última hora</option>
                            <option value="24h" selected>24 horas</option>
                            <option value="7d">7 dias</option>
                            <option value="30d">30 dias</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Cliente IP (contém)</label>
                        <input type="text" id="fClient" placeholder="ex: 192.168.1.10"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Domínio (contém)</label>
                        <input type="text" id="fDomain" placeholder="ex: tiktok, .br, ads"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Tipo de query</label>
                        <select id="fType" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                            <option value="">Todos</option>
                            <option value="A">A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="HTTPS">HTTPS</option>
                            <option value="PTR">PTR</option>
                            <option value="SVCB">SVCB</option>
                            <option value="SRV">SRV</option>
                            <option value="MX">MX</option>
                            <option value="NS">NS</option>
                            <option value="TXT">TXT</option>
                            <option value="SOA">SOA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="NAPTR">NAPTR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Ação</label>
                        <select id="fAction" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                            <option value="">Todas</option>
                            <option value="resolved">Resolved</option>
                            <option value="blocked">Blocked</option>
                            <option value="cached">Cached</option>
                            <option value="nxdomain_upstream">NXDOMAIN upstream</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">País (origem)</label>
                        <select id="fCountry" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                            <option value="">Todos</option>
                            <!-- populado em JS via /api/v1/geoip/distribution -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Por página</label>
                        <select id="fPerPage" class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 justify-end">
                    <button id="btnClear" class="glass-btn text-[10px] uppercase font-black">Limpar filtros</button>
                    <button id="btnExport" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Export CSV</span>
                    </button>
                    <button id="btnSearch" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <span>Buscar</span>
                    </button>
                </div>
            </div>

            <!-- Resultados -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">
                        Resultados (<span id="statTotal" class="text-cyan-500">—</span>)
                    </h3>
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest" id="statPage">—</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <th class="w-44">Quando</th>
                                <th class="w-36">Cliente</th>
                                <th class="w-20">País</th>
                                <th>Domínio</th>
                                <th class="w-20">Tipo</th>
                                <th class="w-32">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="resultsBody">
                            <tr><td colspan="6" class="px-6 py-16 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Configure filtros e clique <b class="text-cyan-500">Buscar</b></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-3">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest" id="paginationInfo">—</div>
                    <div class="flex items-center gap-2" id="paginationCtrl"></div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<script>
(function() {
    const jwtMeta = document.querySelector('meta[name="api-jwt"]');
    const JWT = jwtMeta ? jwtMeta.content : '';
    const H = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};
    let currentPage = 1, currentTotal = 0, currentPerPage = 50;

    const ACTION_COLORS = {
        'resolved': 'emerald',
        'blocked': 'red',
        'cached': 'blue',
        'nxdomain_upstream': 'orange',
    };

    function buildParams(extra = {}) {
        const params = new URLSearchParams({
            window: document.getElementById('fWindow').value,
            client_ip: document.getElementById('fClient').value.trim(),
            domain: document.getElementById('fDomain').value.trim(),
            query_type: document.getElementById('fType').value,
            action: document.getElementById('fAction').value,
            country: document.getElementById('fCountry').value,
            ...extra,
        });
        // Remove vazios
        for (const [k, v] of [...params.entries()]) if (!v) params.delete(k);
        return params;
    }

    // Cache de lookups IP→país (sessão da página)
    const __ipCountryCache = new Map();

    function ccToFlagJSX(cc, tooltip) {
        const tip = tooltip || cc || '';
        if (!cc) return '<span class="text-slate-500">—</span>';
        if (cc === '--') return `<span title="${tip || 'Rede privada'}">🏠</span>`;
        if (cc === '??') return `<span title="${tip || 'Desconhecido'}" class="text-slate-500">❓</span>`;
        if (!/^[A-Z]{2}$/.test(cc)) return '<span class="text-slate-500">—</span>';
        const cp = cc.split('').map(c => 127397 + c.charCodeAt(0));
        const flag = String.fromCodePoint(...cp);
        return `<span title="${tip || cc}">${flag} <span class="text-[10px] font-bold font-mono text-slate-500">${cc}</span></span>`;
    }

    function escAttr(s) { return String(s || '').replace(/"/g, '&quot;'); }

    async function enrichRowsWithCountry(rows) {
        // Faz lookup-bulk dos IPs que ainda não estão em cache. Cache armazena
        // {cc, asn, asn_name} pra evitar re-lookup quando trocar página.
        const need = [...new Set(rows.map(r => r.client_ip).filter(ip => ip && !__ipCountryCache.has(ip)))];
        if (need.length) {
            try {
                const res = await fetch('/api/v1/geoip/lookup-bulk', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', ...H },
                    body: JSON.stringify({ ips: need }),
                });
                if (res.ok) {
                    const d = await res.json();
                    Object.entries(d.results || {}).forEach(([ip, info]) => {
                        __ipCountryCache.set(ip, {
                            cc: info.country_code || '??',
                            asn: info.asn || '',
                            asn_name: info.asn_name || '',
                        });
                    });
                }
            } catch (_) { /* ignora falha */ }
            need.forEach(ip => {
                if (!__ipCountryCache.has(ip)) __ipCountryCache.set(ip, { cc: '??', asn: '', asn_name: '' });
            });
        }
        rows.forEach(r => {
            const info = __ipCountryCache.get(r.client_ip) || { cc: '??', asn: '', asn_name: '' };
            r.__country = info.cc;
            r.__asn = info.asn;
            r.__asn_name = info.asn_name;
        });
    }

    async function populateCountryDropdown() {
        const sel = document.getElementById('fCountry');
        if (!sel) return;
        try {
            const r = await fetch('/api/v1/geoip/distribution?hours=168&limit=40', { headers: H });
            if (!r.ok) return;
            const d = await r.json();
            const items = d.countries || [];
            const current = sel.value;
            // Preserva placeholder "Todos" + reconstrói
            sel.innerHTML = '<option value="">Todos</option>'
                + items.filter(c => /^[A-Z]{2}$/.test(c.country_code || '') || c.country_code === '--' || c.country_code === '??')
                       .map(c => `<option value="${c.country_code}">${c.country_code} — ${c.country_name}</option>`).join('');
            sel.value = current;  // restaura seleção se válida
        } catch (_) { /* silencia */ }
    }

    async function doSearch(page = 1) {
        currentPage = page;
        currentPerPage = parseInt(document.getElementById('fPerPage').value);
        const tbody = document.getElementById('resultsBody');
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center"><div class="w-6 h-6 mx-auto border-2 border-cyan-500/30 border-t-cyan-500 rounded-full animate-spin"></div></td></tr>`;
        try {
            const params = buildParams({ page, per_page: currentPerPage });
            const res = await fetch(`/api/v1/analytics/queries/search?${params}`, { headers: H });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            currentTotal = data.total;
            await renderResults(data);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-500 text-xs uppercase font-black tracking-widest">Erro: ${err.message}</td></tr>`;
        }
    }

    async function renderResults(data) {
        const tbody = document.getElementById('resultsBody');
        document.getElementById('statTotal').textContent = (data.total || 0).toLocaleString('pt-BR');
        const truncTag = data.truncated ? ' <span class="text-amber-500" title="Filtro de país operou sobre as 5000 linhas mais recentes">⚠ pré-cap 5k</span>' : '';
        document.getElementById('statPage').innerHTML = `Página ${data.page} de ${data.total_pages}${truncTag}`;

        if (!data.rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-16 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Nenhum resultado pra esses filtros</td></tr>`;
            renderPagination(data);
            return;
        }

        // Enriquece com country code (cache + lookup-bulk dos novos)
        await enrichRowsWithCountry(data.rows);

        tbody.innerHTML = data.rows.map(r => {
            const d = new Date(r.timestamp * 1000);
            const when = d.toLocaleDateString('pt-BR', { day:'2-digit', month:'2-digit', year:'2-digit' })
                       + ' ' + d.toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
            const c = ACTION_COLORS[r.action] || 'slate';
            return `
            <tr>
                <td class="text-[11px] font-mono text-slate-500">${when}</td>
                <td class="font-mono text-xs" title="${escAttr((r.__asn || '') + (r.__asn_name ? ' — ' + r.__asn_name : ''))}">${escapeHtml(r.client_ip)}</td>
                <td class="text-base">${ccToFlagJSX(r.__country, (r.__asn || '') + (r.__asn_name ? ' — ' + r.__asn_name : ''))}</td>
                <td class="font-mono text-sm">${escapeHtml(r.domain)}</td>
                <td><span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-slate-500/15 text-slate-600 dark:text-slate-400 border border-slate-500/30">${escapeHtml(r.query_type)}</span></td>
                <td><span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-${c}-500/15 text-${c}-600 dark:text-${c}-400 border border-${c}-500/30">${escapeHtml(r.action)}</span></td>
            </tr>`;
        }).join('');
        renderPagination(data);
    }

    function renderPagination(data) {
        const info = document.getElementById('paginationInfo');
        const ctrl = document.getElementById('paginationCtrl');
        if (!data.total) { info.textContent = '—'; ctrl.innerHTML = ''; return; }
        const start = (data.page - 1) * data.per_page + 1;
        const end = Math.min(data.page * data.per_page, data.total);
        info.textContent = `${start.toLocaleString('pt-BR')}–${end.toLocaleString('pt-BR')} de ${data.total.toLocaleString('pt-BR')}`;

        let html = '';
        if (data.total_pages > 1) {
            html += `<button class="glass-btn text-[10px] uppercase font-black" ${data.page<=1?'disabled':''} data-go="${Math.max(1, data.page-1)}">‹</button>`;
            html += `<span class="text-[10px] uppercase font-black text-slate-500">${data.page} / ${data.total_pages}</span>`;
            html += `<button class="glass-btn text-[10px] uppercase font-black" ${data.page>=data.total_pages?'disabled':''} data-go="${Math.min(data.total_pages, data.page+1)}">›</button>`;
        }
        ctrl.innerHTML = html;
        ctrl.querySelectorAll('[data-go]').forEach(b => b.addEventListener('click', () => doSearch(parseInt(b.dataset.go))));
    }

    // ============ HANDLERS ============
    document.getElementById('btnSearch').addEventListener('click', () => doSearch(1));
    document.getElementById('btnClear').addEventListener('click', () => {
        document.getElementById('fClient').value = '';
        document.getElementById('fDomain').value = '';
        document.getElementById('fType').value = '';
        document.getElementById('fAction').value = '';
        document.getElementById('fCountry').value = '';
        doSearch(1);
    });
    document.getElementById('btnExport').addEventListener('click', () => {
        if (currentTotal === 0) {
            if (window.AppUI?.toast) window.AppUI.toast('Faça uma busca antes de exportar', 'warning');
            return;
        }
        const params = buildParams({ limit: '100000' });
        // Download direto via window.open com JWT no header? Não dá — vamos via redirect, mas precisamos do JWT.
        // Solução: fetch + blob + download programático.
        downloadCsv(params);
    });
    async function downloadCsv(params) {
        const btn = document.getElementById('btnExport');
        btn.disabled = true;
        const span = btn.querySelector('span');
        const orig = span.textContent;
        span.textContent = 'Exportando...';
        try {
            const res = await fetch(`/api/v1/analytics/queries/export-csv?${params}`, { headers: H });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const ts = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
            a.download = `unbound-queries-${ts}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            if (window.AppUI?.toast) window.AppUI.toast('CSV baixado', 'success');
        } catch (err) {
            if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            span.textContent = orig;
        }
    }

    // Enter no campo dispara busca
    ['fClient','fDomain'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); doSearch(1); }
        });
    });

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // Dispara nova busca quando troca o filtro de país (mais natural que ter que clicar Buscar)
    document.getElementById('fCountry').addEventListener('change', () => doSearch(1));

    // Bootstrap: popula dropdown de países (paralelo à primeira busca)
    populateCountryDropdown();

    // Auto-busca inicial: últimas queries da última hora
    doSearch(1);
})();
</script>

</body>
</html>
