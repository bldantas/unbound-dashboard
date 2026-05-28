<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();

$currentPage = 'cluster.php';
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Cluster HA - Unbound DNS</title>
    <meta name="description" content="Observabilidade do cluster Unbound: peers, healthcheck e manual failover assist.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('cluster.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <header class="page-header mb-6">
                <h1 class="page-title flex items-center gap-3">
                    <svg class="w-8 h-8 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <?= t('cluster.title') ?>
                </h1>
                <p class="page-subtitle"><?= t('cluster.subtitle') ?></p>
            </header>

            <!-- KPIs -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total peers</p>
                    <p id="kpiTotal" class="text-3xl font-black text-slate-800 dark:text-slate-100 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">OK</p>
                    <p id="kpiOk" class="text-3xl font-black text-emerald-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Down</p>
                    <p id="kpiDown" class="text-3xl font-black text-red-500 mt-1 tabular-nums">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5 p-4">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Primary OK?</p>
                    <p id="kpiPrimary" class="text-sm font-mono text-slate-700 dark:text-slate-300 mt-2">—</p>
                </div>
            </div>

            <!-- Tabela peers -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6 overflow-hidden">
                <div class="px-6 py-3 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('cluster.section_peers') ?></h3>
                    <button type="button" id="btnRefresh" class="glass-btn text-[10px] uppercase font-black">↻ Atualizar</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Label</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">URL</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Role</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Prio</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Status</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Latência</th>
                                <th class="px-3 py-2 text-left font-black uppercase tracking-widest text-[10px]">Último check</th>
                                <th class="px-3 py-2 text-right font-black uppercase tracking-widest text-[10px]">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="peersTbody" class="divide-y divide-slate-200 dark:divide-white/5">
                            <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500 italic">Carregando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($isAdmin): ?>
            <!-- Adicionar peer -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('cluster.section_add_peer') ?></h3>
                    <details class="text-[10px] text-slate-500 mt-2">
                        <summary class="cursor-pointer font-bold hover:text-slate-700 dark:hover:text-slate-300">Como ativar cluster (2 cenários)</summary>
                        <div class="mt-2 ml-2 space-y-3 leading-relaxed">
                            <div>
                                <div class="font-bold text-slate-700 dark:text-slate-300 mb-1">A) Cenário sequencial — recomendado</div>
                                <ol class="ml-3 list-decimal space-y-1">
                                    <li>No <b>servidor A</b>: adicione peer "SRV-B" com <i>Token existente</i> em branco. A gera token <b>T</b> — copie no popup.</li>
                                    <li>No <b>servidor B</b>: adicione peer "SRV-A" colando <b>T</b> em <i>Token existente</i>. B reusa o mesmo token.</li>
                                    <li>Clique em <kbd>Check</kbd> — status vira <span class="text-emerald-500 font-bold">ok</span> nos dois lados.</li>
                                </ol>
                            </div>
                            <div>
                                <div class="font-bold text-slate-700 dark:text-slate-300 mb-1">B) Já criou dos dois lados sem coordenar?</div>
                                <ol class="ml-3 list-decimal space-y-1">
                                    <li>Escolha um dos tokens (digamos <b>T_A</b>, do servidor A). Vá no <b>servidor B</b>, clique no botão <kbd>🔑</kbd> do peer "SRV-A".</li>
                                    <li>Cole <b>T_A</b> no modal. B passa a usar o mesmo token de A.</li>
                                    <li>Clique <kbd>Check</kbd> — fica <span class="text-emerald-500 font-bold">ok</span> autenticado.</li>
                                </ol>
                            </div>
                        </div>
                    </details>
                </div>
                <div class="p-6 flex flex-wrap items-end gap-3">
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Label</span>
                        <input type="text" id="nLabel" placeholder="SRV02-UNBOUND" class="glass-input w-48 font-mono">
                    </label>
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">API URL</span>
                        <input type="text" id="nUrl" placeholder="https://srv02.local" class="glass-input w-64 font-mono">
                    </label>
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Role</span>
                        <select id="nRole" class="glass-input">
                            <option value="secondary">Secondary</option>
                            <option value="primary">Primary</option>
                        </select>
                    </label>
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Prioridade</span>
                        <input type="number" id="nPriority" value="100" min="0" max="1000" class="glass-input w-24 font-mono">
                    </label>
                    <label class="flex flex-col" title="Cole aqui o token gerado no peer espelho. Deixe vazio pra gerar um novo.">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Token existente (opcional)</span>
                        <input type="text" id="nExistingToken" placeholder="cole pra fechar o link" class="glass-input w-64 font-mono">
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer mb-1" title="Guarda o token cifrado pra que o monitor mande X-Api-Token no probe /cluster/peer-ping. Ignorado se Token existente foi colado (implícito).">
                        <input type="checkbox" id="nKeepRaw" class="w-4 h-4">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">🔐 Habilitar healthcheck autenticado</span>
                    </label>
                    <button type="button" id="btnCreate" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Adicionar</button>
                </div>
            </div>

            <!-- Manual failover -->
            <div class="glass-panel border-rose-200 dark:border-rose-500/30 mb-6 bg-rose-50/30 dark:bg-rose-500/5">
                <div class="px-6 py-4 border-b border-rose-200 dark:border-rose-500/30">
                    <h3 class="text-xs font-black text-rose-700 dark:text-rose-300 uppercase tracking-widest"><?= t('cluster.section_failover') ?></h3>
                    <p class="text-[10px] text-slate-500 mt-1">Promove secondary → primary no registro do cluster. <strong>Não muda rede/DNS</strong> — você cuida do cutover real (A record, IP virtual, keepalived).</p>
                </div>
                <div class="p-6 flex flex-wrap items-end gap-3">
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Promover (→ primary)</span>
                        <select id="fPromote" class="glass-input w-56"></select>
                    </label>
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Demover (opcional)</span>
                        <select id="fDemote" class="glass-input w-56"><option value="">— nenhum —</option></select>
                    </label>
                    <button type="button" id="btnFailover" class="glass-btn !bg-rose-600 !text-white text-[10px] uppercase font-black">Executar Failover</button>
                </div>
            </div>
            <?php endif; ?>

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
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const $ = (id) => document.getElementById(id);

    const STATUS_CLS = {
        ok: 'text-emerald-500',
        unauthorized: 'text-amber-500',
        not_found: 'text-amber-500',
        timeout: 'text-amber-500',
        error: 'text-red-500',
        down: 'text-red-500',
    };

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function relTime(iso) {
        if (!iso) return 'nunca';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return iso;
        const diff = Math.floor((Date.now() - t) / 1000);
        if (diff < 60) return diff + 's atrás';
        if (diff < 3600) return Math.floor(diff/60) + 'min atrás';
        if (diff < 86400) return Math.floor(diff/3600) + 'h atrás';
        return Math.floor(diff/86400) + 'd atrás';
    }

    async function loadStatus() {
        const r = await fetch('/api/v1/ha/status', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        $('kpiTotal').textContent = d.total ?? 0;
        $('kpiOk').textContent = d.ok_count ?? 0;
        $('kpiDown').textContent = d.down_count ?? 0;
        $('kpiPrimary').innerHTML = d.has_primary_ok
            ? '<span class="text-emerald-500">● sim</span>'
            : (d.primary_count ? '<span class="text-red-500">● primary down</span>' : '<span class="text-slate-500">— sem primary</span>');

        const peers = d.peers || [];
        if (!peers.length) {
            $('peersTbody').innerHTML = '<tr><td colspan="8" class="px-3 py-6 text-center text-slate-500 italic">Nenhum peer cadastrado.</td></tr>';
        } else {
            $('peersTbody').innerHTML = peers.map(p => {
                const stCls = STATUS_CLS[p.last_check_status] || 'text-slate-500';
                const stTxt = p.last_check_status || 'pendente';
                const errMsg = p.last_check_payload && p.last_check_payload.error ? p.last_check_payload.error : '';
                const stCell = errMsg
                    ? `<span class="${stCls}" title="${esc(errMsg)}">${esc(stTxt)} ⓘ</span>`
                    : `<span class="${stCls}">${esc(stTxt)}</span>`;
                const lat = p.last_check_latency_ms != null ? `${p.last_check_latency_ms}ms` : '—';
                const roleBadge = p.role === 'primary'
                    ? '<span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-500">PRIMARY</span>'
                    : '<span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-slate-500/20 text-slate-500">secondary</span>';
                const enabledBadge = p.enabled ? '' : ' <span class="text-[10px] text-slate-500 italic">(off)</span>';
                const authBadge = p.has_raw_token
                    ? ' <span class="text-[10px] text-emerald-500" title="Healthcheck autenticado (token cifrado guardado)">🔐</span>'
                    : '';
                const actions = IS_ADMIN
                    ? `<button data-id="${p.id}" data-label="${esc(p.label)}" class="tokenBtn glass-btn text-[10px] uppercase font-black" title="Substituir token (cole o token do espelho)">🔑</button>
                       <button data-id="${p.id}" class="checkBtn glass-btn text-[10px] uppercase font-black">Check</button>
                       <button data-id="${p.id}" class="delBtn glass-btn !bg-red-600/80 !text-white text-[10px] uppercase font-black">Excluir</button>`
                    : '';
                return `<tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                    <td class="px-3 py-2 font-mono">${esc(p.label)}${enabledBadge}${authBadge}</td>
                    <td class="px-3 py-2 font-mono text-[10px] break-all">${esc(p.api_url)}</td>
                    <td class="px-3 py-2">${roleBadge}</td>
                    <td class="px-3 py-2 font-mono">${p.priority}</td>
                    <td class="px-3 py-2 font-black uppercase tracking-widest text-[10px]">${stCell}</td>
                    <td class="px-3 py-2 font-mono">${lat}</td>
                    <td class="px-3 py-2 text-[10px] text-slate-500">${relTime(p.last_check_at)}</td>
                    <td class="px-3 py-2 text-right">${actions}</td>
                </tr>`;
            }).join('');
            document.querySelectorAll('.checkBtn').forEach(btn => btn.addEventListener('click', () => checkNow(btn.dataset.id)));
            document.querySelectorAll('.delBtn').forEach(btn => btn.addEventListener('click', () => delPeer(btn.dataset.id)));
            document.querySelectorAll('.tokenBtn').forEach(btn => btn.addEventListener('click', () => replaceToken(btn.dataset.id, btn.dataset.label)));
        }

        if (IS_ADMIN && $('fPromote')) {
            const opts = peers.filter(p => p.role !== 'primary').map(p => `<option value="${p.id}">${esc(p.label)}</option>`);
            $('fPromote').innerHTML = opts.join('') || '<option value="">— nenhum secondary —</option>';
            const demOpts = peers.filter(p => p.role === 'primary').map(p => `<option value="${p.id}">${esc(p.label)}</option>`);
            $('fDemote').innerHTML = '<option value="">— nenhum —</option>' + demOpts.join('');
        }
    }

    async function checkNow(id) {
        const r = await fetch(`/api/v1/ha/peers/${id}/check`, { method: 'POST', headers: H });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) {
            (window.customAlert || alert)('Erro check.');
        } else {
            const msg = [`Status: ${d.status} (${d.latency_ms}ms)`];
            if (d.payload?.probe_path) msg.push(`Probe: ${d.payload.probe_path}`);
            if (d.payload?.authenticated !== undefined) msg.push(`Autenticado: ${d.payload.authenticated ? 'sim' : 'não'}`);
            if (d.error) msg.push(`Detalhe: ${d.error}`);
            (window.customAlert || alert)(msg.join('\n'));
        }
        loadStatus();
    }

    async function replaceToken(id, label) {
        const tok = await (window.customPrompt
            ? customPrompt(`Substituir token de ${label}`,
                'Cole o token gerado no peer espelho. Este lado passa a usar o mesmo token (hash + raw cifrado).',
                {placeholder: 'token raw (16+ chars)', okLabel: 'Substituir', variant: 'warning'})
            : Promise.resolve(window.prompt(`Token novo pra peer ${label}:`)));
        if (tok === null) return;
        const trimmed = (tok || '').trim();
        if (!trimmed) return;
        if (trimmed.length < 16) {
            customAlert('Erro', 'Token muito curto (mín 16 chars).', 'error');
            return;
        }
        const r = await fetch(`/api/v1/ha/peers/${id}/token`, {
            method: 'PUT', headers: HJ,
            body: JSON.stringify({token: trimmed}),
        });
        const d = await r.json().catch(() => ({}));
        if (r.ok) {
            customAlert('Token substituído', 'Clique em Check pra validar contra o peer espelho.', 'success');
            loadStatus();
        } else {
            customAlert('Erro', d.detail || r.statusText, 'error');
        }
    }

    async function delPeer(id) {
        const ok = await (window.customConfirm ? customConfirm('Remover peer? Histórico de checks também sai.') : Promise.resolve(confirm('Confirma?')));
        if (!ok) return;
        const r = await fetch(`/api/v1/ha/peers/${id}`, { method: 'DELETE', headers: H });
        if (r.ok || r.status === 204) loadStatus();
        else (window.customAlert || alert)('Erro ao remover.');
    }

    if (IS_ADMIN) {
        $('btnCreate')?.addEventListener('click', async () => {
            const existingTok = $('nExistingToken').value.trim();
            const body = {
                label: $('nLabel').value.trim(),
                api_url: $('nUrl').value.trim(),
                role: $('nRole').value,
                priority: parseInt($('nPriority').value || '100', 10),
                keep_raw: $('nKeepRaw').checked || !!existingTok,
            };
            if (existingTok) body.existing_token = existingTok;
            if (!body.label || !body.api_url) {
                (window.customAlert || alert)('Label e URL obrigatórios.');
                return;
            }
            const r = await fetch('/api/v1/ha/peers', { method: 'POST', headers: HJ, body: JSON.stringify(body) });
            const d = await r.json().catch(() => ({}));
            if (r.ok || r.status === 201) {
                const intro = d.reused_token
                    ? `Peer criado reutilizando o token fornecido.\n\nTOKEN (mesmo do espelho):\n\n${d.api_token}`
                    : `Peer criado. TOKEN (apenas exibido agora):\n\n${d.api_token}\n\nGuarde antes de fechar — cole no peer espelho.`;
                (window.customAlert || alert)(intro);
                $('nLabel').value = ''; $('nUrl').value = ''; $('nExistingToken').value = '';
                loadStatus();
            } else {
                (window.customAlert || alert)('Erro: ' + (d.detail || r.statusText));
            }
        });

        $('btnFailover')?.addEventListener('click', async () => {
            const promote_id = parseInt($('fPromote').value || '0', 10);
            const demote_id = parseInt($('fDemote').value || '0', 10) || null;
            if (!promote_id) { (window.customAlert || alert)('Selecione peer a promover.'); return; }
            const msg = `Promover peer #${promote_id} → primary${demote_id ? `, demover #${demote_id} → secondary` : ''}?`;
            const ok = await (window.customConfirm ? customConfirm(msg + '\n\n⚠ Esta operação NÃO muda rede/DNS — você deve cuidar do cutover real.') : Promise.resolve(confirm(msg)));
            if (!ok) return;
            const r = await fetch('/api/v1/ha/failover', { method: 'POST', headers: HJ, body: JSON.stringify({promote_id, demote_id}) });
            const d = await r.json().catch(() => ({}));
            (window.customAlert || alert)(r.ok ? `Promovido: ${d.promoted}. ${d.note}` : `Falha: ${d.detail?.error || d.detail || r.statusText}`);
            loadStatus();
        });
    }

    $('btnRefresh').addEventListener('click', loadStatus);
    loadStatus();
    setInterval(loadStatus, 30000);
})();
</script>

</body>
</html>
