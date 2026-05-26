<?php
/**
 * Command palette (Cmd+K) + atalhos de teclado globais.
 * Incluído no footer de todas as páginas autenticadas via includes/footer.php
 * ou direto. Usa só window/document — não depende de framework.
 */
?>
<!-- Command Palette Modal -->
<div id="cmdkBackdrop" class="hidden fixed inset-0 z-[200] bg-slate-900/60 backdrop-blur-sm flex items-start justify-center pt-[15vh] px-4">
    <div id="cmdkPanel" class="w-full max-w-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-200 dark:border-white/10">
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input id="cmdkInput" type="text" placeholder="Buscar página, ação ou atalho..." class="flex-1 bg-transparent outline-none text-slate-900 dark:text-white placeholder-slate-400 text-sm">
            <kbd class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-white/5 text-slate-500 text-[10px] font-bold border border-slate-200 dark:border-white/10">ESC</kbd>
        </div>
        <ul id="cmdkResults" class="max-h-[50vh] overflow-y-auto p-2 text-sm"></ul>
        <div class="px-4 py-2 border-t border-slate-200 dark:border-white/10 text-[10px] text-slate-500 flex items-center justify-between">
            <span><kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 font-mono">↑↓</kbd> navegar · <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 font-mono">Enter</kbd> abrir</span>
            <span>Atalho global: <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 font-mono">Ctrl</kbd>+<kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 font-mono">K</kbd></span>
        </div>
    </div>
</div>

<!-- Help (Atalhos) Modal -->
<div id="shortcutsBackdrop" class="hidden fixed inset-0 z-[200] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 dark:border-white/10">
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 dark:text-white">Atalhos de teclado</h3>
            <kbd class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-white/5 text-slate-500 text-[10px] font-bold border border-slate-200 dark:border-white/10">ESC</kbd>
        </div>
        <div class="p-5 grid grid-cols-2 gap-x-6 gap-y-3 text-[11px]">
            <div class="text-slate-500">Abrir busca</div><div><kbd class="kbd">Ctrl</kbd>+<kbd class="kbd">K</kbd></div>
            <div class="text-slate-500">Esta ajuda</div><div><kbd class="kbd">?</kbd></div>
            <div class="text-slate-500">Dashboard</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">h</kbd></div>
            <div class="text-slate-500">Ameaças</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">t</kbd></div>
            <div class="text-slate-500">Analítico</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">a</kbd></div>
            <div class="text-slate-500">Observabilidade</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">o</kbd></div>
            <div class="text-slate-500">Segurança DNS</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">d</kbd></div>
            <div class="text-slate-500">Cache</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">c</kbd></div>
            <div class="text-slate-500">Logs</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">l</kbd></div>
            <div class="text-slate-500">Configurações</div><div><kbd class="kbd">g</kbd> depois <kbd class="kbd">,</kbd></div>
            <div class="text-slate-500">Alternar tema</div><div><kbd class="kbd">Shift</kbd>+<kbd class="kbd">T</kbd></div>
        </div>
    </div>
</div>

<style>
    .kbd { display: inline-block; padding: 1px 6px; border-radius: 4px; background: rgba(100,116,139,0.12); border: 1px solid rgba(100,116,139,0.2); font-family: ui-monospace, SFMono-Regular, monospace; font-size: 10px; font-weight: 700; color: rgb(71,85,105); }
    .dark .kbd { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); color: rgb(203,213,225); }
    .cmdk-item { padding: 8px 10px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 10px; }
    .cmdk-item:hover, .cmdk-item.active { background: rgba(99,102,241,0.08); }
    .dark .cmdk-item:hover, .dark .cmdk-item.active { background: rgba(99,102,241,0.16); }
    .cmdk-item-title { font-weight: 700; color: rgb(15,23,42); }
    .dark .cmdk-item-title { color: white; }
    .cmdk-item-desc { font-size: 10px; color: rgb(100,116,139); margin-left: auto; text-transform: uppercase; letter-spacing: 0.05em; }
</style>

<script>
(function() {
    'use strict';

    // Catálogo de páginas/ações — fonte da verdade. Cada item: {label, hint, url, icon, kw}
    const COMMANDS = [
        { label: 'Dashboard',           hint: 'Visão geral',                  url: 'index.php',           kw: 'home início overview' },
        { label: 'Ameaças',             hint: 'Top blocked + tempo real',     url: 'threats.php',         kw: 'threats blocked' },
        { label: 'Analítico',           hint: 'Janelas + top + retenção',     url: 'analytics.php',       kw: 'analytics gráficos' },
        { label: 'Buscar Queries',      hint: 'Query log search + CSV',       url: 'query_search.php',    kw: 'queries search csv' },
        { label: 'Anomalias',           hint: 'DGA / spike / cliente novo',   url: 'anomalies.php',       kw: 'anomalia dga spike' },
        { label: 'Observabilidade',     hint: 'KPIs + workers + timeseries',  url: 'observability.php',   kw: 'observability slo latency' },
        { label: 'Segurança DNS',       hint: 'DNSSEC + DoT upstream',        url: 'dns_security.php',    kw: 'dnssec dot tls upstream' },
        { label: 'Geo-Blocking',        hint: 'Bloquear países inteiros',     url: 'geo_blocking.php',    kw: 'geo geoip country pais firewall acl' },
        { label: 'Blocklists',          hint: 'Multi-source + allowlist',     url: 'blocklists.php',      kw: 'blocklist hagezi' },
        { label: 'Blocklist ANATEL',    hint: 'Bloqueios judiciais',          url: 'blocklist.php',       kw: 'anatel judicial' },
        { label: 'Políticas Cliente',   hint: 'Split-horizon por CIDR',       url: 'client_policies.php', kw: 'policies split-horizon view' },
        { label: 'Cache DNS',           hint: 'Lookup / flush / reload',      url: 'cache.php',           kw: 'cache rrset' },
        { label: 'Logs',                hint: 'Logs do sistema',              url: 'logs.php',            kw: 'logs syslog' },
        { label: 'Stream ao Vivo',      hint: 'Feed WebSocket em tempo real', url: 'live_stream.php',     kw: 'stream live websocket realtime' },
        { label: 'Histórico',           hint: 'Histórico geral',              url: 'history.php',         kw: 'history' },
        { label: 'Alertas',             hint: 'Eventos críticos',             url: 'alerts.php',          kw: 'alerts thresholds' },
        { label: 'Diagnóstico',         hint: 'Health check do daemon',       url: 'diagnostics.php',     kw: 'diag health' },
        { label: 'Benchmark DNS',       hint: 'Comparar resolvers',           url: 'dns_benchmark.php',   kw: 'benchmark resolver' },
        { label: 'Exportações',         hint: 'Downloads de dados',           url: 'exports.php',         kw: 'export download csv' },
        { label: 'API & Integrações',   hint: 'Swagger + Prometheus + Grafana', url: 'api_docs.php',      kw: 'api openapi swagger grafana prometheus' },
        { label: 'Backup S3',           hint: 'Upload offsite',               url: 'backup_offsite.php',  kw: 'backup s3 wasabi r2' },
        { label: 'Hosts gerenciados',   hint: 'Multi-host + push config',     url: 'hosts.php',           kw: 'hosts multi-host master agent' },
        { label: 'Configurações',       hint: 'Sistema + atualizações',       url: 'config.php',          kw: 'config settings' },
        { label: 'Usuários',            hint: 'Gerenciar usuários',           url: 'users.php',           kw: 'users team' },
        { label: 'Saúde',               hint: 'System health',                url: 'health.php',          kw: 'health status' },
        { label: 'Changelog',           hint: 'Histórico de releases',        url: 'changelog.php',       kw: 'changelog releases' },
        { label: 'Sair',                hint: 'Logout',                       url: 'logout.php',          kw: 'logout sair' },
    ];

    const backdrop = document.getElementById('cmdkBackdrop');
    const panel = document.getElementById('cmdkPanel');
    const input = document.getElementById('cmdkInput');
    const results = document.getElementById('cmdkResults');
    const shortcutsBackdrop = document.getElementById('shortcutsBackdrop');
    if (!backdrop || !input || !results) return;

    let activeIdx = 0;
    let filtered = COMMANDS.slice();

    function fuzzyMatch(q, item) {
        if (!q) return 0;
        const ql = q.toLowerCase();
        const hay = (item.label + ' ' + item.hint + ' ' + item.kw).toLowerCase();
        if (hay.includes(ql)) return 100;
        // Match por iniciais (ex: "ob" → "Observabilidade")
        const labelLower = item.label.toLowerCase();
        if (labelLower.startsWith(ql)) return 90;
        // char-by-char fuzzy
        let qi = 0;
        for (let i = 0; i < hay.length && qi < ql.length; i++) {
            if (hay[i] === ql[qi]) qi++;
        }
        return qi === ql.length ? 50 : 0;
    }

    function render() {
        if (filtered.length === 0) {
            results.innerHTML = '<li class="px-3 py-4 text-center text-slate-500 text-xs">Nenhum resultado.</li>';
            return;
        }
        results.innerHTML = filtered.map((item, idx) => `
            <li class="cmdk-item ${idx === activeIdx ? 'active' : ''}" data-idx="${idx}" data-url="${item.url}">
                <span class="cmdk-item-title text-sm">${item.label}</span>
                <span class="cmdk-item-desc">${item.hint}</span>
            </li>
        `).join('');
        results.querySelectorAll('.cmdk-item').forEach(li => {
            li.addEventListener('click', () => navigate(li.dataset.url));
            li.addEventListener('mouseenter', () => {
                activeIdx = parseInt(li.dataset.idx, 10);
                render();
            });
        });
    }

    function open() {
        backdrop.classList.remove('hidden');
        input.value = '';
        filtered = COMMANDS.slice();
        activeIdx = 0;
        render();
        setTimeout(() => input.focus(), 0);
    }
    function close() { backdrop.classList.add('hidden'); }
    function openShortcuts() { shortcutsBackdrop.classList.remove('hidden'); }
    function closeShortcuts() { shortcutsBackdrop.classList.add('hidden'); }

    function navigate(url) {
        if (!url) return;
        close();
        window.location.href = url;
    }

    input.addEventListener('input', () => {
        const q = input.value.trim();
        if (!q) {
            filtered = COMMANDS.slice();
        } else {
            const scored = COMMANDS
                .map(item => ({ item, score: fuzzyMatch(q, item) }))
                .filter(x => x.score > 0)
                .sort((a, b) => b.score - a.score);
            filtered = scored.map(x => x.item);
        }
        activeIdx = 0;
        render();
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') { close(); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); if (filtered.length) { activeIdx = (activeIdx + 1) % filtered.length; render(); }}
        else if (e.key === 'ArrowUp') { e.preventDefault(); if (filtered.length) { activeIdx = (activeIdx - 1 + filtered.length) % filtered.length; render(); }}
        else if (e.key === 'Enter') { e.preventDefault(); if (filtered[activeIdx]) navigate(filtered[activeIdx].url); }
    });

    backdrop.addEventListener('click', e => { if (e.target === backdrop) close(); });
    shortcutsBackdrop.addEventListener('click', e => { if (e.target === shortcutsBackdrop) closeShortcuts(); });

    // ===== Atalhos globais =====
    let gPending = false;
    let gTimer = null;
    const G_MAP = {
        'h': 'index.php',
        't': 'threats.php',
        'a': 'analytics.php',
        'o': 'observability.php',
        'd': 'dns_security.php',
        'c': 'cache.php',
        'l': 'logs.php',
        ',': 'config.php',
    };

    function isTypingTarget(el) {
        if (!el) return false;
        const tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        if (el.isContentEditable) return true;
        return false;
    }

    document.addEventListener('keydown', e => {
        // Cmd/Ctrl+K abre busca
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            open();
            return;
        }
        if (e.key === 'Escape') {
            if (!backdrop.classList.contains('hidden')) close();
            if (!shortcutsBackdrop.classList.contains('hidden')) closeShortcuts();
            return;
        }
        if (isTypingTarget(e.target)) return;

        // ? abre ajuda
        if (e.key === '?' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            openShortcuts();
            return;
        }
        // Shift+T alterna tema
        if (e.key === 'T' && e.shiftKey && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            const btn = document.getElementById('themeToggle');
            if (btn) btn.click();
            return;
        }
        // Sequência 'g' + letter (vim-style)
        if (gPending) {
            gPending = false;
            clearTimeout(gTimer);
            const key = e.key.toLowerCase();
            const url = G_MAP[key];
            if (url) {
                e.preventDefault();
                window.location.href = url;
            }
            return;
        }
        if (e.key === 'g' && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
            gPending = true;
            gTimer = setTimeout(() => { gPending = false; }, 1500);
        }
    });
})();
</script>
