<!-- includes/topbar.php -->
<?php
$tb_isOnline = false;
$tb_uptime = '---';

if (isset($isUnboundRunning) && isset($uptimeHuman)) {
    $tb_isOnline = $isUnboundRunning;
    $tb_uptime = $uptimeHuman;
} else {
    try {
        $statsFile = __DIR__ . '/../data/latest_stats.json';
        if (file_exists($statsFile)) {
            $cache = json_decode(file_get_contents($statsFile), true);
            if ($cache) {
                $tb_isOnline = $cache['online'] ?? false;
                $tb_uptime = $cache['uptime_human'] ?? '---';
            }
        }
    } catch (\Exception $e) {}
}
?>
<header class="px-8 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-100/80 dark:bg-slate-900/40 flex items-center justify-between sticky top-0 z-50 backdrop-blur-md w-full transition-colors duration-300">
    <div class="flex items-center gap-4">
        <!-- Botão Abrir/Fechar Sidebar -->
        <button id="sidebarToggle" class="p-2 rounded-xl bg-slate-200 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-white/10 transition-all shadow-sm">
            <svg id="iconSidebarOpen" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            <svg id="iconSidebarClose" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"></path></svg>
        </button>
        
        <div>
            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest block leading-none mb-1">Unbound Dashboard</span>
            <h2 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider"><?= $pageTitle ?? 'Visão Geral' ?></h2>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <!-- Status do Serviço -->
        <div class="hidden sm:flex items-center gap-3 pr-4 border-r border-slate-900/10 dark:border-white/5 mr-1">
            <div class="text-right">
                <p class="text-[10px] font-black <?= $tb_isOnline ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' ?> uppercase tracking-widest leading-none mb-1">
                    <?= $tb_isOnline ? 'Sistema Online' : 'Sistema Offline' ?>
                </p>
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter">Uptime: <span class="text-slate-700 dark:text-slate-300 font-mono"><?= htmlspecialchars($tb_uptime) ?></span></p>
            </div>
            <div class="w-2 h-2 rounded-full <?= $tb_isOnline ? 'bg-emerald-500 dark:bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.5)] animate-pulse' : 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]' ?>"></div>
        </div>

        <!-- Seletor de Tema (Sol/Lua) -->
        <button id="themeToggle" class="p-2.5 rounded-2xl bg-slate-200 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-white/10 transition-all shadow-sm group">
            <!-- Ícone Sol -->
            <svg id="sunIcon" class="w-5 h-5 hidden group-hover:rotate-45 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"></path>
            </svg>
            <!-- Ícone Lua -->
            <svg id="moonIcon" class="w-5 h-5 hidden group-hover:-rotate-12 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
            </svg>
        </button>

        <div class="h-8 w-px bg-slate-900/10 dark:bg-white/5 mx-2"></div>

        <!-- Perfil / Dropdown Simples -->
        <div class="flex items-center gap-3 pl-2">
            <div class="text-right hidden sm:block">
                <p class="text-[10px] font-black text-slate-900 dark:text-white leading-none mb-1"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></p>
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter"><?= strtoupper($_SESSION['role'] ?? 'Admin') ?></p>
            </div>
            <div class="w-10 h-10 rounded-2xl bg-blue-600/10 border border-blue-500/20 text-blue-500 flex items-center justify-center font-black text-sm">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
        </div>
    </div>
</header>

<script>
    const btnSidebar = document.getElementById('sidebarToggle');
    const btnTheme = document.getElementById('themeToggle');
    const sidebar = document.getElementById('mainSidebar');
    const iconOpen = document.getElementById('iconSidebarOpen');
    const iconClose = document.getElementById('iconSidebarClose');

    // --- SIDEBAR LOGIC ---
    function updateSidebarUI(isCollapsed) {
        if (isCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.style.width = '0px';
            sidebar.style.minWidth = '0px';
            sidebar.style.opacity = '0';
            iconOpen.classList.remove('hidden');
            iconClose.classList.add('hidden');
        } else {
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.style.width = '256px'; // w-64
            sidebar.style.minWidth = '256px';
            sidebar.style.opacity = '1';
            iconOpen.classList.add('hidden');
            iconClose.classList.remove('hidden');
        }
    }

    btnSidebar.addEventListener('click', () => {
        const currentlyCollapsed = sidebar.classList.contains('sidebar-collapsed');
        const newState = !currentlyCollapsed;
        localStorage.setItem('sidebar_collapsed', newState);
        updateSidebarUI(newState);
    });

    // Inicializar Sidebar
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        updateSidebarUI(true);
    }

    // --- THEME LOGIC ---
    const sunIcon = document.getElementById('sunIcon');
    const moonIcon = document.getElementById('moonIcon');

    function updateThemeUI(theme) {
        const root = document.documentElement;
        if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            root.classList.add('dark');
            sunIcon.classList.remove('hidden');
            moonIcon.classList.add('hidden');
        } else {
            root.classList.remove('dark');
            sunIcon.classList.add('hidden');
            moonIcon.classList.remove('hidden');
        }
    }

    btnTheme.addEventListener('click', () => {
        const isDark = document.documentElement.classList.contains('dark');
        const newTheme = isDark ? 'light' : 'dark';
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);
    });

    // Inicializar Temas
    updateThemeUI(localStorage.getItem('theme') || 'system');

    // Escutar mudança de sistema
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (localStorage.getItem('theme') === 'system') {
            updateThemeUI('system');
        }
    });

</script>
