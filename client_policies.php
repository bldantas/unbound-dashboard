<?php
require_once 'src/Auth.php';
use App\Auth;
Auth::check();

$currentPage = 'client_policies.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Políticas por Cliente - Unbound DNS</title>
    <meta name="description" content="DNS split-horizon: aplica regras de bloqueio/allowlist específicas por IP/CIDR. Cada política herda o global e adiciona regras próprias.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Políticas por Cliente";
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <div class="glass-panel border-l-4 border-blue-500 mb-6 border-slate-200 dark:border-white/5">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-1">
                            Split-horizon DNS — bloqueio/allowlist por cliente
                        </p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                            Cada política aplica regras extras a um conjunto de IPs/CIDRs
                        </p>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Clientes numa política bloqueiam <b>tudo do global</b> (blocklists ativas em <a href="blocklists.php" class="text-orange-500 hover:underline font-bold">Blocklists</a> + ANATEL) <b>+ extras desta política</b>.
                            A <em>allowlist específica</em> da política sobrescreve qualquer bloqueio só pra esses clientes.
                            Clientes fora de qualquer política caem no global puro.
                        </p>
                    </div>
                    <?php if (\App\Auth::can('blocklist.write')): ?>
                        <button id="btnNewPolicy" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span>Nova Política</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="policiesList" class="space-y-4">
                <div class="glass-panel border-slate-200 dark:border-white/5 text-center py-12 text-slate-500 text-xs uppercase font-black tracking-widest">
                    <div class="w-6 h-6 mx-auto border-2 border-blue-500/30 border-t-blue-500 rounded-full animate-spin"></div>
                    <span class="block mt-2">Carregando políticas...</span>
                </div>
            </div>

            <div class="glass-panel border-l-4 border-emerald-500 border-slate-200 dark:border-white/5 mt-6" id="applyBanner" style="display:none">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-1">Mudança pendente</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Aplicar as políticas no Unbound (regera views.conf + reload).</p>
                    </div>
                    <?php if (\App\Auth::can('blocklist.write')): ?>
                        <button id="btnApplyConf" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Aplicar &amp; Recarregar Unbound</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

<?php include 'includes/custom_modals.php'; ?>

<!-- Modal: Nova Política -->
<div id="newPolicyModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-white/10 max-w-md w-full p-6 shadow-2xl">
        <h3 class="text-base font-black uppercase tracking-widest mb-4">Nova Política</h3>
        <form id="formNewPolicy" class="space-y-3">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Slug (técnico)</label>
                <input type="text" id="newSlug" required pattern="^[a-z][a-z0-9_-]{1,49}$" placeholder="ex: kids, iot, office"
                       class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                <p class="text-[10px] text-slate-500 mt-1">Vira o nome da view no Unbound. Letras minúsculas, números, _-, começa com letra.</p>
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Nome</label>
                <input type="text" id="newName" required maxlength="100" placeholder="ex: Crianças"
                       class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/40">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Descrição (opcional)</label>
                <input type="text" id="newDescription" maxlength="200" placeholder="Bloqueia redes sociais e jogos +18"
                       class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/40">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="cancelNewPolicy" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Criar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const jwtMeta = document.querySelector('meta[name="api-jwt"]');
    const JWT = jwtMeta ? jwtMeta.content : '';
    const H = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};
    const HJ = { ...H, 'Content-Type': 'application/json' };
    const CAN_WRITE = <?= \App\Auth::can('blocklist.write') ? 'true' : 'false' ?>;

    const listEl = document.getElementById('policiesList');
    const applyBanner = document.getElementById('applyBanner');
    let policies = [];  // cache local com detalhe

    // ============ LIST + DETAIL FETCH ============
    async function loadList() {
        try {
            const res = await fetch('/api/v1/policies', { headers: H });
            const data = await res.json();
            const summary = data.policies || [];
            // Pega detalhe de cada uma (em paralelo) pra renderizar inline
            const details = await Promise.all(summary.map(p =>
                fetch(`/api/v1/policies/${p.slug}`, { headers: H }).then(r => r.json())
            ));
            policies = details;
            render();
        } catch (err) {
            listEl.innerHTML = `<div class="glass-panel text-center text-red-500 py-8 text-xs uppercase font-black tracking-widest">Erro: ${err.message}</div>`;
        }
    }

    function render() {
        if (!policies.length) {
            listEl.innerHTML = `<div class="glass-panel border-slate-200 dark:border-white/5 text-center py-12 text-slate-500 text-xs uppercase font-black tracking-widest">
                Nenhuma política cadastrada. ${CAN_WRITE ? 'Clique em <b class="text-blue-500">Nova Política</b> pra criar.' : ''}
            </div>`;
            return;
        }
        listEl.innerHTML = policies.map(renderCard).join('');
        attachHandlers();
    }

    function renderCard(p) {
        const rangesHtml = (p.ranges || []).map(r => `
            <div class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/60 px-3 py-2 rounded-xl text-xs font-mono">
                <div>
                    <span class="font-bold">${escapeHtml(r.cidr)}</span>
                    ${r.label ? `<span class="ml-2 text-slate-500 font-sans not-italic text-[10px]">${escapeHtml(r.label)}</span>` : ''}
                </div>
                ${CAN_WRITE ? `<button data-rem="range" data-slug="${p.slug}" data-id="${r.id}" class="text-red-500 hover:text-red-700 text-[10px]" title="Remover">×</button>` : ''}
            </div>
        `).join('') || `<div class="text-[11px] text-slate-500 italic">Nenhum range — ninguém cai nessa policy</div>`;

        const blocksHtml = (p.blocks || []).map(d => `
            <div class="flex items-center justify-between bg-red-500/5 dark:bg-red-950/30 px-3 py-2 rounded-xl text-xs font-mono">
                <span class="text-red-700 dark:text-red-300">${escapeHtml(d)}</span>
                ${CAN_WRITE ? `<button data-rem="block" data-slug="${p.slug}" data-domain="${escapeHtml(d)}" class="text-red-500 hover:text-red-700 text-[10px]">×</button>` : ''}
            </div>
        `).join('') || `<div class="text-[11px] text-slate-500 italic">Nenhum bloqueio extra</div>`;

        const allowsHtml = (p.allows || []).map(d => `
            <div class="flex items-center justify-between bg-emerald-500/5 dark:bg-emerald-950/30 px-3 py-2 rounded-xl text-xs font-mono">
                <span class="text-emerald-700 dark:text-emerald-300">${escapeHtml(d)}</span>
                ${CAN_WRITE ? `<button data-rem="allow" data-slug="${p.slug}" data-domain="${escapeHtml(d)}" class="text-red-500 hover:text-red-700 text-[10px]">×</button>` : ''}
            </div>
        `).join('') || `<div class="text-[11px] text-slate-500 italic">Nenhuma exceção</div>`;

        return `
        <div class="glass-panel border-slate-200 dark:border-white/5 ${p.enabled ? '' : 'opacity-60'}" data-slug="${p.slug}">
            <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="text-base font-black text-slate-900 dark:text-white">${escapeHtml(p.name)}</h3>
                        <span class="text-[10px] font-mono text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">${escapeHtml(p.slug)}</span>
                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md ${p.enabled ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-slate-500/15 text-slate-500 border border-slate-500/30'}">
                            ${p.enabled ? 'Ativa' : 'Pausada'}
                        </span>
                    </div>
                    ${p.description ? `<p class="text-[11px] text-slate-500">${escapeHtml(p.description)}</p>` : ''}
                </div>
                ${CAN_WRITE ? `
                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center cursor-pointer select-none" title="Pausar/ativar">
                        <input type="checkbox" class="peer sr-only policy-enabled" data-slug="${p.slug}" ${p.enabled ? 'checked' : ''}>
                        <span class="relative inline-block w-9 h-5">
                            <span class="block w-9 h-5 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-colors"></span>
                            <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4"></span>
                        </span>
                    </label>
                    <button data-rem="policy" data-slug="${p.slug}" class="text-red-500 hover:text-red-700" title="Deletar política inteira">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2"></path></svg>
                    </button>
                </div>` : ''}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Ranges -->
                <div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span> Clientes (CIDR/IP)
                    </h4>
                    <div class="space-y-1 mb-2">${rangesHtml}</div>
                    ${CAN_WRITE ? `
                    <form class="form-add-range flex gap-1" data-slug="${p.slug}">
                        <input type="text" required placeholder="192.168.x.0/24" pattern="^[0-9./:a-fA-F]+$"
                               class="flex-1 px-2 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-lg text-xs font-mono focus:outline-none focus:ring-1 focus:ring-blue-500/40">
                        <button class="glass-btn text-[10px] uppercase font-black px-2 py-1.5" title="Adicionar">+</button>
                    </form>` : ''}
                </div>
                <!-- Blocks -->
                <div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span> Bloqueios extras
                    </h4>
                    <div class="space-y-1 mb-2">${blocksHtml}</div>
                    ${CAN_WRITE ? `
                    <form class="form-add-block flex gap-1" data-slug="${p.slug}">
                        <input type="text" required placeholder="tiktok.com" pattern="^[a-z0-9][a-z0-9.\\-]*\\.[a-z]{2,}$"
                               class="flex-1 px-2 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-lg text-xs font-mono focus:outline-none focus:ring-1 focus:ring-red-500/40">
                        <button class="glass-btn text-[10px] uppercase font-black px-2 py-1.5" title="Adicionar">+</button>
                    </form>` : ''}
                </div>
                <!-- Allows -->
                <div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Exceções (allowlist)
                    </h4>
                    <div class="space-y-1 mb-2">${allowsHtml}</div>
                    ${CAN_WRITE ? `
                    <form class="form-add-allow flex gap-1" data-slug="${p.slug}">
                        <input type="text" required placeholder="educa.gov.br" pattern="^[a-z0-9][a-z0-9.\\-]*\\.[a-z]{2,}$"
                               class="flex-1 px-2 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-lg text-xs font-mono focus:outline-none focus:ring-1 focus:ring-emerald-500/40">
                        <button class="glass-btn text-[10px] uppercase font-black px-2 py-1.5" title="Adicionar">+</button>
                    </form>` : ''}
                </div>
            </div>
        </div>`;
    }

    function attachHandlers() {
        // Toggle enabled
        document.querySelectorAll('.policy-enabled').forEach(cb => cb.addEventListener('change', async (e) => {
            const slug = e.target.dataset.slug;
            try {
                await fetch(`/api/v1/policies/${slug}`, { method: 'PATCH', headers: HJ, body: JSON.stringify({ enabled: e.target.checked }) });
                applyBanner.style.display = '';
                toast(`Política ${slug}: ${e.target.checked ? 'ativada' : 'pausada'}`, 'success');
                await loadList();
            } catch (err) { toast('Falha: ' + err.message, 'error'); }
        }));
        // Add range/block/allow
        document.querySelectorAll('.form-add-range').forEach(f => f.addEventListener('submit', addRangeHandler));
        document.querySelectorAll('.form-add-block').forEach(f => f.addEventListener('submit', addBlockHandler));
        document.querySelectorAll('.form-add-allow').forEach(f => f.addEventListener('submit', addAllowHandler));
        // Remove (delegated)
    }

    async function addRangeHandler(e) {
        e.preventDefault();
        const slug = e.target.dataset.slug;
        const input = e.target.querySelector('input');
        const cidr = input.value.trim();
        try {
            const res = await fetch(`/api/v1/policies/${slug}/ranges`, { method: 'POST', headers: HJ, body: JSON.stringify({ cidr }) });
            const data = await res.json();
            if (res.ok && data.added) {
                input.value = '';
                applyBanner.style.display = '';
                toast(`Range ${cidr} adicionado`, 'success');
                await loadList();
            } else {
                toast(data.detail || data.added === false ? 'CIDR já existe' : 'Falha', 'warning');
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    }

    async function addBlockHandler(e) {
        e.preventDefault();
        const slug = e.target.dataset.slug;
        const input = e.target.querySelector('input');
        const domain = input.value.trim().toLowerCase();
        try {
            const res = await fetch(`/api/v1/policies/${slug}/blocks`, { method: 'POST', headers: HJ, body: JSON.stringify({ domain }) });
            const data = await res.json();
            if (res.ok && data.added) {
                input.value = '';
                applyBanner.style.display = '';
                toast(`Bloqueio ${domain} adicionado`, 'success');
                await loadList();
            } else {
                toast(data.detail || 'Já existe ou falhou', 'warning');
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    }

    async function addAllowHandler(e) {
        e.preventDefault();
        const slug = e.target.dataset.slug;
        const input = e.target.querySelector('input');
        const domain = input.value.trim().toLowerCase();
        try {
            const res = await fetch(`/api/v1/policies/${slug}/allows`, { method: 'POST', headers: HJ, body: JSON.stringify({ domain }) });
            const data = await res.json();
            if (res.ok && data.added) {
                input.value = '';
                applyBanner.style.display = '';
                toast(`Exceção ${domain} adicionada`, 'success');
                await loadList();
            } else {
                toast(data.detail || 'Já existe ou falhou', 'warning');
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    }

    // Delegated remove
    listEl.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-rem]');
        if (!btn) return;
        const kind = btn.dataset.rem;
        const slug = btn.dataset.slug;
        if (kind === 'range') {
            const id = btn.dataset.id;
            await fetch(`/api/v1/policies/${slug}/ranges/${id}`, { method: 'DELETE', headers: H });
            applyBanner.style.display = '';
            await loadList();
        } else if (kind === 'block') {
            const d = btn.dataset.domain;
            await fetch(`/api/v1/policies/${slug}/blocks/${encodeURIComponent(d)}`, { method: 'DELETE', headers: H });
            applyBanner.style.display = '';
            await loadList();
        } else if (kind === 'allow') {
            const d = btn.dataset.domain;
            await fetch(`/api/v1/policies/${slug}/allows/${encodeURIComponent(d)}`, { method: 'DELETE', headers: H });
            applyBanner.style.display = '';
            await loadList();
        } else if (kind === 'policy') {
            const ok = await customConfirm(`Deletar a política <b>${slug}</b> e todos os ranges/regras dela?`, 'Deletar?');
            if (!ok) return;
            await fetch(`/api/v1/policies/${slug}`, { method: 'DELETE', headers: H });
            applyBanner.style.display = '';
            toast(`Política ${slug} deletada`, 'success');
            await loadList();
        }
    });

    // ============ MODAL NOVA POLÍTICA ============
    const modal = document.getElementById('newPolicyModal');
    const btnNew = document.getElementById('btnNewPolicy');
    const btnCancel = document.getElementById('cancelNewPolicy');
    if (btnNew) btnNew.addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
    btnCancel.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); });
    document.getElementById('formNewPolicy').addEventListener('submit', async (e) => {
        e.preventDefault();
        const slug = document.getElementById('newSlug').value.trim().toLowerCase();
        const name = document.getElementById('newName').value.trim();
        const description = document.getElementById('newDescription').value.trim();
        try {
            const res = await fetch('/api/v1/policies', { method: 'POST', headers: HJ, body: JSON.stringify({ slug, name, description }) });
            const data = await res.json();
            if (res.ok && data.policy) {
                modal.classList.add('hidden'); modal.classList.remove('flex');
                document.getElementById('formNewPolicy').reset();
                applyBanner.style.display = '';
                toast(`Política ${slug} criada`, 'success');
                await loadList();
            } else {
                toast(data.detail || 'Falha ao criar', 'error');
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    });

    // ============ APPLY ============
    document.getElementById('btnApplyConf')?.addEventListener('click', async () => {
        const ok = await customConfirm('Regerar views.conf e recarregar o Unbound? Restart leve de DNS.', 'Aplicar?');
        if (!ok) return;
        const btn = document.getElementById('btnApplyConf');
        btn.disabled = true;
        btn.querySelector('span').textContent = 'Aplicando...';
        try {
            const fd = new FormData();
            fd.append('action', 'apply_blocklists');
            const res = await fetch('api/blocklist_apply.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                toast('Aplicado — Unbound recarregado.', 'success');
                applyBanner.style.display = 'none';
            } else {
                toast('Falha: ' + (data.message || 'erro'), 'error');
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
        finally {
            btn.disabled = false;
            btn.querySelector('span').textContent = 'Aplicar & Recarregar Unbound';
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

    loadList();
})();
</script>

</body>
</html>
