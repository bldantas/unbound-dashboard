<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'live_stream.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('live_stream.title') ?> - Unbound DNS</title>
    <meta name="description" content="Feed em tempo real de queries DNS via WebSocket — vê cada consulta no momento que acontece.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('live_stream.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6 flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <?= t('live_stream.title') ?>
                    </h1>
                    <p class="page-subtitle"><?= t('live_stream.subtitle') ?></p>
                </div>
                <div class="flex items-center gap-3">
                    <span id="streamStatus" class="text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full bg-slate-200 dark:bg-white/5 text-slate-500">Desconectado</span>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="streamPause" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-300 dark:bg-slate-700 rounded-full peer-checked:bg-amber-500 transition-colors relative">
                            <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest">Pausar</span>
                    </label>
                    <button type="button" id="streamClear" class="glass-btn text-[10px] uppercase font-black">Limpar</button>
                </div>
            </header>

            <!-- Filtro -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Filtro (IP ou domínio)</label>
                        <input type="text" id="streamFilter" placeholder="parcial — case-insensitive" class="glass-input w-full mt-1 font-mono">
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Ação</label>
                        <select id="streamAction" class="glass-input w-full mt-1 text-xs">
                            <option value="">Todas</option>
                            <option value="blocked">blocked</option>
                            <option value="resolved">resolved</option>
                            <option value="nxdomain_upstream">nxdomain_upstream</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Estatísticas</label>
                        <p class="text-xs font-mono text-slate-700 dark:text-slate-300 mt-1.5"><span id="streamCount">0</span> total · <span id="streamRate">0</span> q/s</p>
                    </div>
                </div>
            </div>

            <!-- Feed -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead><tr>
                            <th class="w-24">Hora</th>
                            <th>IP</th>
                            <th>Domínio</th>
                            <th class="w-16">Tipo</th>
                            <th class="w-32">Ação</th>
                        </tr></thead>
                        <tbody id="streamBody">
                            <tr><td colspan="5" class="px-6 py-20 text-center text-slate-500 text-xs font-black tracking-widest uppercase">Conectando...</td></tr>
                        </tbody>
                    </table>
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
    if (!JWT) {
        document.getElementById('streamStatus').textContent = 'Sem JWT';
        return;
    }

    const proto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const url = `${proto}//${window.location.host}/api/v1/ws/queries?token=${encodeURIComponent(JWT)}`;

    const statusEl = document.getElementById('streamStatus');
    const body = document.getElementById('streamBody');
    const filterEl = document.getElementById('streamFilter');
    const actionEl = document.getElementById('streamAction');
    const pauseEl = document.getElementById('streamPause');
    const clearBtn = document.getElementById('streamClear');
    const countEl = document.getElementById('streamCount');
    const rateEl = document.getElementById('streamRate');

    const MAX_ROWS = 200;
    let total = 0;
    let lastSecond = Math.floor(Date.now() / 1000);
    let countInWindow = 0;
    let ws = null;
    let reconnectDelay = 1000;
    let initialized = false;

    function escHtml(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    function setStatus(text, color) {
        statusEl.textContent = text;
        statusEl.className = `text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full ${color}`;
    }

    function actionBadge(a) {
        if (a === 'blocked') return '<span class="text-[10px] font-black text-red-500 bg-red-500/10 px-2 py-0.5 rounded-full">BLOCKED</span>';
        if (a === 'resolved') return '<span class="text-[10px] font-black text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full">resolved</span>';
        return `<span class="text-[10px] font-black text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded-full">${escHtml(a)}</span>`;
    }

    function appendRow(ev) {
        // Filtro client-side
        const f = (filterEl.value || '').trim().toLowerCase();
        if (f && !ev.domain.toLowerCase().includes(f) && !ev.client_ip.toLowerCase().includes(f)) return;
        const aFilter = actionEl.value;
        if (aFilter && ev.action !== aFilter) return;

        if (!initialized) {
            body.innerHTML = '';
            initialized = true;
        }

        const d = new Date(ev.timestamp * 1000);
        const time = d.toLocaleTimeString('pt-BR', { hour12: false }) + '.' + String(d.getMilliseconds()).padStart(3, '0').slice(0,3);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="font-mono text-[10px] text-slate-500">${time}</td>
            <td class="font-mono text-xs text-blue-500 dark:text-blue-400">${escHtml(ev.client_ip)}</td>
            <td class="font-bold text-xs">${escHtml(ev.domain)}</td>
            <td class="font-mono text-[10px] text-slate-500">${escHtml(ev.query_type)}</td>
            <td>${actionBadge(ev.action)}</td>
        `;
        // Highlight visual de novo
        tr.style.background = 'rgba(16,185,129,0.08)';
        setTimeout(() => { tr.style.transition = 'background 600ms'; tr.style.background = ''; }, 100);

        body.insertBefore(tr, body.firstChild);
        while (body.children.length > MAX_ROWS) body.removeChild(body.lastChild);
    }

    function connect() {
        setStatus('Conectando...', 'bg-amber-500/10 text-amber-500');
        try {
            ws = new WebSocket(url);
        } catch (e) {
            setStatus('Erro: ' + e.message, 'bg-red-500/10 text-red-500');
            return;
        }

        ws.onopen = () => {
            setStatus('Conectado', 'bg-emerald-500/10 text-emerald-500');
            reconnectDelay = 1000;
        };
        ws.onclose = () => {
            setStatus('Desconectado — reconectando...', 'bg-red-500/10 text-red-500');
            setTimeout(connect, reconnectDelay);
            reconnectDelay = Math.min(reconnectDelay * 2, 15000);
        };
        ws.onerror = () => { /* close será chamado depois */ };
        ws.onmessage = (msg) => {
            if (pauseEl.checked) return;
            try {
                const data = JSON.parse(msg.data);
                if (data.type === 'query') {
                    total++;
                    countEl.textContent = total.toLocaleString('pt-BR');
                    const nowSec = Math.floor(Date.now() / 1000);
                    if (nowSec === lastSecond) countInWindow++;
                    else { rateEl.textContent = countInWindow; lastSecond = nowSec; countInWindow = 1; }
                    appendRow(data);
                }
            } catch (_) { /* ignore */ }
        };
    }

    clearBtn.addEventListener('click', () => {
        body.innerHTML = '<tr><td colspan="5" class="px-6 py-20 text-center text-slate-500 text-xs italic">Aguardando próximas queries...</td></tr>';
        initialized = false;
        total = 0;
        countEl.textContent = '0';
    });

    connect();
})();
</script>

</body>
</html>
