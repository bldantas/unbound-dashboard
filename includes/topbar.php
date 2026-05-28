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
<div id="sidebarBackdrop"></div>
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

        <!-- Notification Center -->
        <div class="relative">
            <button id="notifBell" class="p-2.5 rounded-2xl bg-slate-200 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-white/10 transition-all shadow-sm relative" title="Notificações">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                <span id="notifBadge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-black flex items-center justify-center">0</span>
            </button>
            <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 max-h-[400px] overflow-y-auto bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-2xl z-[180]">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-white/10">
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-200">Notificações</h4>
                    <button type="button" id="notifMarkRead" class="text-[9px] uppercase font-black text-blue-500 hover:underline">Marcar todas lidas</button>
                </div>
                <ul id="notifList" class="p-2">
                    <li class="px-3 py-4 text-center text-slate-500 text-xs">Carregando...</li>
                </ul>
            </div>
        </div>

        <!-- Seletor de Idioma (PT/EN) -->
        <?php
        if (!class_exists('\\App\\I18n')) {
            @require_once __DIR__ . '/../src/I18n.php';
        }
        $tb_locale = \App\I18n::current();
        ?>
        <form method="POST" action="/set_locale.php" class="inline-block" id="langForm">
            <input type="hidden" name="lang" id="langField" value="<?= $tb_locale === 'pt-BR' ? 'en' : 'pt-BR' ?>">
            <button type="submit" class="p-2.5 rounded-2xl bg-slate-200 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-white/10 transition-all shadow-sm text-[10px] font-black uppercase tracking-widest" title="<?= htmlspecialchars(\App\I18n::t('topbar.lang_toggle')) ?>">
                <?= $tb_locale === 'pt-BR' ? 'PT' : 'EN' ?>
            </button>
        </form>

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
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const iconOpen = document.getElementById('iconSidebarOpen');
    const iconClose = document.getElementById('iconSidebarClose');

    // Mobile breakpoint = Tailwind md (768px)
    const mqMobile = window.matchMedia('(max-width: 767px)');

    // --- SIDEBAR LOGIC (v2.112+) ---
    // Desktop: 3 modos ciclados pelo toggle do topbar:
    //   'full'      → 256px com texto
    //   'icon-only' → 64px só ícones (flyout no hover do mouse)
    //   'hidden'    → 0px (totalmente colapsada)
    // Persistido em localStorage como 'sidebar_mode'.
    // Mobile: drawer simples (open/closed), igual antes.
    function applyDesktopMode(mode) {
        sidebar.classList.remove('sidebar-collapsed', 'sidebar-icon-only');
        if (mode === 'hidden') {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.style.width = '0px';
            sidebar.style.minWidth = '0px';
            sidebar.style.opacity = '0';
            iconOpen.classList.remove('hidden');
            iconClose.classList.add('hidden');
        } else if (mode === 'icon-only') {
            sidebar.classList.add('sidebar-icon-only');
            sidebar.style.width = '';
            sidebar.style.minWidth = '';
            sidebar.style.opacity = '1';
            iconOpen.classList.add('hidden');
            iconClose.classList.remove('hidden');
        } else {
            // 'full' (default)
            sidebar.style.width = '256px';
            sidebar.style.minWidth = '256px';
            sidebar.style.opacity = '1';
            iconOpen.classList.add('hidden');
            iconClose.classList.remove('hidden');
        }
    }

    function updateSidebarUI(mobileCollapsedOrMode) {
        if (mqMobile.matches) {
            // Mobile: arg=true → drawer fechada
            const isCollapsed = mobileCollapsedOrMode === true || mobileCollapsedOrMode === 'hidden';
            if (isCollapsed) {
                sidebar.classList.remove('mobile-open');
                sidebarBackdrop?.classList.remove('mobile-open');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('mobile-open');
                sidebarBackdrop?.classList.add('mobile-open');
                document.body.style.overflow = 'hidden';
            }
            sidebar.style.width = '';
            sidebar.style.minWidth = '';
            sidebar.style.opacity = '';
            iconOpen.classList.toggle('hidden', !isCollapsed);
            iconClose.classList.toggle('hidden', isCollapsed);
            return;
        }
        // Desktop: arg pode ser bool (legacy) ou string ('full'|'icon-only'|'hidden')
        let mode = mobileCollapsedOrMode;
        if (typeof mode === 'boolean') mode = mode ? 'hidden' : 'full';
        if (!['full', 'icon-only', 'hidden'].includes(mode)) mode = 'full';
        applyDesktopMode(mode);
    }

    function getDesktopMode() {
        if (sidebar.classList.contains('sidebar-collapsed')) return 'hidden';
        if (sidebar.classList.contains('sidebar-icon-only')) return 'icon-only';
        return 'full';
    }

    function isCurrentlyCollapsed() {
        if (mqMobile.matches) return !sidebar.classList.contains('mobile-open');
        return sidebar.classList.contains('sidebar-collapsed');
    }

    btnSidebar.addEventListener('click', () => {
        if (mqMobile.matches) {
            updateSidebarUI(!isCurrentlyCollapsed());
            return;
        }
        // Desktop: cicla full → icon-only → hidden → full
        const current = getDesktopMode();
        const next = current === 'full' ? 'icon-only'
                   : current === 'icon-only' ? 'hidden'
                   : 'full';
        localStorage.setItem('sidebar_mode', next);
        updateSidebarUI(next);
    });

    // Click no backdrop fecha o drawer mobile
    sidebarBackdrop?.addEventListener('click', () => {
        if (mqMobile.matches) updateSidebarUI(true);
    });

    // Link da sidebar fecha o drawer (UX mobile)
    sidebar?.addEventListener('click', (e) => {
        if (mqMobile.matches && e.target.closest('a')) {
            updateSidebarUI(true);
        }
    });

    // Resize handler: alterna entre modos
    mqMobile.addEventListener('change', () => {
        // Reset estado ao trocar de breakpoint
        sidebar.classList.remove('mobile-open');
        sidebarBackdrop?.classList.remove('mobile-open');
        document.body.style.overflow = '';
        if (mqMobile.matches) {
            // Entrando em mobile: drawer fechada
            updateSidebarUI(true);
        } else {
            // Voltando pra desktop: restaura modo salvo (v2.112+)
            // Compat com chave legada sidebar_collapsed
            let savedMode = localStorage.getItem('sidebar_mode');
            if (!savedMode) {
                savedMode = localStorage.getItem('sidebar_collapsed') === 'true' ? 'hidden' : 'full';
            }
            updateSidebarUI(savedMode);
        }
    });

    // Inicializar
    if (mqMobile.matches) {
        updateSidebarUI(true);  // mobile sempre começa fechada
    } else {
        // Desktop: aplica modo salvo (v2.112+ usa sidebar_mode; compat com legacy)
        let savedMode = localStorage.getItem('sidebar_mode');
        if (!savedMode) {
            savedMode = localStorage.getItem('sidebar_collapsed') === 'true' ? 'hidden' : 'full';
        }
        if (savedMode !== 'full') updateSidebarUI(savedMode);
    }

    // --- COLLAPSE DE SEÇÕES (v2.113+) ---
    // Cada seção tem header clicável. Estado persistido em localStorage como
    // array de keys colapsadas. Seção que contém a página ativa força expandida
    // (independente do estado salvo) pra navegação não "esconder" onde o user está.
    const SIDEBAR_SECTIONS_KEY = 'sidebar_sections_collapsed';

    function loadCollapsedSections() {
        try {
            const raw = localStorage.getItem(SIDEBAR_SECTIONS_KEY);
            return new Set(raw ? JSON.parse(raw) : []);
        } catch { return new Set(); }
    }

    function saveCollapsedSections(set) {
        localStorage.setItem(SIDEBAR_SECTIONS_KEY, JSON.stringify([...set]));
    }

    function applySidebarSectionsState() {
        const collapsed = loadCollapsedSections();
        document.querySelectorAll('#mainSidebar .sidebar-section').forEach(sec => {
            const key = sec.dataset.sectionKey;
            if (!key) return;
            const hasActive = !!sec.querySelector('.nav-link.active');
            const shouldCollapse = collapsed.has(key) && !hasActive;
            sec.classList.toggle('collapsed', shouldCollapse);
            const header = sec.querySelector('.sidebar-section-header');
            if (header) header.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true');
        });
    }

    document.querySelectorAll('#mainSidebar .sidebar-section-header').forEach(header => {
        header.addEventListener('click', () => {
            // Em icon-only o pointer-events: none já bloqueia, mas guard extra:
            if (sidebar.classList.contains('sidebar-icon-only')) return;
            const sec = header.closest('.sidebar-section');
            const key = sec?.dataset.sectionKey;
            if (!key) return;
            const collapsed = loadCollapsedSections();
            const wasCollapsed = sec.classList.contains('collapsed');
            if (wasCollapsed) {
                collapsed.delete(key);
            } else {
                collapsed.add(key);
            }
            saveCollapsedSections(collapsed);
            sec.classList.toggle('collapsed', !wasCollapsed);
            header.setAttribute('aria-expanded', wasCollapsed ? 'true' : 'false');
        });
    });

    applySidebarSectionsState();

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

    // --- NOTIFICATION CENTER (D.3) ---
    (function() {
        const bell = document.getElementById('notifBell');
        const badge = document.getElementById('notifBadge');
        const dropdown = document.getElementById('notifDropdown');
        const list = document.getElementById('notifList');
        const markBtn = document.getElementById('notifMarkRead');
        if (!bell) return;

        const jwtMeta = document.querySelector('meta[name="api-jwt"]');
        const JWT = jwtMeta ? jwtMeta.content : '';
        if (!JWT) {
            bell.style.display = 'none';
            return;
        }
        const H = { 'Authorization': 'Bearer ' + JWT };

        function lastSeen() { return parseInt(localStorage.getItem('notif_last_seen') || '0', 10); }
        function setLastSeen(id) { localStorage.setItem('notif_last_seen', String(id)); }

        function esc(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

        function relTime(iso) {
            if (!iso) return '';
            const t = new Date(iso).getTime();
            if (isNaN(t)) return '';
            const diff = Math.floor((Date.now() - t) / 1000);
            if (diff < 60) return diff + 's';
            if (diff < 3600) return Math.floor(diff/60) + 'min';
            if (diff < 86400) return Math.floor(diff/3600) + 'h';
            return Math.floor(diff/86400) + 'd';
        }

        const SEV_COLORS = {
            'critical': 'text-red-500 bg-red-500/10',
            'warning':  'text-amber-500 bg-amber-500/10',
            'info':     'text-blue-500 bg-blue-500/10',
        };

        async function load() {
            try {
                const r = await fetch('/api/v1/notifications/feed?limit=30', { headers: H });
                if (!r.ok) return;
                const d = await r.json();
                const items = d.items || [];
                const ls = lastSeen();
                const unread = items.filter(i => i.id > ls);
                if (unread.length > 0) {
                    badge.textContent = unread.length > 99 ? '99+' : String(unread.length);
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
                if (!items.length) {
                    list.innerHTML = '<li class="px-3 py-6 text-center text-slate-500 text-xs italic">Nenhuma notificação pendente.</li>';
                    return;
                }
                list.innerHTML = items.map(it => {
                    const isUnread = it.id > ls;
                    const sev = (it.severity || 'info').toLowerCase();
                    const color = SEV_COLORS[sev] || SEV_COLORS.info;
                    const dot = isUnread ? '<span class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mr-2"></span>' : '<span class="w-2 h-2 shrink-0 mr-2"></span>';
                    return `<li><a href="${esc(it.url)}" class="flex items-start gap-2 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 transition">
                        ${dot}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[10px] font-black uppercase tracking-widest ${color.split(' ')[0]}">${esc(it.type)}</span>
                                <span class="text-[10px] text-slate-500 font-mono">${relTime(it.started_at)}</span>
                            </div>
                            <p class="text-xs text-slate-700 dark:text-slate-300 truncate mt-0.5">${esc(it.message) || '—'}</p>
                        </div>
                    </a></li>`;
                }).join('');
            } catch (e) { /* silent */ }
        }

        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
            if (!dropdown.classList.contains('hidden')) load();
        });
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== bell) dropdown.classList.add('hidden');
        });
        markBtn.addEventListener('click', async () => {
            // Server-side dismiss-all (era client-side via localStorage)
            try {
                const r = await fetch('/api/v1/notifications/dismiss-all', { method: 'POST', headers: H });
                if (r.ok) {
                    badge.classList.add('hidden');
                    load();
                    return;
                }
            } catch (e) { /* fallback abaixo */ }
            // Fallback se endpoint falhar (sem capability ou erro): client-side via localStorage
            const r2 = await fetch('/api/v1/notifications/feed?limit=1', { headers: H });
            if (r2.ok) {
                const d = await r2.json();
                const maxId = (d.items || []).reduce((m, i) => Math.max(m, i.id), 0);
                if (maxId > 0) setLastSeen(maxId);
                badge.classList.add('hidden');
                load();
            }
        });

        // WebSocket push em tempo real — bell atualiza sem esperar polling
        let ws = null;
        function connectWs() {
            try {
                const proto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                ws = new WebSocket(`${proto}//${window.location.host}/api/v1/ws/notifications?token=${encodeURIComponent(JWT)}`);
                ws.onmessage = (ev) => {
                    try {
                        const m = JSON.parse(ev.data);
                        if (m.type === 'alert') load();
                    } catch {}
                };
                ws.onclose = () => { ws = null; setTimeout(connectWs, 10000); };
                ws.onerror = () => { try { ws.close(); } catch {} };
            } catch (e) { /* polling continua */ }
        }
        connectWs();

        load();
        setInterval(load, 60000);  // fallback se WS cair
    })();
</script>
