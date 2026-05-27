<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'geo_blocking.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?= t('geo_blocking.title') ?> - Unbound DNS</title>
    <meta name="description" content="Bloqueio de países inteiros via access-control do Unbound. CIDRs baixados de iwik.org.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('geo_blocking.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= t('geo_blocking.title') ?>
                </h1>
                <p class="page-subtitle"><?= t('geo_blocking.subtitle') ?></p>
            </header>

            <!-- Aviso master -->
            <div class="glass-panel border-l-4 border-amber-500 mb-6 border-slate-200 dark:border-white/5">
                <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-1">Atenção</p>
                <p class="text-xs text-slate-700 dark:text-slate-300">Habilitar o geo-blocking <b>reinicia o Unbound</b>. Toggle de país ou atualizações de CIDR só entram em vigor após clicar <b>Apply</b>. Em caso de falha o include é revertido pro estado anterior automaticamente.</p>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Habilitado</p>
                    <p id="kpiEnabled" class="text-3xl font-black text-slate-400 mt-1">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Países bloqueados</p>
                    <p id="kpiBlocked" class="text-3xl font-black text-rose-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">CIDRs no include</p>
                    <p id="kpiCidrs" class="text-3xl font-black text-cyan-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">IPv4 / IPv6</p>
                    <p id="kpiV4V6" class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-2">—</p>
                </div>
            </div>

            <!-- Master toggle + actions -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('geo_blocking.section_config') ?></h3>
                </div>
                <div class="p-6 space-y-4">
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">Habilitar geo-blocking</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">Quando desligado, o include é regenerado vazio (nenhum país é bloqueado).</p>
                        </div>
                        <input type="checkbox" id="setEnabled" class="w-5 h-5">
                    </label>
                    <label class="flex items-center justify-between cursor-pointer border-t border-slate-900/10 dark:border-white/5 pt-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white">Incluir IPv6 nos CIDRs</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">Listas IPv6 são bem maiores. Mantenha desligado se seus clientes são só IPv4.</p>
                        </div>
                        <input type="checkbox" id="setIncludeIpv6" class="w-5 h-5">
                    </label>
                    <div class="flex flex-wrap gap-2 justify-end border-t border-slate-900/10 dark:border-white/5 pt-4">
                        <button id="btnSaveSettings" class="glass-btn text-[10px] uppercase font-black" <?= $isAdmin ? '' : 'disabled' ?>>Salvar settings</button>
                        <button id="btnRefreshAll" class="glass-btn text-[10px] uppercase font-black" <?= $isAdmin ? '' : 'disabled' ?>>Atualizar CIDRs (todos)</button>
                        <button id="btnApply" class="glass-btn !bg-rose-600 !text-white text-[10px] uppercase font-black flex items-center gap-2" <?= $isAdmin ? '' : 'disabled' ?>>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Apply (regenera + restart Unbound)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Countries table -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('geo_blocking.section_countries') ?> (<span id="countryCount" class="text-rose-500">—</span>)</h3>
                    <div class="flex items-center gap-2">
                        <input type="text" id="newCC" placeholder="ISO-2 ex: CN" maxlength="2" class="glass-input text-xs uppercase font-mono w-24" <?= $isAdmin ? '' : 'disabled' ?>>
                        <input type="text" id="newName" placeholder="Nome (ex: China)" maxlength="80" class="glass-input text-xs w-44" <?= $isAdmin ? '' : 'disabled' ?>>
                        <button id="btnAddCountry" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black" <?= $isAdmin ? '' : 'disabled' ?>>+ Adicionar</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <th class="w-24">Código</th>
                                <th>País</th>
                                <th class="w-28 text-right">IPv4 CIDRs</th>
                                <th class="w-28 text-right">IPv6 CIDRs</th>
                                <th class="w-40">Atualizado</th>
                                <th class="w-32">Bloqueado</th>
                                <th class="w-40">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="countriesBody">
                            <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500 text-xs italic">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<?php include 'includes/custom_modals.php'; ?>

<script>
(function () {
    const jwtMeta = document.querySelector('meta[name="api-jwt"]');
    const JWT = jwtMeta ? jwtMeta.content : '';
    const H = { 'Authorization': 'Bearer ' + JWT };
    const HJ = { ...H, 'Content-Type': 'application/json' };
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    function ccToFlag(cc) {
        if (!cc || cc.length !== 2 || /[^A-Z]/i.test(cc)) return '🏳️';
        const cp = cc.toUpperCase().split('').map(c => 127397 + c.charCodeAt(0));
        return String.fromCodePoint(...cp);
    }
    function fmtInt(n) { return Number(n || 0).toLocaleString('pt-BR'); }
    function fmtRel(ts) {
        if (!ts) return '—';
        const diff = Math.floor(Date.now() / 1000) - ts;
        if (diff < 60) return 'agora';
        if (diff < 3600) return Math.floor(diff / 60) + 'min atrás';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
        return Math.floor(diff / 86400) + 'd atrás';
    }
    function esc(s) { return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    async function loadStatus() {
        try {
            const r = await fetch('/api/v1/geo-blocking/status', { headers: H });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            renderKpis(d);
            renderSettings(d.settings);
            renderTable(d.countries);
        } catch (e) {
            if (window.AppUI?.toast) window.AppUI.toast('Falha ao carregar status: ' + e.message, 'error');
        }
    }

    function renderKpis(d) {
        const enabled = d.settings.geo_blocking_enabled === '1';
        const el = document.getElementById('kpiEnabled');
        el.textContent = enabled ? 'ON' : 'OFF';
        el.className = 'text-3xl font-black mt-1 ' + (enabled ? 'text-emerald-500' : 'text-slate-400');
        document.getElementById('kpiBlocked').textContent = fmtInt(d.total_blocked);
        document.getElementById('kpiCidrs').textContent = fmtInt(d.preview_cidrs);
        document.getElementById('kpiV4V6').textContent = fmtInt(d.ipv4_count) + ' / ' + fmtInt(d.ipv6_count);
    }

    function renderSettings(s) {
        document.getElementById('setEnabled').checked = s.geo_blocking_enabled === '1';
        document.getElementById('setIncludeIpv6').checked = s.geo_blocking_include_ipv6 === '1';
    }

    function renderTable(countries) {
        const tbody = document.getElementById('countriesBody');
        document.getElementById('countryCount').textContent = fmtInt(countries.length);
        if (!countries.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-slate-500 text-xs italic">Nenhum país cadastrado. Adicione um país via ISO-2 (ex: CN, RU, KP).</td></tr>';
            return;
        }
        tbody.innerHTML = countries.map(c => {
            const flag = ccToFlag(c.country_code);
            const errBadge = c.last_error ? '<span title="' + esc(c.last_error) + '" class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded bg-red-500/15 text-red-500 border border-red-500/30 ml-1">err</span>' : '';
            return `
            <tr data-cc="${esc(c.country_code)}">
                <td class="font-mono text-base"><span class="text-xl mr-1">${flag}</span><span class="text-xs font-bold">${esc(c.country_code)}</span></td>
                <td class="text-xs font-bold">${esc(c.country_name)}${errBadge}</td>
                <td class="text-right font-mono text-xs">${fmtInt(c.ipv4_count)}</td>
                <td class="text-right font-mono text-xs">${fmtInt(c.ipv6_count)}</td>
                <td class="text-[11px] font-mono text-slate-500">${fmtRel(c.updated_at)}</td>
                <td>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="w-4 h-4 toggle-blocked" ${c.blocked ? 'checked' : ''} ${isAdmin ? '' : 'disabled'}>
                        <span class="text-[10px] font-black uppercase tracking-widest ${c.blocked ? 'text-rose-500' : 'text-slate-500'}">${c.blocked ? 'block' : 'inactive'}</span>
                    </label>
                </td>
                <td>
                    <button class="glass-btn text-[10px] uppercase font-black btn-refresh" ${isAdmin ? '' : 'disabled'} title="Re-baixar CIDRs">↻</button>
                    <button class="glass-btn !bg-red-600 !text-white text-[10px] uppercase font-black btn-remove ml-1" ${isAdmin ? '' : 'disabled'} title="Remover país">×</button>
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.toggle-blocked').forEach(el => {
            el.addEventListener('change', async (e) => {
                const cc = e.target.closest('tr').dataset.cc;
                const blocked = e.target.checked;
                try {
                    const r = await fetch(`/api/v1/geo-blocking/countries/${encodeURIComponent(cc)}/blocked`, {
                        method: 'PUT', headers: HJ, body: JSON.stringify({ blocked }),
                    });
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    if (window.AppUI?.toast) window.AppUI.toast(`${cc} → ${blocked ? 'bloqueado' : 'inativo'} (precisa Apply pra valer)`, 'info');
                    loadStatus();
                } catch (err) {
                    if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
                }
            });
        });

        tbody.querySelectorAll('.btn-refresh').forEach(el => {
            el.addEventListener('click', async (e) => {
                const cc = e.target.closest('tr').dataset.cc;
                e.target.disabled = true;
                e.target.textContent = '...';
                try {
                    const r = await fetch(`/api/v1/geo-blocking/countries/${encodeURIComponent(cc)}/refresh`, { method: 'POST', headers: H });
                    const d = await r.json();
                    if (!r.ok || !d.ok) throw new Error(d.error || 'HTTP ' + r.status);
                    if (window.AppUI?.toast) window.AppUI.toast(`${cc}: ${d.ipv4_count} IPv4 + ${d.ipv6_count} IPv6 baixados`, 'success');
                    loadStatus();
                } catch (err) {
                    if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
                    e.target.disabled = false;
                    e.target.textContent = '↻';
                }
            });
        });

        tbody.querySelectorAll('.btn-remove').forEach(el => {
            el.addEventListener('click', async (e) => {
                const cc = e.target.closest('tr').dataset.cc;
                const ok = await (window.customConfirm ? window.customConfirm(`Remover ${cc} da lista? (não aplica restart)`) : Promise.resolve(true));
                if (!ok) return;
                try {
                    const r = await fetch(`/api/v1/geo-blocking/countries/${encodeURIComponent(cc)}`, { method: 'DELETE', headers: H });
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    loadStatus();
                } catch (err) {
                    if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
                }
            });
        });
    }

    // ============ HANDLERS ============
    document.getElementById('btnSaveSettings').addEventListener('click', async () => {
        const body = {
            geo_blocking_enabled: document.getElementById('setEnabled').checked ? '1' : '0',
            geo_blocking_include_ipv6: document.getElementById('setIncludeIpv6').checked ? '1' : '0',
        };
        try {
            const r = await fetch('/api/v1/geo-blocking/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            if (window.AppUI?.toast) window.AppUI.toast('Settings salvos (não aplicado ainda — clique Apply)', 'info');
            loadStatus();
        } catch (err) {
            if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
        }
    });

    document.getElementById('btnAddCountry').addEventListener('click', async () => {
        const cc = document.getElementById('newCC').value.trim().toUpperCase();
        const name = document.getElementById('newName').value.trim() || cc;
        if (cc.length !== 2 || !/^[A-Z]{2}$/.test(cc)) {
            if (window.customAlert) window.customAlert('Código ISO-2 inválido (ex: CN, RU, KP)');
            return;
        }
        try {
            const r = await fetch('/api/v1/geo-blocking/countries', {
                method: 'POST', headers: HJ,
                body: JSON.stringify({ country_code: cc, country_name: name, blocked: true, refresh: true }),
            });
            const d = await r.json();
            if (!r.ok || !d.ok) throw new Error(d.error || 'HTTP ' + r.status);
            document.getElementById('newCC').value = '';
            document.getElementById('newName').value = '';
            const rf = d.refresh || {};
            const msg = rf.ok ? `${cc} adicionado (${rf.ipv4_count || 0} IPv4 + ${rf.ipv6_count || 0} IPv6 baixados)` : `${cc} adicionado, sem CIDRs`;
            if (window.AppUI?.toast) window.AppUI.toast(msg, 'success');
            loadStatus();
        } catch (err) {
            if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
        }
    });

    document.getElementById('btnRefreshAll').addEventListener('click', async () => {
        const btn = document.getElementById('btnRefreshAll');
        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = 'Atualizando...';
        try {
            const r = await fetch('/api/v1/geo-blocking/refresh-all', { method: 'POST', headers: H });
            const d = await r.json();
            if (!r.ok) throw new Error('HTTP ' + r.status);
            if (window.AppUI?.toast) window.AppUI.toast(`Atualizado: ${d.successful}/${d.total} países`, 'success');
            loadStatus();
        } catch (err) {
            if (window.AppUI?.toast) window.AppUI.toast('Falha: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    });

    document.getElementById('btnApply').addEventListener('click', async () => {
        const ok = await (window.customConfirm ? window.customConfirm('Aplicar e reiniciar o Unbound? O DNS fica indisponível por ~1s durante o restart.') : Promise.resolve(true));
        if (!ok) return;
        const btn = document.getElementById('btnApply');
        btn.disabled = true;
        const span = btn.querySelector('span');
        const orig = span.textContent;
        span.textContent = 'Aplicando...';
        try {
            const r = await fetch('/api/v1/geo-blocking/apply', { method: 'POST', headers: H });
            const d = await r.json();
            if (!r.ok || !d.ok) {
                const stageMsg = d.stage ? ` no estágio ${d.stage}` : '';
                const rbMsg = d.rollback ? ` (rollback: ${d.rollback})` : '';
                throw new Error((d.error || 'falhou') + stageMsg + rbMsg);
            }
            if (window.AppUI?.toast) window.AppUI.toast(`Aplicado: ${d.total_cidrs} CIDRs (${d.ipv4_count} IPv4 + ${d.ipv6_count} IPv6). Unbound reiniciado.`, 'success');
            loadStatus();
        } catch (err) {
            if (window.AppUI?.toast) window.AppUI.toast('Apply falhou: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            span.textContent = orig;
        }
    });

    // Enter no input de CC dispara Add
    document.getElementById('newCC').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnAddCountry').click(); }
    });
    document.getElementById('newName').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnAddCountry').click(); }
    });

    loadStatus();
})();
</script>

</body>
</html>
