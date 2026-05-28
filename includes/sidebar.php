<?php
// includes/sidebar.php — v2.112 reorganizada em 7 seções data-driven.
//
// Cada seção é renderizada via loop sobre $sidebarSections. Cada item tem
// `requires` que define quem vê:
//   - null            → qualquer user autenticado
//   - 'admin'         → admin (qualquer org_id)
//   - 'global_admin'  → admin sem org_id (system admin)
//   - 'cap:NAME'      → require capability X (rbac.py)
//
// Modos de exibição (modo NOC/TV):
//   - full      → 256px com texto                                (default)
//   - icon-only → 64px só ícones, flyout no hover mostra label   (v2.112+)
//   - hidden    → 0px (sumiu)                                    (mobile/manual)
//
// Toggle no topbar cicla full → icon-only → hidden → full.

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/AlertManager.php';
require_once __DIR__ . '/../src/ApiClient.php';
require_once __DIR__ . '/../src/I18n.php';

$sidebarIsAdmin = \App\Auth::isAdmin();
// Admin global = admin sem org_id. Esconde itens infra-only de admin org-scoped.
$sidebarIsGlobalAdmin = \App\Auth::isGlobalAdmin();
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

    // Badge "update disponível" — cache PHP-side de 60s.
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

// Helper: avalia se o user atual pode ver o item.
$canSee = function ($requires) use ($sidebarIsAdmin, $sidebarIsGlobalAdmin) {
    if (!$requires) return true;
    if ($requires === 'admin') return $sidebarIsAdmin;
    if ($requires === 'global_admin') return $sidebarIsGlobalAdmin;
    if (str_starts_with($requires, 'cap:')) {
        $cap = substr($requires, 4);
        return \App\Auth::can($cap);
    }
    return false;
};

// ============================================================
// Estrutura de seções — 38 itens em 7 grupos temáticos
// ============================================================
// Convenção pros SVGs: só o `<path>` interno; a tag <svg> é montada
// uniformemente no template.

// Ícones reusados (compactos pra ler)
$ICON = [
    'home'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
    'chart'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
    'lightning'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
    'document'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
    'bell'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
    'shieldcheck'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
    'server'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
    'shieldlock'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
    'check'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'globe'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    'building'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
    'warning'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
    'book'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
    'people'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
    'search'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
    'cloud'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>',
    'code'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>',
    'database'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>',
    'download'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>',
    'house'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12H3l9-9 9 9h-2M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7M5 12h14M9 21V13h6v8"/>',
    'gear'         => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
];

$sidebarSections = [
    [
        'label' => 'Operação',
        'items' => [
            ['href' => 'index.php',         'label' => t('sidebar.dashboard'),        'icon' => $ICON['home']],
            ['href' => 'history.php',       'label' => 'Histórico',                   'icon' => $ICON['chart']],
            ['href' => 'live_stream.php',   'label' => 'Stream ao Vivo',              'icon' => $ICON['lightning'],  'requires' => 'admin'],
            ['href' => 'logs.php',          'label' => t('sidebar.logs'),             'icon' => $ICON['document'],   'requires' => 'admin'],
            ['href' => 'alerts.php',        'label' => t('sidebar.alerts'),           'icon' => $ICON['bell'],       'requires' => 'admin', 'badge' => $activeAlerts],
            ['href' => 'notifications.php', 'label' => t('sidebar.notifications'),    'icon' => $ICON['bell'],       'requires' => 'admin'],
        ],
    ],
    [
        'label' => 'DNS & Blocklists',
        'items' => [
            ['href' => 'threats.php',         'label' => t('sidebar.threats'),         'icon' => $ICON['shieldlock'], 'requires' => 'admin'],
            ['href' => 'blocklist.php',       'label' => t('sidebar.blocklist_anatel'),'icon' => $ICON['warning'],    'requires' => 'admin'],
            ['href' => 'blocklists.php',      'label' => t('sidebar.blocklists'),      'icon' => $ICON['book'],       'requires' => 'admin', 'active_also' => ['catalog.php']],
            ['href' => 'client_policies.php', 'label' => t('sidebar.client_policies'), 'icon' => $ICON['people'],     'requires' => 'admin'],
            ['href' => 'dns_security.php',    'label' => t('sidebar.dns_security'),    'icon' => $ICON['shieldlock'], 'requires' => 'admin'],
            ['href' => 'geo_blocking.php',    'label' => 'Geo-Blocking',               'icon' => $ICON['globe'],      'requires' => 'admin'],
        ],
    ],
    [
        'label' => 'Análise',
        'items' => [
            ['href' => 'analytics.php',     'label' => t('sidebar.analytics'),       'icon' => $ICON['chart'],     'requires' => 'admin'],
            ['href' => 'query_search.php',  'label' => t('sidebar.query_search'),    'icon' => $ICON['search'],    'requires' => 'admin'],
            ['href' => 'anomalies.php',     'label' => t('sidebar.anomalies'),       'icon' => $ICON['lightning'], 'requires' => 'admin'],
            ['href' => 'performance.php',   'label' => t('sidebar.performance'),     'icon' => $ICON['lightning'], 'requires' => 'admin'],
            ['href' => 'audit.php',         'label' => t('sidebar.audit'),           'icon' => $ICON['shieldcheck'], 'requires' => 'admin'],
        ],
    ],
    [
        'label' => 'Cluster & Multi-host',
        'items' => [
            ['href' => 'cluster.php',         'label' => t('sidebar.cluster'),         'icon' => $ICON['server'],   'requires' => 'global_admin'],
            ['href' => 'hosts.php',           'label' => 'Hosts',                      'icon' => $ICON['house'],    'requires' => 'cap:config.write'],
            ['href' => 'external_health.php', 'label' => t('sidebar.external_health'), 'icon' => $ICON['globe'],    'requires' => 'admin'],
        ],
    ],
    [
        'label' => 'Acesso & Tenant',
        'items' => [
            ['href' => 'orgs.php',      'label' => 'Organizações',          'icon' => $ICON['building'],   'requires' => 'global_admin'],
            ['href' => 'sso.php',       'label' => t('sidebar.sso'),        'icon' => $ICON['shieldlock'], 'requires' => 'global_admin'],
            ['href' => 'approvals.php', 'label' => t('sidebar.approvals'),  'icon' => $ICON['check'],      'requires' => 'admin'],
        ],
    ],
    [
        'label' => 'Sistema',
        'items' => [
            ['href' => 'config.php' . ($hasUpdate ? '?tab=updates' : ''), 'label' => 'Configurações', 'icon' => $ICON['gear'], 'requires' => 'admin', 'active_key' => 'config.php', 'badge_html' => $hasUpdate ? '<span class="sidebar-update-badge ml-auto inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30 text-[9px] font-black uppercase tracking-widest relative" title="Nova versão disponível">↑ Update</span>' : ''],
            ['href' => 'health.php',         'label' => 'Saúde & Auditoria',  'icon' => $ICON['shieldcheck'], 'requires' => 'admin'],
            ['href' => 'observability.php',  'label' => t('sidebar.observability'), 'icon' => $ICON['chart'], 'requires' => 'admin'],
            ['href' => 'backup_offsite.php', 'label' => 'Backup S3',          'icon' => $ICON['cloud'],    'requires' => 'global_admin'],
            ['href' => 'exports.php',        'label' => 'Exportações',        'icon' => $ICON['download'], 'requires' => 'admin'],
            ['href' => 'api_docs.php',       'label' => 'API & SDKs',         'icon' => $ICON['code'],     'requires' => 'global_admin'],
            ['href' => 'changelog.php',      'label' => 'Changelog',          'icon' => $ICON['document'], 'requires' => 'admin'],
        ],
    ],
    [
        'label' => 'Ferramentas',
        'items' => [
            ['href' => 'dns_benchmark.php', 'label' => 'Benchmark DNS', 'icon' => $ICON['lightning'], 'requires' => 'admin'],
            ['href' => 'diagnostics.php',   'label' => 'Diagnóstico',   'icon' => $ICON['code'],      'requires' => 'admin'],
            ['href' => 'cache.php',         'label' => 'Cache DNS',     'icon' => $ICON['database'],  'requires' => 'admin'],
        ],
    ],
];

// Filtra items por permissão e pré-conta cada seção
foreach ($sidebarSections as &$_sect) {
    $_sect['items'] = array_values(array_filter($_sect['items'], function ($it) use ($canSee) {
        return $canSee($it['requires'] ?? null);
    }));
}
unset($_sect);
?>
<aside class="w-64 bg-slate-100 dark:bg-slate-900 border-r border-slate-900/10 dark:border-white/5 flex flex-col h-full overflow-hidden transition-all duration-300 z-[60]" id="mainSidebar">
    <!-- Logo / Brand -->
    <div class="p-6 border-b border-slate-900/10 dark:border-white/5 flex items-center gap-3 sidebar-brand">
        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-600/20 sidebar-brand-icon shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
        </div>
        <div class="sidebar-text-block min-w-0">
            <span class="text-lg font-black bg-clip-text text-transparent bg-gradient-to-r from-slate-900 to-slate-500 dark:from-white dark:to-slate-500 tracking-tighter">UNBOUND</span>
            <p class="text-[9px] text-slate-500 dark:text-slate-500 font-bold uppercase tracking-widest leading-none">Control Center</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-6 overflow-y-auto overflow-x-visible custom-scrollbar">
        <?php foreach ($sidebarSections as $section): ?>
        <?php if (empty($section['items'])) continue; /* esconde seção vazia */ ?>
        <div class="sidebar-section relative" data-section="<?= htmlspecialchars($section['label']) ?>">
            <p class="px-3 text-[10px] font-black text-slate-600 uppercase tracking-[0.2em] mb-3 flex items-center justify-between sidebar-section-header">
                <span class="sidebar-text"><?= htmlspecialchars($section['label']) ?></span>
                <span class="sidebar-text text-slate-500 dark:text-slate-600 normal-case tracking-normal font-bold"><?= count($section['items']) ?></span>
                <!-- Ícone de "se há mais itens" em modo icon-only -->
                <span class="sidebar-section-dot hidden">
                    <svg class="w-3 h-3 text-slate-500" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="2"/></svg>
                </span>
            </p>
            <div class="space-y-1 sidebar-section-items">
                <?php foreach ($section['items'] as $it):
                    $isActive = ($currentPage === ($it['active_key'] ?? $it['href']));
                    if (!$isActive && !empty($it['active_also'])) {
                        $isActive = in_array($currentPage, $it['active_also'], true);
                    }
                ?>
                <a href="<?= htmlspecialchars($it['href']) ?>" class="nav-link <?= $isActive ? 'active' : '' ?> <?= isset($it['badge']) && $it['badge'] > 0 ? 'flex justify-between' : '' ?>" data-label="<?= htmlspecialchars($it['label']) ?>" title="<?= htmlspecialchars($it['label']) ?>">
                    <?php if (isset($it['badge']) && $it['badge'] > 0): ?>
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $it['icon'] ?></svg>
                            <span class="sidebar-text"><?= htmlspecialchars($it['label']) ?></span>
                        </div>
                        <span class="sidebar-text bg-red-500/20 text-red-500 text-[10px] font-black px-2 py-0.5 rounded-full border border-red-500/20"><?= (int) $it['badge'] ?></span>
                    <?php else: ?>
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $it['icon'] ?></svg>
                        <span class="sidebar-text"><?= htmlspecialchars($it['label']) ?></span>
                        <?php if (!empty($it['badge_html'])): ?>
                            <span class="sidebar-text"><?= $it['badge_html'] /* já é HTML seguro vindo da config */ ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Flyout: exibido em modo icon-only no hover da seção -->
            <div class="sidebar-flyout" aria-hidden="true">
                <p class="text-[10px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-[0.2em] mb-2"><?= htmlspecialchars($section['label']) ?></p>
                <ul class="space-y-1">
                    <?php foreach ($section['items'] as $it): ?>
                        <li><a href="<?= htmlspecialchars($it['href']) ?>" class="block text-xs text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 py-1 px-2 rounded-md hover:bg-slate-100 dark:hover:bg-white/5"><?= htmlspecialchars($it['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="p-6 border-t border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-black/20 sidebar-footer">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 shrink-0">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-4h-2V7h2v6z"/></svg>
            </div>
            <div class="min-w-0 sidebar-text-block">
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

/* ============================================================
   Modo icon-only (v2.112+) — 64px de largura, só ícones + flyout
   ============================================================ */
#mainSidebar.sidebar-icon-only {
    width: 64px !important;
    min-width: 64px !important;
    opacity: 1 !important;
    overflow: visible !important;
}
#mainSidebar.sidebar-icon-only nav {
    overflow-x: visible;
    overflow-y: auto;
}
#mainSidebar.sidebar-icon-only .sidebar-text,
#mainSidebar.sidebar-icon-only .sidebar-text-block,
#mainSidebar.sidebar-icon-only .sidebar-section-header > .sidebar-text {
    display: none !important;
}
#mainSidebar.sidebar-icon-only .sidebar-section-header {
    justify-content: center;
    padding: 0;
    margin-bottom: 0.5rem;
}
#mainSidebar.sidebar-icon-only .sidebar-section-dot {
    display: inline-flex !important;
}
#mainSidebar.sidebar-icon-only .nav-link {
    justify-content: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
#mainSidebar.sidebar-icon-only .nav-link > div {
    justify-content: center;
}
#mainSidebar.sidebar-icon-only .sidebar-brand {
    padding: 1rem 0.75rem;
    justify-content: center;
}
#mainSidebar.sidebar-icon-only .sidebar-footer {
    padding: 1rem 0.5rem;
    text-align: center;
}

/* Flyout — escondido por padrão. Em icon-only, aparece ao hover na seção. */
.sidebar-flyout {
    display: none;
    position: absolute;
    left: 100%;
    top: 0;
    z-index: 70;
    min-width: 220px;
    margin-left: 8px;
    padding: 12px;
    background: rgb(248 250 252);
    border: 1px solid rgb(15 23 42 / 0.1);
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.05);
}
html.dark .sidebar-flyout {
    background: rgb(15 23 42);
    border-color: rgb(255 255 255 / 0.08);
    box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.5);
}
#mainSidebar.sidebar-icon-only .sidebar-section:hover .sidebar-flyout {
    display: block;
}
/* Tooltip-like flyout pra hover de NAV-LINK em icon-only (usa o data-label) */
#mainSidebar.sidebar-icon-only .nav-link {
    position: relative;
}
#mainSidebar.sidebar-icon-only .nav-link::after {
    content: attr(data-label);
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    margin-left: 12px;
    padding: 4px 10px;
    background: rgb(15 23 42);
    color: white;
    font-size: 11px;
    font-weight: 600;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.1s;
    z-index: 75;
}
#mainSidebar.sidebar-icon-only .nav-link:hover::after {
    opacity: 1;
}
</style>
