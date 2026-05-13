<?php
require_once 'src/Auth.php';
require_once 'src/BlocklistManager.php';
use App\Auth;
use App\BlocklistManager;
Auth::check();

$currentPage = 'blocklist.php';

// Source ativa + metadata da blocklist
$bm = new BlocklistManager();
$blocklistSource = $bm->getBlocklistSource(); // stevenblack | hagezi_normal | hagezi_pro
$sourceLabels = [
    'stevenblack'   => ['name' => 'StevenBlack',       'desc' => 'Hosts unificado (Adware/Malware/Trackers)'],
    'hagezi_normal' => ['name' => 'Hagezi Normal',     'desc' => 'Multi NORMAL'],
    'hagezi_pro'    => ['name' => 'Hagezi Pro',        'desc' => 'Multi PRO (mais agressivo)'],
];
$sourceMeta = $sourceLabels[$blocklistSource] ?? ['name' => $blocklistSource, 'desc' => 'Fonte personalizada'];

$blocklistFile = __DIR__ . '/src/data/official_blocklist.conf';
$blocklistMtime = file_exists($blocklistFile) ? filemtime($blocklistFile) : 0;
$blocklistAgeSecs = $blocklistMtime > 0 ? (time() - $blocklistMtime) : null;
$fmtAge = function ($secs) {
    if ($secs === null) return 'nunca';
    if ($secs < 60) return $secs . 's atrás';
    if ($secs < 3600) return floor($secs / 60) . ' min atrás';
    if ($secs < 86400) return floor($secs / 3600) . 'h atrás';
    return floor($secs / 86400) . 'd atrás';
};
$blocklistAgeText = $fmtAge($blocklistAgeSecs);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Lista de Bloqueio - Unbound DNS</title>
    <meta name="description" content="Consulta e pesquisa de domínios na lista de bloqueio ativa (StevenBlack / Hagezi).">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Lista de Bloqueio Ativa";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <!-- Source info panel -->
            <div class="glass-panel border-l-4 border-orange-500 mb-6 border-slate-200 dark:border-white/5">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Origem Ativa</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                            <span class="text-orange-500 dark:text-orange-400"><?= htmlspecialchars($sourceMeta['name']) ?></span>
                            <span class="text-slate-500 dark:text-slate-400 font-medium"> · <?= htmlspecialchars($sourceMeta['desc']) ?></span>
                        </p>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Última atualização do arquivo:
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($blocklistAgeText) ?></span>
                            <?php if ($blocklistMtime > 0): ?>
                                <span class="text-slate-500 dark:text-slate-600">(<?= date('d/m/Y H:i', $blocklistMtime) ?>)</span>
                            <?php endif; ?>
                            <span class="ml-2 text-slate-500 dark:text-slate-600">— configure a fonte em <a href="config.php#tab-rpz" class="text-orange-500 hover:underline">Configurações → Lista de Bloqueios</a>.</span>
                        </p>
                    </div>
                    <?php if (\App\Auth::can('blocklist.write')): ?>
                        <button type="button" id="btnUpdateBlocklist"
                                class="glass-btn !bg-orange-600 !text-white text-[10px] uppercase font-black flex items-center gap-2"
                                title="Re-baixa a fonte ativa e regenera o arquivo">
                            <svg id="iconRefresh" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span id="btnUpdateBlocklistLabel">Atualizar Agora</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" id="statsCards">
                <div class="glass-panel group border-slate-200 dark:border-white/5 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 to-transparent dark:from-orange-500/10"></div>
                    <div class="relative">
                        <p class="metric-label">Total de Domínios</p>
                        <div class="metric-value text-orange-500" id="statTotal">
                            <div class="w-16 h-7 bg-slate-200 dark:bg-slate-800 rounded-lg animate-pulse"></div>
                        </div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2">Origem: <?= htmlspecialchars($sourceMeta['name']) ?></div>
                    </div>
                </div>
                <div class="glass-panel group border-slate-200 dark:border-white/5 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-transparent dark:from-blue-500/10"></div>
                    <div class="relative">
                        <p class="metric-label">Resultados da Busca</p>
                        <div class="metric-value text-blue-500" id="statFiltered">
                            <div class="w-16 h-7 bg-slate-200 dark:bg-slate-800 rounded-lg animate-pulse"></div>
                        </div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2">Domínios Encontrados</div>
                    </div>
                </div>
                <div class="glass-panel group border-slate-200 dark:border-white/5 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-transparent dark:from-purple-500/10"></div>
                    <div class="relative">
                        <p class="metric-label">Página Atual</p>
                        <div class="metric-value text-purple-500" id="statPage">
                            <div class="w-16 h-7 bg-slate-200 dark:bg-slate-800 rounded-lg animate-pulse"></div>
                        </div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2" id="statTotalPages">Carregando...</div>
                    </div>
                </div>
                <div class="glass-panel group border-slate-200 dark:border-white/5 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent dark:from-emerald-500/10"></div>
                    <div class="relative">
                        <p class="metric-label">TLDs Únicos</p>
                        <div class="metric-value text-emerald-500" id="statTlds">
                            <div class="w-16 h-7 bg-slate-200 dark:bg-slate-800 rounded-lg animate-pulse"></div>
                        </div>
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mt-2">Extensões Detectadas</div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-8">
                <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Search Input -->
                    <div class="flex-1 relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 group-focus-within:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            id="searchInput" 
                            placeholder="Pesquisar domínio... ex: bet, casino, poker, 123movies"
                            class="w-full pl-12 pr-4 py-3.5 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-500/40 focus:border-orange-500/40 transition-all duration-300 font-medium"
                            autocomplete="off"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2">
                            <button id="clearSearch" class="hidden p-1.5 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition-all duration-200" title="Limpar busca">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                            <kbd class="hidden sm:inline-flex items-center px-2 py-1 text-[9px] font-black text-slate-400 bg-slate-200/50 dark:bg-slate-700/50 rounded-lg border border-slate-300/50 dark:border-white/10 uppercase tracking-widest">/</kbd>
                        </div>
                    </div>

                    <!-- TLD Filter -->
                    <div class="relative">
                        <select id="tldFilter" class="appearance-none pl-4 pr-10 py-3.5 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500/40 focus:border-orange-500/40 transition-all duration-300 font-medium min-w-[160px] cursor-pointer">
                            <option value="">Todos TLDs</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>

                    <!-- Per Page -->
                    <div class="relative">
                        <select id="perPageSelect" class="appearance-none pl-4 pr-10 py-3.5 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-white/10 rounded-2xl text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500/40 focus:border-orange-500/40 transition-all duration-300 font-medium min-w-[130px] cursor-pointer">
                            <option value="25">25 por pág.</option>
                            <option value="50" selected>50 por pág.</option>
                            <option value="100">100 por pág.</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Active search indicator -->
                <div id="searchIndicator" class="hidden mt-4 flex items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500/10 text-orange-500 rounded-xl border border-orange-500/20 font-black uppercase tracking-widest text-[10px]">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        Filtro Ativo
                    </span>
                    <span class="text-slate-500 font-medium" id="searchSummary"></span>
                </div>
            </div>

            <!-- Top TLDs Distribution -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-8" id="tldDistribution">
                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-5 flex items-center gap-2">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                    Distribuição por TLD
                </h3>
                <div class="flex flex-wrap gap-2" id="tldBadges">
                    <div class="w-20 h-8 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse"></div>
                    <div class="w-16 h-8 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse"></div>
                    <div class="w-24 h-8 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse"></div>
                    <div class="w-14 h-8 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse"></div>
                    <div class="w-18 h-8 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse"></div>
                </div>
            </div>

            <!-- Domains Table -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                        Domínios Bloqueados — Origem: <?= htmlspecialchars($sourceMeta['name']) ?>
                    </h3>
                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest" id="tableInfo">Carregando...</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="glass-table" id="domainsTable">
                        <thead>
                            <tr>
                                <th class="w-16">#</th>
                                <th>Domínio</th>
                                <th class="w-32">TLD</th>
                                <th class="w-40 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody id="domainsBody">
                            <tr>
                                <td colspan="4" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-8 h-8 border-2 border-orange-500/30 border-t-orange-500 rounded-full animate-spin"></div>
                                        <span class="text-slate-500 text-xs font-black tracking-widest uppercase">Carregando lista...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest" id="paginationInfo">
                        —
                    </div>
                    <div class="flex items-center gap-2" id="paginationControls">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<script>
(function() {
    // State
    let currentPage = 1;
    let currentSearch = '';
    let currentTld = '';
    let currentPerPage = 50;
    let debounceTimer = null;
    let isLoading = false;
    let topTldsLoaded = false;

    // DOM Elements
    const searchInput     = document.getElementById('searchInput');
    const clearBtn        = document.getElementById('clearSearch');
    const tldFilter       = document.getElementById('tldFilter');
    const perPageSelect   = document.getElementById('perPageSelect');
    const domainsBody     = document.getElementById('domainsBody');
    const paginationInfo  = document.getElementById('paginationInfo');
    const paginationCtrl  = document.getElementById('paginationControls');
    const searchIndicator = document.getElementById('searchIndicator');
    const searchSummary   = document.getElementById('searchSummary');
    const tableInfo       = document.getElementById('tableInfo');

    // -- Fetch Data --
    async function fetchDomains() {
        if (isLoading) return;
        isLoading = true;
        showLoading();

        const params = new URLSearchParams({
            search: currentSearch,
            page: currentPage,
            per_page: currentPerPage,
            tld: currentTld,
        });

        try {
            const res = await fetch(`api/blocklist_search.php?${params}`);
            const data = await res.json();
            if (data.success) {
                renderStats(data);
                renderDomains(data);
                renderPagination(data);
                renderSearchIndicator(data);
                if (!topTldsLoaded) {
                    renderTopTlds(data.top_tlds);
                    populateTldFilter(data.top_tlds);
                    topTldsLoaded = true;
                }
            } else {
                showError(data.error || 'Erro desconhecido');
            }
        } catch (err) {
            showError('Falha na comunicação com o servidor.');
        } finally {
            isLoading = false;
        }
    }

    // -- Render Stats --
    function renderStats(data) {
        animateNumber('statTotal', data.total);
        animateNumber('statFiltered', data.filtered);
        document.getElementById('statPage').textContent = data.page;
        document.getElementById('statTotalPages').textContent = `De ${data.total_pages} páginas`;
        document.getElementById('statTlds').textContent = Object.keys(data.top_tlds).length + '+';
    }

    function animateNumber(id, target) {
        const el = document.getElementById(id);
        const start = parseInt(el.textContent.replace(/\D/g, '')) || 0;
        if (start === target) { el.textContent = target.toLocaleString('pt-BR'); return; }
        const duration = 400;
        const startTime = performance.now();
        function step(now) {
            const progress = Math.min((now - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(start + (target - start) * eased).toLocaleString('pt-BR');
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // -- Render Domains --
    function renderDomains(data) {
        if (data.domains.length === 0) {
            domainsBody.innerHTML = `
                <tr>
                    <td colspan="4" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <svg class="w-12 h-12 text-slate-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="text-slate-500 text-xs font-black tracking-widest uppercase">Nenhum domínio encontrado</span>
                            <span class="text-slate-400 text-[10px]">Tente ajustar os termos de busca</span>
                        </div>
                    </td>
                </tr>`;
            tableInfo.textContent = '0 resultados';
            return;
        }

        const startIndex = (data.page - 1) * data.per_page;
        let html = '';

        data.domains.forEach((domain, i) => {
            const index = startIndex + i + 1;
            const parts = domain.split('.');
            const tld = parts[parts.length - 1];
            const highlighted = currentSearch
                ? domain.replace(new RegExp(`(${escapeRegex(currentSearch)})`, 'gi'), '<mark class="bg-orange-500/20 text-orange-400 rounded px-0.5">$1</mark>')
                : domain;

            html += `
            <tr class="blocklist-row" style="animation-delay: ${i * 15}ms">
                <td class="px-6 py-3.5">
                    <span class="text-[10px] font-black text-slate-400 tabular-nums">${index.toLocaleString('pt-BR')}</span>
                </td>
                <td class="px-6 py-3.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full ${getTldColor(tld)} flex-shrink-0"></span>
                        <span class="font-mono text-xs text-slate-900 dark:text-white font-medium">${highlighted}</span>
                    </div>
                </td>
                <td class="px-6 py-3.5">
                    <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg ${getTldBadgeClass(tld)}">.${tld}</span>
                </td>
                <td class="px-6 py-3.5 text-right">
                    <span class="text-[10px] font-black text-red-500 bg-red-500/10 dark:bg-red-950/40 px-3 py-1.5 rounded-xl border border-red-500/20 uppercase tracking-widest inline-flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        BLOQUEADO
                    </span>
                </td>
            </tr>`;
        });

        domainsBody.innerHTML = html;
        tableInfo.textContent = `${data.filtered.toLocaleString('pt-BR')} domínios`;
    }

    // -- Render Pagination --
    function renderPagination(data) {
        const { page, total_pages, filtered, per_page } = data;
        const from = (page - 1) * per_page + 1;
        const to = Math.min(page * per_page, filtered);

        paginationInfo.textContent = filtered > 0 
            ? `Exibindo ${from.toLocaleString('pt-BR')} – ${to.toLocaleString('pt-BR')} de ${filtered.toLocaleString('pt-BR')}`
            : 'Nenhum resultado';

        let btns = '';

        // Prev
        btns += `<button class="pagination-btn ${page <= 1 ? 'opacity-30 cursor-not-allowed' : ''}" ${page <= 1 ? 'disabled' : ''} onclick="window.__blocklist.goPage(${page - 1})">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>`;

        // Page numbers
        const pages = getPageRange(page, total_pages);
        pages.forEach(p => {
            if (p === '...') {
                btns += `<span class="px-2 text-slate-500 text-xs font-bold">…</span>`;
            } else {
                btns += `<button class="pagination-btn ${p === page ? 'active' : ''}" onclick="window.__blocklist.goPage(${p})">${p}</button>`;
            }
        });

        // Next
        btns += `<button class="pagination-btn ${page >= total_pages ? 'opacity-30 cursor-not-allowed' : ''}" ${page >= total_pages ? 'disabled' : ''} onclick="window.__blocklist.goPage(${page + 1})">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>`;

        paginationCtrl.innerHTML = btns;
    }

    function getPageRange(current, total) {
        if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
        const pages = [];
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) pages.push(i);
            pages.push('...', total);
        } else if (current >= total - 3) {
            pages.push(1, '...');
            for (let i = total - 4; i <= total; i++) pages.push(i);
        } else {
            pages.push(1, '...');
            for (let i = current - 1; i <= current + 1; i++) pages.push(i);
            pages.push('...', total);
        }
        return pages;
    }

    // -- Render Search Indicator --
    function renderSearchIndicator(data) {
        const hasFilter = currentSearch || currentTld;
        searchIndicator.classList.toggle('hidden', !hasFilter);
        if (hasFilter) {
            const parts = [];
            if (currentSearch) parts.push(`"${currentSearch}"`);
            if (currentTld) parts.push(`.${currentTld}`);
            searchSummary.textContent = `${data.filtered.toLocaleString('pt-BR')} resultados para ${parts.join(' em ')}`;
        }
    }

    // -- Render Top TLDs --
    function renderTopTlds(tlds) {
        const container = document.getElementById('tldBadges');
        const colors = ['orange', 'blue', 'purple', 'emerald', 'red', 'cyan', 'pink', 'yellow', 'indigo', 'teal', 'rose', 'amber', 'lime', 'sky', 'violet'];
        let html = '';
        let i = 0;
        for (const [tld, count] of Object.entries(tlds)) {
            const color = colors[i % colors.length];
            html += `
            <button class="tld-chip group" data-tld="${tld}" onclick="window.__blocklist.filterTld('${tld}')" style="animation-delay: ${i * 40}ms">
                <span class="w-2 h-2 rounded-full bg-${color}-500 group-hover:scale-125 transition-transform"></span>
                <span class="font-black text-[10px] text-slate-700 dark:text-slate-300 uppercase tracking-wider">.${tld}</span>
                <span class="text-[9px] font-bold text-slate-400 tabular-nums">${count.toLocaleString('pt-BR')}</span>
            </button>`;
            i++;
        }
        container.innerHTML = html;
    }

    // -- Populate TLD Filter --
    function populateTldFilter(tlds) {
        let options = '<option value="">Todos TLDs</option>';
        for (const [tld, count] of Object.entries(tlds)) {
            options += `<option value="${tld}">.${tld} (${count.toLocaleString('pt-BR')})</option>`;
        }
        tldFilter.innerHTML = options;
    }

    // -- Helpers --
    function getTldColor(tld) {
        const map = { com: 'bg-blue-500', net: 'bg-purple-500', bet: 'bg-red-500', vip: 'bg-orange-500', 'com.br': 'bg-emerald-500', org: 'bg-cyan-500', cc: 'bg-pink-500', win: 'bg-yellow-500', top: 'bg-indigo-500', xyz: 'bg-teal-500' };
        return map[tld] || 'bg-slate-500';
    }

    function getTldBadgeClass(tld) {
        const map = {
            com: 'text-blue-500 bg-blue-500/10 border border-blue-500/20',
            net: 'text-purple-500 bg-purple-500/10 border border-purple-500/20',
            bet: 'text-red-500 bg-red-500/10 border border-red-500/20',
            vip: 'text-orange-500 bg-orange-500/10 border border-orange-500/20',
            org: 'text-cyan-500 bg-cyan-500/10 border border-cyan-500/20',
            cc: 'text-pink-500 bg-pink-500/10 border border-pink-500/20',
            win: 'text-yellow-500 bg-yellow-500/10 border border-yellow-500/20',
            top: 'text-indigo-500 bg-indigo-500/10 border border-indigo-500/20',
        };
        return map[tld] || 'text-slate-500 bg-slate-500/10 border border-slate-500/20';
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function showLoading() {
        domainsBody.innerHTML = `
            <tr>
                <td colspan="4" class="px-6 py-16 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-8 h-8 border-2 border-orange-500/30 border-t-orange-500 rounded-full animate-spin"></div>
                        <span class="text-slate-500 text-xs font-black tracking-widest uppercase">Buscando domínios...</span>
                    </div>
                </td>
            </tr>`;
    }

    function showError(msg) {
        domainsBody.innerHTML = `
            <tr>
                <td colspan="4" class="px-6 py-20 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span class="text-red-400 text-xs font-black tracking-widest uppercase">${msg}</span>
                    </div>
                </td>
            </tr>`;
    }

    // -- Event Listeners --
    searchInput.addEventListener('input', function() {
        clearBtn.classList.toggle('hidden', !this.value);
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentSearch = this.value.trim();
            currentPage = 1;
            fetchDomains();
        }, 350);
    });

    clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearBtn.classList.add('hidden');
        currentSearch = '';
        currentPage = 1;
        fetchDomains();
        searchInput.focus();
    });

    tldFilter.addEventListener('change', function() {
        currentTld = this.value;
        currentPage = 1;
        fetchDomains();
    });

    perPageSelect.addEventListener('change', function() {
        currentPerPage = parseInt(this.value);
        currentPage = 1;
        fetchDomains();
    });

    // Keyboard shortcut: / para focar no search
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.blur();
        }
    });

    // -- Public API --
    window.__blocklist = {
        goPage: (p) => {
            currentPage = p;
            fetchDomains();
            // Scroll to table
            document.getElementById('domainsTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
        },
        filterTld: (tld) => {
            if (currentTld === tld) {
                currentTld = '';
                tldFilter.value = '';
            } else {
                currentTld = tld;
                tldFilter.value = tld;
            }
            currentPage = 1;
            fetchDomains();
        }
    };

    // -- Botão "Atualizar Agora" (admin only) --
    const btnUpdate = document.getElementById('btnUpdateBlocklist');
    if (btnUpdate) {
        btnUpdate.addEventListener('click', async () => {
            const label = document.getElementById('btnUpdateBlocklistLabel');
            const icon = document.getElementById('iconRefresh');
            btnUpdate.disabled = true;
            label.textContent = 'Atualizando...';
            icon && icon.classList.add('animate-spin');
            try {
                const fd = new FormData();
                fd.append('action', 'update_blacklist');
                const res = await fetch('api/service_control.php', { method: 'POST', body: fd, cache: 'no-store' });
                const json = await res.json().catch(() => ({}));
                if (window.AppUI && typeof window.AppUI.toast === 'function') {
                    window.AppUI.toast(json.message || 'Sincronização iniciada.', json.success ? 'success' : 'error');
                } else {
                    alert(json.message || 'Sincronização iniciada.');
                }
                // Aguarda ~5s pra dar tempo do background script iniciar, depois recarrega
                // (a página vai pegar o mtime novo no PHP server-side).
                setTimeout(() => location.reload(), 5000);
            } catch (err) {
                if (window.AppUI && typeof window.AppUI.toast === 'function') {
                    window.AppUI.toast('Falha ao iniciar sincronização: ' + err.message, 'error');
                } else {
                    alert('Falha ao iniciar sincronização: ' + err.message);
                }
                btnUpdate.disabled = false;
                label.textContent = 'Atualizar Agora';
                icon && icon.classList.remove('animate-spin');
            }
        });
    }

    // -- Init --
    fetchDomains();
})();
</script>

<style>
    .blocklist-row {
        animation: fadeSlideIn 0.3s ease-out both;
    }
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .pagination-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 8px;
        font-size: 12px;
        font-weight: 900;
        border-radius: 12px;
        transition: all 0.2s;
        border: 1px solid transparent;
    }
    .pagination-btn:not(.active):not(:disabled) {
        color: rgb(148 163 184);
        background: transparent;
    }
    .pagination-btn:not(.active):not(:disabled):hover {
        color: rgb(249 115 22);
        background: rgba(249, 115, 22, 0.08);
        border-color: rgba(249, 115, 22, 0.15);
    }
    .pagination-btn.active {
        color: white;
        background: linear-gradient(135deg, rgb(249 115 22), rgb(234 88 12));
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        border-color: rgba(249, 115, 22, 0.3);
    }

    .tld-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 12px;
        border: 1px solid rgba(0,0,0,0.06);
        background: rgba(0,0,0,0.02);
        transition: all 0.2s;
        cursor: pointer;
        animation: fadeSlideIn 0.4s ease-out both;
    }
    .dark .tld-chip {
        border-color: rgba(255,255,255,0.06);
        background: rgba(255,255,255,0.03);
    }
    .tld-chip:hover {
        border-color: rgba(249, 115, 22, 0.3);
        background: rgba(249, 115, 22, 0.06);
        transform: translateY(-1px);
    }

    mark {
        background: rgba(249, 115, 22, 0.2) !important;
        color: rgb(249, 115, 22) !important;
        border-radius: 3px;
        padding: 0 2px;
    }
</style>

</body>
</html>
