<?php
require_once 'src/Auth.php';

use App\Auth;

Auth::check();
// Capability config.write — admin only (mesma de updates/webhooks/SMTP)
if (!\App\Auth::can('config.write')) {
    header('Location: index.php');
    exit;
}

$currentPage = 'hosts.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Hosts gerenciados - Unbound DNS</title>
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = "Hosts Gerenciados";
        include 'includes/topbar.php';
        ?>

        <div class="page-container">
            <header class="page-header mb-6 flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <svg class="w-8 h-8 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z"/></svg>
                        Hosts Gerenciados
                    </h1>
                    <p class="page-subtitle">Master multi-host — inventário de agents pollados via API token.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="hosts-refresh-btn" class="glass-btn text-[10px] uppercase font-black flex items-center gap-2" title="Recarregar lista">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        Recarregar
                    </button>
                    <button type="button" id="hosts-add-btn" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Adicionar host
                    </button>
                </div>
            </header>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Total</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-total">…</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">Online</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-ok">…</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest">Auth/Erro</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-warn">…</p>
                </div>
                <div class="glass-panel !p-4">
                    <p class="text-[10px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest">Inalcançáveis</p>
                    <p class="text-2xl font-black text-slate-900 dark:text-white mt-1" id="hosts-stat-down">…</p>
                </div>
            </div>

            <!-- Lista de hosts -->
            <div id="hosts-list" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="glass-panel text-center py-10 col-span-full">
                    <p class="text-sm text-slate-500 italic">Carregando…</p>
                </div>
            </div>

            <?php include 'includes/footer.php'; ?>
        </div>
    </main>

    <!-- Modal: adicionar/editar host -->
    <div id="host-form-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="glass-panel max-w-lg w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
            <h3 id="host-form-title" class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest mb-4">Adicionar host</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Label *</label>
                    <input type="text" id="host-form-label" autocomplete="off" maxlength="100" class="glass-input w-full" placeholder="ex: Recursor-SP1">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Base URL *</label>
                    <input type="url" id="host-form-url" autocomplete="off" maxlength="255" class="glass-input w-full font-mono text-xs" placeholder="https://dns1.exemplo.com">
                    <p class="text-[10px] text-slate-500 mt-1">URL completa do agent (sem `/api/...` no fim). Imutável após criar.</p>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">API token *</label>
                    <input type="text" id="host-form-token" autocomplete="off" maxlength="255" class="glass-input w-full font-mono text-xs" placeholder="cole o token gerado no agent">
                    <p class="text-[10px] text-slate-500 mt-1" id="host-form-token-hint">Gere em Configurações → API Tokens no agent. Mostrado UMA vez lá.</p>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Notas (opcional)</label>
                    <textarea id="host-form-notes" maxlength="500" rows="2" class="glass-input w-full text-xs" placeholder="ex: recursor primary, edge SP1"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" id="host-form-cancel" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                <button type="button" id="host-form-submit" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black" disabled>Salvar</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const jwtMeta = document.querySelector('meta[name="api-jwt"]');
            const JWT = jwtMeta ? jwtMeta.content : '';
            const HEADERS = JWT ? { 'Authorization': 'Bearer ' + JWT, 'Content-Type': 'application/json' } : { 'Content-Type': 'application/json' };

            const el = {
                list:        document.getElementById('hosts-list'),
                refreshBtn:  document.getElementById('hosts-refresh-btn'),
                addBtn:      document.getElementById('hosts-add-btn'),
                statTotal:   document.getElementById('hosts-stat-total'),
                statOk:      document.getElementById('hosts-stat-ok'),
                statWarn:    document.getElementById('hosts-stat-warn'),
                statDown:    document.getElementById('hosts-stat-down'),
                modal:       document.getElementById('host-form-modal'),
                modalTitle:  document.getElementById('host-form-title'),
                fLabel:      document.getElementById('host-form-label'),
                fUrl:        document.getElementById('host-form-url'),
                fToken:      document.getElementById('host-form-token'),
                fTokenHint:  document.getElementById('host-form-token-hint'),
                fNotes:      document.getElementById('host-form-notes'),
                fCancel:     document.getElementById('host-form-cancel'),
                fSubmit:     document.getElementById('host-form-submit'),
            };

            let editingId = null;  // null=create, int=edit

            const STATUS_BADGE = {
                ok:          { label: '● Online',       color: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border-emerald-500/30' },
                auth_failed: { label: '🔒 Auth falhou',  color: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' },
                unreachable: { label: '⚠ Inalcançável',  color: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30' },
                error:       { label: '⚠ Erro',          color: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' },
                null:        { label: '— Não pollado',   color: 'bg-slate-500/15 text-slate-500 border-slate-500/30' },
            };

            function fmtDate(iso) {
                if (!iso) return '—';
                try { return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); }
                catch (_) { return iso; }
            }
            function fmtRelative(iso) {
                if (!iso) return 'nunca';
                try {
                    const diff = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
                    if (diff < 60) return 'agora';
                    if (diff < 3600) return `há ${Math.floor(diff/60)} min`;
                    if (diff < 86400) return `há ${Math.floor(diff/3600)}h`;
                    return `há ${Math.floor(diff/86400)}d`;
                } catch (_) { return iso; }
            }

            async function load() {
                el.refreshBtn.disabled = true;
                try {
                    const resp = await fetch('/api/v1/hosts', { headers: HEADERS });
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                    const data = await resp.json();
                    render(data.hosts || []);
                } catch (err) {
                    el.list.innerHTML = `<div class="glass-panel col-span-full text-center py-6"><p class="text-sm text-red-500">Erro: ${err.message}</p></div>`;
                } finally {
                    el.refreshBtn.disabled = false;
                }
            }

            function render(hosts) {
                el.statTotal.textContent = hosts.length;
                el.statOk.textContent    = hosts.filter(h => h.last_status === 'ok').length;
                el.statWarn.textContent  = hosts.filter(h => h.last_status === 'auth_failed' || h.last_status === 'error').length;
                el.statDown.textContent  = hosts.filter(h => h.last_status === 'unreachable').length;

                if (!hosts.length) {
                    el.list.innerHTML = `
                        <div class="glass-panel col-span-full text-center py-10">
                            <p class="text-sm text-slate-500 italic">Nenhum host gerenciado ainda.</p>
                            <p class="text-[11px] text-slate-500 mt-2">Clique em "Adicionar host" pra começar.</p>
                        </div>
                    `;
                    return;
                }
                el.list.innerHTML = hosts.map(h => {
                    const badge = STATUS_BADGE[h.last_status] || STATUS_BADGE.null;
                    const p = h.last_status_payload || {};
                    const ratio = (p.hit_ratio_24h !== null && p.hit_ratio_24h !== undefined) ? p.hit_ratio_24h.toFixed(1) + '%' : '—';
                    const queries = (p.queries_24h !== null && p.queries_24h !== undefined) ? p.queries_24h.toLocaleString('pt-BR') : '—';
                    const version = p.version || '?';
                    const alerts = (p.alerts_active !== null && p.alerts_active !== undefined) ? p.alerts_active : '?';
                    return `
                        <div class="glass-panel">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <h3 class="text-base font-black text-slate-900 dark:text-white truncate">${escapeHtml(h.label)}</h3>
                                    <a href="${escapeAttr(h.base_url)}" target="_blank" rel="noopener" class="text-[10px] font-mono text-cyan-600 dark:text-cyan-400 hover:underline truncate block">${escapeHtml(h.base_url)}</a>
                                </div>
                                <span class="shrink-0 inline-block px-2 py-1 rounded-md border text-[10px] font-black uppercase tracking-widest ${badge.color}">${badge.label}</span>
                            </div>

                            ${h.last_status === 'ok' ? `
                                <div class="grid grid-cols-4 gap-2 mb-3 text-[10px]">
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Versão</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">v${escapeHtml(version)}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Hit ratio</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">${ratio}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Queries 24h</p>
                                        <p class="font-mono font-bold text-slate-900 dark:text-white">${queries}</p>
                                    </div>
                                    <div class="bg-slate-900/5 dark:bg-white/5 p-2 rounded-lg">
                                        <p class="text-slate-500 uppercase font-bold">Alertas</p>
                                        <p class="font-mono font-bold ${alerts > 0 ? 'text-red-500' : 'text-slate-900 dark:text-white'}">${alerts}</p>
                                    </div>
                                </div>
                            ` : `
                                <div class="bg-red-500/5 border border-red-500/20 text-red-700 dark:text-red-300 text-[11px] p-3 rounded-lg mb-3">
                                    ${h.last_error ? escapeHtml(h.last_error) : 'Sem detalhes.'}
                                </div>
                            `}

                            ${h.notes ? `<p class="text-[10px] text-slate-500 mb-3">${escapeHtml(h.notes)}</p>` : ''}

                            <div class="flex items-center justify-between text-[10px] text-slate-500 mb-3">
                                <span>Pollado ${fmtRelative(h.last_polled_at)}</span>
                                <span>Adicionado ${fmtDate(h.added_at)}</span>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" data-action="poll" data-id="${h.id}" class="host-action glass-btn !py-1 !px-3 text-[9px] uppercase font-black">↻ Poll agora</button>
                                <button type="button" data-action="edit" data-id="${h.id}" class="host-action glass-btn !py-1 !px-3 text-[9px] uppercase font-black">Editar</button>
                                <a href="${escapeAttr(h.base_url)}" target="_blank" rel="noopener" class="glass-btn !py-1 !px-3 text-[9px] uppercase font-black">↗ Abrir UI</a>
                                <button type="button" data-action="delete" data-id="${h.id}" data-label="${escapeAttr(h.label)}" class="host-action glass-btn !py-1 !px-3 text-[9px] uppercase font-black bg-red-500/15 text-red-600 dark:text-red-400 ml-auto">Remover</button>
                            </div>
                        </div>
                    `;
                }).join('');
                el.list.querySelectorAll('.host-action').forEach(btn => {
                    btn.addEventListener('click', () => handleAction(btn));
                });
            }

            function escapeHtml(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function escapeAttr(s) {
                return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            async function handleAction(btn) {
                const action = btn.getAttribute('data-action');
                const id = btn.getAttribute('data-id');
                if (action === 'poll') {
                    btn.disabled = true;
                    btn.textContent = '⟳ Pollando…';
                    try {
                        const resp = await fetch(`/api/v1/hosts/${id}/poll`, { method: 'POST', headers: HEADERS });
                        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                        await load();
                    } catch (err) {
                        alert('Erro ao polar: ' + err.message);
                        btn.disabled = false;
                        btn.textContent = '↻ Poll agora';
                    }
                } else if (action === 'edit') {
                    openEditModal(id);
                } else if (action === 'delete') {
                    const label = btn.getAttribute('data-label');
                    if (!confirm(`Remover host "${label}"? Master para de polar mas o agent não é afetado.`)) return;
                    try {
                        const resp = await fetch(`/api/v1/hosts/${id}`, { method: 'DELETE', headers: HEADERS });
                        if (!resp.ok && resp.status !== 204) throw new Error(`HTTP ${resp.status}`);
                        await load();
                    } catch (err) {
                        alert('Erro ao remover: ' + err.message);
                    }
                }
            }

            function openCreateModal() {
                editingId = null;
                el.modalTitle.textContent = 'Adicionar host';
                el.fLabel.value = '';
                el.fUrl.value = '';
                el.fUrl.disabled = false;
                el.fToken.value = '';
                el.fTokenHint.textContent = 'Gere em Configurações → API Tokens no agent. Mostrado UMA vez lá.';
                el.fNotes.value = '';
                el.fSubmit.textContent = 'Salvar';
                el.fSubmit.disabled = true;
                el.modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                el.fLabel.focus();
                validateForm();
            }

            async function openEditModal(id) {
                // Pega dados atuais do host
                try {
                    const resp = await fetch('/api/v1/hosts', { headers: HEADERS });
                    const data = await resp.json();
                    const h = (data.hosts || []).find(x => String(x.id) === String(id));
                    if (!h) throw new Error('Host não encontrado');

                    editingId = parseInt(id);
                    el.modalTitle.textContent = `Editar host #${id}`;
                    el.fLabel.value = h.label;
                    el.fUrl.value = h.base_url;
                    el.fUrl.disabled = true;  // base_url imutável
                    el.fToken.value = '';
                    el.fTokenHint.textContent = 'Deixe vazio pra preservar o atual. Cole um novo só se for trocar.';
                    el.fNotes.value = h.notes || '';
                    el.fSubmit.textContent = 'Atualizar';
                    el.modal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    validateForm();
                } catch (err) {
                    alert('Erro ao carregar dados: ' + err.message);
                }
            }

            function closeModal() {
                el.modal.classList.add('hidden');
                document.body.style.overflow = '';
                editingId = null;
            }

            function validateForm() {
                const labelOk = el.fLabel.value.trim().length > 0;
                const urlOk = editingId !== null || (el.fUrl.value.trim().length > 7 && /^https?:\/\//.test(el.fUrl.value.trim()));
                const tokenOk = editingId !== null || el.fToken.value.trim().length >= 20;
                el.fSubmit.disabled = !(labelOk && urlOk && tokenOk);
            }

            [el.fLabel, el.fUrl, el.fToken].forEach(input => input.addEventListener('input', validateForm));

            el.fCancel.addEventListener('click', closeModal);
            el.fSubmit.addEventListener('click', async () => {
                el.fSubmit.disabled = true;
                el.fCancel.disabled = true;
                try {
                    let resp;
                    if (editingId === null) {
                        // CREATE
                        const body = {
                            label: el.fLabel.value.trim(),
                            base_url: el.fUrl.value.trim(),
                            api_token: el.fToken.value.trim(),
                            notes: el.fNotes.value.trim() || null,
                        };
                        resp = await fetch('/api/v1/hosts', { method: 'POST', headers: HEADERS, body: JSON.stringify(body) });
                    } else {
                        // UPDATE — só envia campos preenchidos
                        const body = {
                            label: el.fLabel.value.trim(),
                            notes: el.fNotes.value.trim(),
                        };
                        const newTok = el.fToken.value.trim();
                        if (newTok) body.api_token = newTok;
                        resp = await fetch(`/api/v1/hosts/${editingId}`, { method: 'PUT', headers: HEADERS, body: JSON.stringify(body) });
                    }
                    if (!resp.ok && resp.status !== 201 && resp.status !== 204) {
                        const data = await resp.json().catch(() => ({}));
                        throw new Error(data.detail || `HTTP ${resp.status}`);
                    }
                    closeModal();
                    await load();
                } catch (err) {
                    alert('Erro: ' + err.message);
                } finally {
                    el.fSubmit.disabled = false;
                    el.fCancel.disabled = false;
                }
            });

            el.addBtn.addEventListener('click', openCreateModal);
            el.refreshBtn.addEventListener('click', load);

            // Auto-refresh a cada 60s (matching o intervalo do worker)
            setInterval(load, 60000);

            // Carrega ao abrir
            load();

            // Loader hide
            window.addEventListener('load', function () {
                var loader = document.getElementById('global-page-loader');
                if (loader) loader.classList.remove('is-visible');
            });
        })();
    </script>
</body>
</html>
