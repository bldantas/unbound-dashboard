<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'blocklists.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Blocklists - Unbound DNS</title>
    <meta name="description" content="Gerencia múltiplas blocklists curadas (StevenBlack/Hagezi/OISD/AdGuard/NoCoin/EasyPrivacy) + allowlist global. ANATEL Judicial fica em página própria.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('blocklists.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <!-- Cabeçalho com link de retorno pra ANATEL -->
            <div class="glass-panel border-l-4 border-orange-500 mb-6 border-slate-200 dark:border-white/5">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">
                            Blocklists curadas — Adware / Malware / Tracking
                        </p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                            Múltiplas fontes simultâneas
                            <span class="text-slate-500 dark:text-slate-400 font-medium"> · cada uma com toggles independentes de <b>indexar</b> (catálogo) e <b>bloquear</b> (NXDOMAIN no Unbound)</span>
                        </p>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Bloqueio judicial ANATEL fica em <a href="blocklist.php" class="text-orange-500 hover:underline font-bold">página própria</a>.
                            Esta página não inclui categoria <em>Judicial</em>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 mb-6 border-b border-slate-200 dark:border-white/5 flex-wrap">
                <button data-tab="sources" class="tab-btn tab-active px-5 py-3 text-[11px] font-black uppercase tracking-widest border-b-2 border-orange-500 text-orange-500">
                    Fontes
                </button>
                <button data-tab="search" class="tab-btn px-5 py-3 text-[11px] font-black uppercase tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                    Busca no Catálogo
                </button>
                <button data-tab="exceptions" class="tab-btn px-5 py-3 text-[11px] font-black uppercase tracking-widest border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                    Exceções (Allowlist)
                </button>
            </div>

            <!-- ====================== TAB: FONTES ====================== -->
            <section data-panel="sources" class="tab-panel">
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('blocklists.section_sources') ?></h3>
                            <p class="text-[11px] text-slate-500 mt-1">Indexar popula o catálogo (busca/analytics). Bloquear injeta como NXDOMAIN no Unbound.</p>
                        </div>
                        <?php if (\App\Auth::can('blocklist.write')): ?>
                            <button type="button" id="btnSyncAll" class="glass-btn !bg-orange-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                <span>Sincronizar Ativas</span>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="glass-table w-full" id="sourcesTable">
                            <thead>
                                <tr>
                                    <th>Fonte</th>
                                    <th class="w-28">Categoria</th>
                                    <th class="w-24 text-center">Indexar</th>
                                    <th class="w-24 text-center">Bloquear</th>
                                    <th class="w-28 text-right">Domínios</th>
                                    <th class="w-40">Último sync</th>
                                    <th class="w-24"></th>
                                </tr>
                            </thead>
                            <tbody id="sourcesBody">
                                <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500 text-xs uppercase tracking-widest font-black">
                                    <div class="w-6 h-6 mx-auto border-2 border-orange-500/30 border-t-orange-500 rounded-full animate-spin"></div>
                                    <span class="block mt-2">Carregando fontes...</span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Aviso e botão de Aplicar (gerar conf + reload do Unbound) -->
                <div class="glass-panel border-l-4 border-blue-500 border-slate-200 dark:border-white/5" id="applyBanner" style="display:none">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-1"><?= t('blocklists.pending_change_title') ?></p>
                            <p class="text-sm font-bold text-slate-900 dark:text-white"><?= t('blocklists.pending_change_desc') ?></p>
                            <p class="text-[11px] text-slate-500 mt-1">Clique em <b>Aplicar &amp; Recarregar Unbound</b> pra regerar o arquivo de bloqueio e mandar o Unbound recarregar.</p>
                        </div>
                        <?php if (\App\Auth::can('blocklist.write')): ?>
                            <button type="button" id="btnApplyConf" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                <span>Aplicar &amp; Recarregar Unbound</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- ====================== TAB: BUSCA ====================== -->
            <section data-panel="search" class="tab-panel" style="display:none">
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                    <div class="flex flex-col lg:flex-row gap-4 mb-4">
                        <div class="flex-1 relative group">
                            <input type="text" id="searchInput" placeholder="Pesquisar domínio... ex: ads, tracker, doubleclick"
                                   class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500/40 transition-all font-medium" autocomplete="off">
                        </div>
                        <select id="categoryFilter" class="appearance-none px-4 py-3 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm focus:outline-none font-medium min-w-[180px] cursor-pointer">
                            <option value="">Todas categorias (exc. Judicial)</option>
                            <option value="Malware/Adware">Malware / Adware</option>
                            <option value="Tracking">Tracking</option>
                        </select>
                        <select id="perPageSelect" class="appearance-none px-4 py-3 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm focus:outline-none font-medium cursor-pointer">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <div class="text-[10px] uppercase font-black tracking-widest text-slate-500 mb-2">
                        Resultados: <span id="searchTotal" class="text-orange-500">—</span>
                        · Página <span id="searchPage" class="text-slate-700 dark:text-slate-300">—</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="glass-table w-full">
                            <thead>
                                <tr>
                                    <th class="w-16">#</th>
                                    <th>Domínio</th>
                                    <th class="w-32">Categoria</th>
                                    <th class="w-24">Severidade</th>
                                </tr>
                            </thead>
                            <tbody id="searchBody">
                                <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Digite acima pra buscar</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between mt-4 flex-wrap gap-2">
                        <div class="text-[10px] uppercase font-black text-slate-500 tracking-widest" id="searchPaginationInfo">—</div>
                        <div class="flex items-center gap-2" id="searchPaginationCtrl"></div>
                    </div>
                </div>
            </section>

            <!-- ====================== TAB: EXCEÇÕES ====================== -->
            <section data-panel="exceptions" class="tab-panel" style="display:none">
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3"><?= t('blocklists.section_add_exception') ?></h3>
                    <p class="text-[11px] text-slate-500 mb-4">
                        Domínios na allowlist <b>sempre resolvem normalmente</b>, mesmo que apareçam em alguma blocklist.
                        Use pra liberar serviços legítimos que alguma fonte bloqueia por engano (ex: <code>googletagmanager.com</code>).
                    </p>
                    <?php if (\App\Auth::can('blocklist.write')): ?>
                        <form id="formAddException" class="flex flex-col md:flex-row gap-3">
                            <input type="text" id="exDomain" placeholder="domain.com" required pattern="^[a-z0-9][a-z0-9.\-]*\.[a-z]{2,}$"
                                   class="flex-1 px-4 py-3 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                            <input type="text" id="exReason" placeholder="Motivo (opcional)" maxlength="200"
                                   class="flex-1 px-4 py-3 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                            <button type="submit" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                <span>Adicionar</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-[11px] text-slate-500 italic">Você não tem permissão pra criar exceções.</p>
                    <?php endif; ?>
                </div>

                <!-- Bulk operations (D.2) -->
                <?php if (\App\Auth::can('blocklist.write')): ?>
                <div class="glass-panel border-slate-200 dark:border-white/5 mb-4">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-3"><?= t('blocklists.section_bulk') ?></h3>
                    <p class="text-[11px] text-slate-500 mb-4">
                        Importa lista de domínios (CSV ou texto, 1 por linha). Exporta toda a allowlist em CSV.
                    </p>
                    <div class="flex flex-wrap gap-3 items-center">
                        <label class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black flex items-center gap-2 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            <span>Importar CSV/TXT</span>
                            <input type="file" id="exImportFile" accept=".csv,.txt,text/csv,text/plain" class="hidden">
                        </label>
                        <a href="/api/v1/blocklist/exceptions/export.csv" id="exExportLink" target="_blank" rel="noopener" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            <span>Exportar CSV</span>
                        </a>
                        <span id="exBulkStatus" class="text-[10px] text-slate-500"></span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('blocklists.section_active_exceptions') ?> (<span id="exCount">0</span>)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="glass-table w-full">
                            <thead>
                                <tr>
                                    <th>Domínio</th>
                                    <th>Motivo</th>
                                    <th class="w-32">Criado por</th>
                                    <th class="w-40">Quando</th>
                                    <th class="w-16"></th>
                                </tr>
                            </thead>
                            <tbody id="exceptionsBody">
                                <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

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
    const CAN_WRITE = <?= \App\Auth::can('blocklist.write') ? 'true' : 'false' ?>;

    // ============ TABS ============
    const tabs = document.querySelectorAll('[data-tab]');
    const panels = document.querySelectorAll('[data-panel]');
    function activateTab(name) {
        tabs.forEach(t => {
            const isActive = t.dataset.tab === name;
            t.classList.toggle('tab-active', isActive);
            t.classList.toggle('border-orange-500', isActive);
            t.classList.toggle('text-orange-500', isActive);
            t.classList.toggle('border-transparent', !isActive);
            t.classList.toggle('text-slate-500', !isActive);
        });
        panels.forEach(p => {
            p.style.display = p.dataset.panel === name ? '' : 'none';
        });
        history.replaceState(null, '', '#tab-' + name);
        if (name === 'exceptions') loadExceptions();
        if (name === 'search') maybeFetchSearch();
    }
    tabs.forEach(t => t.addEventListener('click', () => activateTab(t.dataset.tab)));
    const initialTab = (location.hash || '').replace('#tab-', '') || 'sources';
    if (['sources','search','exceptions'].includes(initialTab)) activateTab(initialTab);

    // ============ FONTES (Tab 1) ============
    const sourcesBody = document.getElementById('sourcesBody');
    const applyBanner = document.getElementById('applyBanner');
    let lastBlockFlags = new Map();  // slug -> bool (estado inicial pra detectar mudança)

    function fmtAge(iso) {
        if (!iso) return 'nunca';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return '—';
        const s = Math.max(0, Math.floor((Date.now() - t) / 1000));
        if (s < 60) return s + 's atrás';
        if (s < 3600) return Math.floor(s / 60) + 'min atrás';
        if (s < 86400) return Math.floor(s / 3600) + 'h atrás';
        return Math.floor(s / 86400) + 'd atrás';
    }

    async function loadSources() {
        try {
            const res = await fetch('/api/v1/blocklist/sources', { headers: H });
            const data = await res.json();
            const sources = (data.sources || []).filter(s => s.category !== 'Judicial');
            renderSources(sources);
            // Captura estado inicial de block_enabled pra detectar mudanças
            if (lastBlockFlags.size === 0) {
                sources.forEach(s => lastBlockFlags.set(s.slug, s.block_enabled));
            }
        } catch (err) {
            sourcesBody.innerHTML = `<tr><td colspan="7" class="px-6 py-12 text-center text-red-500 text-xs uppercase font-black tracking-widest">Erro ao carregar fontes: ${err.message}</td></tr>`;
        }
    }

    function renderSources(sources) {
        if (!sources.length) {
            sourcesBody.innerHTML = `<tr><td colspan="7" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Nenhuma fonte cadastrada</td></tr>`;
            return;
        }
        const catColor = { 'Malware/Adware': 'orange', 'Tracking': 'purple' };
        sourcesBody.innerHTML = sources.map(s => {
            const color = catColor[s.category] || 'slate';
            const errBadge = s.last_error
                ? `<span class="block text-[9px] text-red-500 font-mono mt-1" title="${escapeHtml(s.last_error)}">⚠ ${escapeHtml(s.last_error.slice(0,60))}${s.last_error.length>60?'…':''}</span>`
                : '';
            return `
            <tr data-slug="${s.slug}" class="source-row">
                <td>
                    <div class="font-black text-base text-slate-900 dark:text-white">${escapeHtml(s.name)}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">${escapeHtml(s.description || '')}</div>
                    ${errBadge}
                </td>
                <td>
                    <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md bg-${color}-500/15 text-${color}-600 dark:text-${color}-400 border border-${color}-500/30">
                        ${escapeHtml(s.category)}
                    </span>
                </td>
                <td class="text-center">
                    ${toggleHtml('idx', s.slug, s.index_enabled)}
                </td>
                <td class="text-center">
                    ${toggleHtml('blk', s.slug, s.block_enabled)}
                </td>
                <td class="text-right font-mono text-sm font-bold">${(s.last_count || 0).toLocaleString('pt-BR')}</td>
                <td class="text-[11px] text-slate-500">${fmtAge(s.last_sync)}</td>
                <td class="text-right">
                    ${CAN_WRITE ? `<button class="glass-btn !bg-orange-600 !text-white text-[10px] uppercase font-black btn-sync-source inline-flex items-center gap-1.5 px-3 py-2" data-slug="${s.slug}" title="Sincronizar agora">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <span>Sincronizar</span>
                    </button>` : ''}
                </td>
            </tr>`;
        }).join('');
    }

    function toggleHtml(kind, slug, enabled) {
        if (!CAN_WRITE) return enabled ? '✓' : '—';
        return `
        <label class="inline-flex items-center cursor-pointer select-none">
            <span class="relative inline-block w-9 h-5">
                <input type="checkbox" class="peer sr-only toggle-flag" data-kind="${kind}" data-slug="${slug}" ${enabled ? 'checked' : ''}>
                <span class="block w-9 h-5 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-colors"></span>
                <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
            </span>
        </label>`;
    }

    // Delegated handlers
    sourcesBody.addEventListener('change', async (e) => {
        const t = e.target;
        if (!t.classList.contains('toggle-flag')) return;
        const slug = t.dataset.slug;
        const kind = t.dataset.kind; // 'idx' | 'blk'
        const body = {};
        body[kind === 'idx' ? 'index_enabled' : 'block_enabled'] = t.checked;
        t.disabled = true;
        try {
            const res = await fetch(`/api/v1/blocklist/sources/${slug}`, { method: 'PATCH', headers: HJ, body: JSON.stringify(body) });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            // Se mudou block_enabled e difere do estado inicial, mostra banner
            if (kind === 'blk' && lastBlockFlags.get(slug) !== t.checked) {
                applyBanner.style.display = '';
            }
            toast(`${slug}: ${kind === 'idx' ? 'indexar' : 'bloquear'} = ${t.checked ? 'on' : 'off'}`, 'success');
        } catch (err) {
            t.checked = !t.checked;
            toast('Falha ao atualizar: ' + err.message, 'error');
        } finally {
            t.disabled = false;
        }
    });

    sourcesBody.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-sync-source');
        if (!btn) return;
        const slug = btn.dataset.slug;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = '...';
        try {
            const res = await fetch(`/api/v1/blocklist/sources/${slug}/sync?force=true`, { method: 'POST', headers: H });
            const data = await res.json();
            if (data.status === 'ok') toast(`${slug}: ${data.count.toLocaleString('pt-BR')} domínios sincronizados`, 'success');
            else if (data.status === 'fresh') toast(`${slug}: já estava atualizado (${data.count} domínios)`, 'info');
            else toast(`${slug}: ${data.status} — ${data.error || 'erro'}`, 'error');
            await loadSources();
        } catch (err) {
            toast('Falha no sync: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = original;
        }
    });

    const btnSyncAll = document.getElementById('btnSyncAll');
    if (btnSyncAll) btnSyncAll.addEventListener('click', async () => {
        const ok = await customConfirm('Sincronizar todas as fontes com "Indexar" ligado? Pode levar alguns minutos.', 'Sincronizar?');
        if (!ok) return;
        btnSyncAll.disabled = true;
        const original = btnSyncAll.querySelector('span').textContent;
        btnSyncAll.querySelector('span').textContent = 'Sincronizando...';
        const enabled = Array.from(document.querySelectorAll('.toggle-flag[data-kind="idx"]:checked')).map(t => t.dataset.slug);
        let okCount = 0, errCount = 0;
        for (const slug of enabled) {
            try {
                const res = await fetch(`/api/v1/blocklist/sources/${slug}/sync?force=true`, { method: 'POST', headers: H });
                const data = await res.json();
                if (data.status === 'ok') okCount++; else errCount++;
            } catch { errCount++; }
        }
        toast(`Sync concluído: ${okCount} ok, ${errCount} falhas`, errCount ? 'warning' : 'success');
        btnSyncAll.disabled = false;
        btnSyncAll.querySelector('span').textContent = original;
        await loadSources();
    });

    const btnApply = document.getElementById('btnApplyConf');
    if (btnApply) btnApply.addEventListener('click', async () => {
        const ok = await customConfirm('Regerar /etc/unbound/includes/blocked_domains.conf e recarregar o Unbound? O DNS pode ficar lento por ~1s.', 'Aplicar mudanças?');
        if (!ok) return;
        btnApply.disabled = true;
        btnApply.querySelector('span').textContent = 'Aplicando...';
        try {
            const fd = new FormData();
            fd.append('action', 'apply_blocklists');
            const res = await fetch('api/blocklist_apply.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                toast('Configuração aplicada — Unbound recarregado.', 'success');
                applyBanner.style.display = 'none';
                // Re-captura novo estado base
                lastBlockFlags.clear();
                await loadSources();
            } else {
                toast('Falha: ' + (data.message || 'erro desconhecido'), 'error');
            }
        } catch (err) {
            toast('Falha ao aplicar: ' + err.message, 'error');
        } finally {
            btnApply.disabled = false;
            btnApply.querySelector('span').textContent = 'Aplicar & Recarregar Unbound';
        }
    });

    // ============ BUSCA (Tab 2) ============
    let searchInited = false;
    let searchPage = 1, searchPerPage = 25, searchQ = '', searchCat = '';
    let debounce;

    function maybeFetchSearch() {
        if (!searchInited) { searchInited = true; fetchSearch(); }
    }

    async function fetchSearch() {
        const body = document.getElementById('searchBody');
        body.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr>`;
        const params = new URLSearchParams({ q: searchQ, page: searchPage, per_page: searchPerPage });
        if (searchCat) params.set('category', searchCat);
        try {
            const res = await fetch(`/api/v1/blocklist/search?${params}`, { headers: H });
            const data = await res.json();
            renderSearch(data);
        } catch (err) {
            body.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-red-500 text-xs uppercase font-black tracking-widest">Erro: ${err.message}</td></tr>`;
        }
    }

    function renderSearch(data) {
        const body = document.getElementById('searchBody');
        document.getElementById('searchTotal').textContent = (data.filtered || 0).toLocaleString('pt-BR');
        document.getElementById('searchPage').textContent = `${data.page || 1}/${data.total_pages || 1}`;
        // Filtra Judicial no client (defesa em profundidade — fontes ANATEL não devem aparecer aqui)
        const rows = (data.domains || []).filter(d => true); // search já filtra por category quando setado
        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Nenhum domínio encontrado</td></tr>`;
        } else {
            const start = (data.page - 1) * data.per_page;
            body.innerHTML = rows.map((d, i) => `
                <tr>
                    <td class="text-slate-500 font-mono text-xs">${start + i + 1}</td>
                    <td class="font-mono text-sm">${escapeHtml(d)}</td>
                    <td class="text-[10px] text-slate-500 font-black uppercase tracking-widest">—</td>
                    <td>—</td>
                </tr>
            `).join('');
        }
        renderSearchPagination(data);
    }

    function renderSearchPagination(data) {
        const info = document.getElementById('searchPaginationInfo');
        const ctrl = document.getElementById('searchPaginationCtrl');
        const total = data.filtered || 0, page = data.page || 1, totalPages = data.total_pages || 1;
        info.textContent = total ? `${((page - 1) * data.per_page + 1).toLocaleString('pt-BR')}–${Math.min(page * data.per_page, total).toLocaleString('pt-BR')} de ${total.toLocaleString('pt-BR')}` : '—';
        let html = '';
        if (totalPages > 1) {
            const prev = Math.max(1, page - 1), next = Math.min(totalPages, page + 1);
            html += `<button class="glass-btn text-[10px] uppercase font-black" ${page<=1?'disabled':''} data-go="${prev}">‹</button>`;
            html += `<span class="text-[10px] uppercase font-black text-slate-500">${page} / ${totalPages}</span>`;
            html += `<button class="glass-btn text-[10px] uppercase font-black" ${page>=totalPages?'disabled':''} data-go="${next}">›</button>`;
        }
        ctrl.innerHTML = html;
        ctrl.querySelectorAll('[data-go]').forEach(b => b.addEventListener('click', () => {
            searchPage = parseInt(b.dataset.go);
            fetchSearch();
        }));
    }

    document.getElementById('searchInput').addEventListener('input', (e) => {
        clearTimeout(debounce);
        searchQ = e.target.value.trim();
        searchPage = 1;
        debounce = setTimeout(fetchSearch, 300);
    });
    document.getElementById('categoryFilter').addEventListener('change', (e) => {
        searchCat = e.target.value;
        searchPage = 1;
        fetchSearch();
    });
    document.getElementById('perPageSelect').addEventListener('change', (e) => {
        searchPerPage = parseInt(e.target.value);
        searchPage = 1;
        fetchSearch();
    });

    // ============ EXCEÇÕES (Tab 3) ============
    async function loadExceptions() {
        const body = document.getElementById('exceptionsBody');
        body.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Carregando...</td></tr>`;
        try {
            const res = await fetch('/api/v1/blocklist/exceptions', { headers: H });
            const data = await res.json();
            document.getElementById('exCount').textContent = data.count || 0;
            renderExceptions(data.exceptions || []);
        } catch (err) {
            body.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-red-500 text-xs uppercase font-black tracking-widest">Erro: ${err.message}</td></tr>`;
        }
    }

    function renderExceptions(list) {
        const body = document.getElementById('exceptionsBody');
        if (!list.length) {
            body.innerHTML = `<tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Nenhuma exceção cadastrada</td></tr>`;
            return;
        }
        body.innerHTML = list.map(ex => `
            <tr>
                <td class="font-mono text-sm font-bold">${escapeHtml(ex.domain)}</td>
                <td class="text-[12px] text-slate-500">${escapeHtml(ex.reason || '—')}</td>
                <td class="text-[10px] text-slate-500 font-black uppercase tracking-widest">${escapeHtml(ex.created_by || '—')}</td>
                <td class="text-[11px] text-slate-500">${fmtAge(ex.created_at)}</td>
                <td class="text-right">
                    ${CAN_WRITE ? `<button class="text-red-500 hover:text-red-700 btn-del-ex" data-domain="${escapeHtml(ex.domain)}" title="Remover">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path></svg>
                    </button>` : ''}
                </td>
            </tr>
        `).join('');
    }

    document.getElementById('exceptionsBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-del-ex');
        if (!btn) return;
        const domain = btn.dataset.domain;
        const ok = await customConfirm(`Remover exceção <code>${domain}</code>?`, 'Remover?');
        if (!ok) return;
        try {
            const res = await fetch(`/api/v1/blocklist/exceptions/${encodeURIComponent(domain)}`, { method: 'DELETE', headers: H });
            const data = await res.json();
            if (data.removed) {
                toast(`Exceção ${domain} removida`, 'success');
                applyBanner && (applyBanner.style.display = '');
                await loadExceptions();
            } else {
                toast('Domínio não encontrado', 'warning');
            }
        } catch (err) {
            toast('Falha: ' + err.message, 'error');
        }
    });

    const formAdd = document.getElementById('formAddException');
    if (formAdd) formAdd.addEventListener('submit', async (e) => {
        e.preventDefault();
        const domain = document.getElementById('exDomain').value.trim().toLowerCase();
        const reason = document.getElementById('exReason').value.trim();
        try {
            const res = await fetch('/api/v1/blocklist/exceptions', {
                method: 'POST', headers: HJ,
                body: JSON.stringify({ domain, reason }),
            });
            const data = await res.json();
            if (data.added) {
                toast(`Exceção ${domain} adicionada`, 'success');
                document.getElementById('exDomain').value = '';
                document.getElementById('exReason').value = '';
                applyBanner && (applyBanner.style.display = '');
                await loadExceptions();
            } else {
                toast(data.added === false ? 'Domínio já existe na allowlist' : 'Falha ao adicionar', 'warning');
            }
        } catch (err) {
            toast('Falha: ' + err.message, 'error');
        }
    });

    // ============ UTIL ============
    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function toast(msg, type = 'info') {
        if (window.AppUI && typeof window.AppUI.toast === 'function') window.AppUI.toast(msg, type);
        else console.log('[' + type + ']', msg);
    }

    // ============ BULK / CSV (D.2) ============
    const exImportFile = document.getElementById('exImportFile');
    const exBulkStatus = document.getElementById('exBulkStatus');
    const exExportLink = document.getElementById('exExportLink');
    // Export precisa de JWT no header — converte clique em fetch+blob
    if (exExportLink) {
        exExportLink.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                const r = await fetch('/api/v1/blocklist/exceptions/export.csv', { headers: H });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const blob = await r.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = 'allowlist.csv';
                document.body.appendChild(a); a.click(); a.remove();
                URL.revokeObjectURL(url);
            } catch (err) {
                toast('Falha export: ' + err.message, 'error');
            }
        });
    }
    if (exImportFile) {
        exImportFile.addEventListener('change', async (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            exBulkStatus.textContent = `Lendo ${file.name}...`;
            try {
                const text = await file.text();
                // Parse: 1 domínio por linha. Aceita CSV simples (1ª coluna se houver vírgula).
                const domains = text.split(/\r?\n/).map(l => {
                    const trimmed = l.trim();
                    if (!trimmed || trimmed.startsWith('#') || trimmed === 'domain') return '';
                    const firstCol = trimmed.split(',')[0].trim();
                    return firstCol.replace(/^["']|["']$/g, '');
                }).filter(Boolean);
                if (!domains.length) { exBulkStatus.textContent = 'Arquivo vazio.'; return; }
                exBulkStatus.textContent = `Enviando ${domains.length} domínios...`;
                const r = await fetch('/api/v1/blocklist/exceptions/bulk', {
                    method: 'POST', headers: HJ,
                    body: JSON.stringify({ domains, reason: 'CSV import — ' + file.name }),
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                exBulkStatus.textContent = `+${d.added} adicionados · ${d.skipped_dup} duplicados · ${d.skipped_invalid} inválidos`;
                toast(`${d.added} domínios adicionados à allowlist`, 'success');
                await loadExceptions();
            } catch (err) {
                exBulkStatus.textContent = 'Falha: ' + err.message;
                toast('Falha import: ' + err.message, 'error');
            } finally {
                exImportFile.value = '';
            }
        });
    }

    // boot
    loadSources();
})();
</script>

</body>
</html>
