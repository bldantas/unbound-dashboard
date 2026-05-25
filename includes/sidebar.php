<?php
// includes/sidebar.php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/AlertManager.php';
require_once __DIR__ . '/../src/ApiClient.php';

$sidebarIsAdmin = \App\Auth::isAdmin();
$activeAlerts = 0;
$hasUpdate = false;
if ($sidebarIsAdmin) {
    $alertCacheTtl = 30;
    $alertCacheTs = (int) ($_SESSION['sidebar_alert_count_ts'] ?? 0);
    if ($alertCacheTs > 0 && (time() - $alertCacheTs) < $alertCacheTtl) {
        $activeAlerts = (int) ($_SESSION['sidebar_alert_count'] ?? 0);
    } else {
        $alertManager = new \App\AlertManager();
        $activeAlerts = $alertManager->getActiveCount();
        $_SESSION['sidebar_alert_count'] = $activeAlerts;
        $_SESSION['sidebar_alert_count_ts'] = time();
    }

    // Badge "update disponível" — usa o cache Redis do worker update_checker
    // via /api/v1/updates/check. Cache PHP-side de 60s pra não bater na API
    // a cada navegação.
    $updateCacheTtl = 60;
    $updateCacheTs = (int) ($_SESSION['sidebar_update_check_ts'] ?? 0);
    if ($updateCacheTs > 0 && (time() - $updateCacheTs) < $updateCacheTtl) {
        $hasUpdate = !empty($_SESSION['sidebar_has_update']);
    } else {
        $jwt = $_SESSION['api_jwt'] ?? '';
        if ($jwt !== '') {
            $checkRes = \App\ApiClient::get('/api/v1/updates/check', $jwt);
            $hasUpdate = !empty($checkRes['ok']) && !empty($checkRes['data']['has_update']);
        }
        $_SESSION['sidebar_has_update'] = $hasUpdate;
        $_SESSION['sidebar_update_check_ts'] = time();
    }
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-64 bg-slate-100 dark:bg-slate-900 border-r border-slate-900/10 dark:border-white/5 flex flex-col h-full overflow-hidden transition-all duration-300 z-[60]" id="mainSidebar">
    <!-- Logo / Brand -->
    <div class="p-6 border-b border-slate-900/10 dark:border-white/5 flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-600/20">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
        </div>
        <div>
            <span class="text-lg font-black bg-clip-text text-transparent bg-gradient-to-r from-slate-900 to-slate-500 dark:from-white dark:to-slate-500 tracking-tighter">UNBOUND</span>
            <p class="text-[9px] text-slate-500 dark:text-slate-500 font-bold uppercase tracking-widest leading-none">Control Center</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-8 overflow-y-auto custom-scrollbar">
        
        <!-- MONITORING (visível para todos) -->
        <div>
            <p class="px-3 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-4">Monitoramento</p>
            <div class="space-y-1">
                <a href="index.php" class="nav-link <?= $currentPage == 'index.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span>Dashboard</span>
                </a>
                <a href="history.php" class="nav-link <?= $currentPage == 'history.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Histórico</span>
                </a>
                <?php if ($sidebarIsAdmin): ?>
                <a href="logs.php" class="nav-link <?= $currentPage == 'logs.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Logs</span>
                </a>
                <a href="alerts.php" class="nav-link <?= $currentPage == 'alerts.php' ? 'active' : '' ?> flex justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span>Alertas</span>
                    </div>
                    <?php if ($activeAlerts > 0): ?>
                        <span class="bg-red-500/20 text-red-500 text-[10px] font-black px-2 py-0.5 rounded-full border border-red-500/20"><?= $activeAlerts ?></span>
                    <?php endif; ?>
                </a>
                <a href="threats.php" class="nav-link <?= $currentPage == 'threats.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <span>Ameaças</span>
                </a>
                <a href="blocklist.php" class="nav-link <?= $currentPage == 'blocklist.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>Lista ANATEL</span>
                </a>
                <a href="blocklists.php" class="nav-link <?= ($currentPage == 'blocklists.php' || $currentPage == 'catalog.php') ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <span>Blocklists</span>
                </a>
                <a href="client_policies.php" class="nav-link <?= $currentPage == 'client_policies.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span>Pol. Cliente</span>
                </a>
                <a href="analytics.php" class="nav-link <?= $currentPage == 'analytics.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Analítico</span>
                </a>
                <a href="query_search.php" class="nav-link <?= $currentPage == 'query_search.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <span>Buscar Queries</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sidebarIsAdmin): ?>
        <!-- TOOLS (somente admin) -->
        <div>
            <p class="px-3 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-4">Ferramentas</p>
            <div class="space-y-1">
                <a href="dns_benchmark.php" class="nav-link <?= $currentPage == 'dns_benchmark.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>Benchmark DNS</span>
                </a>
                <a href="diagnostics.php" class="nav-link <?= $currentPage == 'diagnostics.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    <span>Diagnóstico</span>
                </a>
                <a href="cache.php" class="nav-link <?= $currentPage == 'cache.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                    <span>Cache DNS</span>
                </a>
                <a href="exports.php" class="nav-link <?= $currentPage == 'exports.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span>Exportações</span>
                </a>
            </div>
        </div>

        <!-- SYSTEM (somente admin) -->
        <div>
            <p class="px-3 text-[10px] font-black text-slate-400 dark:text-slate-600 uppercase tracking-[0.2em] mb-4">Sistema</p>
            <div class="space-y-1">
                <?php if (\App\Auth::can('config.write')): ?>
                <a href="hosts.php" class="nav-link <?= $currentPage == 'hosts.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12H3l9-9 9 9h-2M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7M5 12h14M9 21V13h6v8"></path></svg>
                    <span>Hosts</span>
                </a>
                <?php endif; ?>
                <a href="config.php<?= $hasUpdate ? '?tab=updates' : '' ?>" class="nav-link <?= $currentPage == 'config.php' ? 'active' : '' ?>" <?= $hasUpdate ? 'title="Nova versão disponível"' : '' ?>>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>Configurações</span>
                    <?php if ($hasUpdate): ?>
                        <span class="sidebar-update-badge ml-auto inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30 text-[9px] font-black uppercase tracking-widest relative" title="Nova versão disponível">
                            ↑ Update
                        </span>
                    <?php endif; ?>
                </a>
                <a href="health.php" class="nav-link <?= $currentPage == 'health.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <span>Saúde & Auditoria</span>
                </a>
                <a href="changelog.php" class="nav-link <?= $currentPage == 'changelog.php' ? 'active' : '' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Changelog</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="p-6 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-black/20">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-4h-2V7h2v6z"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                <a href="logout.php" class="text-[10px] text-slate-500 hover:text-red-400 font-black uppercase tracking-widest decoration-none">Sair do Sistema</a>
            </div>
        </div>
    </div>
</aside>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.1); }

/* Badge "Update" na sidebar — animação pulse pra chamar atenção sem ser intrusivo */
.sidebar-update-badge::after {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    border: 1px solid rgb(59 130 246 / 0.6);
    animation: sidebar-update-pulse 2s ease-in-out infinite;
    pointer-events: none;
}
@keyframes sidebar-update-pulse {
    0%, 100% { opacity: 0; transform: scale(1); }
    50%      { opacity: 1; transform: scale(1.08); }
}
</style>
