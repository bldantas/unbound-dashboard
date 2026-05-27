<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';
use App\Auth;
Auth::check();
if (!Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$currentPage = 'backup_offsite.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>Backup Offsite - Unbound DNS</title>
    <meta name="description" content="Upload automático de backups (DuckDB + configs) pra S3-compatible: AWS S3, MinIO, Wasabi, Cloudflare R2, Backblaze B2.">
    <?php include 'includes/head.php'; ?>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 transition-colors duration-300">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
        <?php
        $pageTitle = t('backup.title');
        include 'includes/topbar.php';
        ?>
        <div class="page-container">

            <div class="glass-panel border-l-4 mb-6 border-slate-200 dark:border-white/5" id="statusPanel">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest mb-1" id="statusLabel">Backup S3 — Status</p>
                        <p class="text-sm font-bold text-slate-900 dark:text-white" id="statusText">Carregando...</p>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Upload automático do <b>DuckDB</b> + configs do Unbound (<code>unbound.conf</code>, <code>includes/</code>, <code>settings.json</code>)
                            pra qualquer storage S3-compatible: <b>AWS S3, MinIO, Wasabi, Cloudflare R2, Backblaze B2</b>.
                            Worker dispara conforme schedule (default 24h). Retenção remota mantém só os N mais recentes.
                        </p>
                    </div>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none shrink-0">
                        <span id="enabledLabel" class="text-[10px] font-black uppercase tracking-widest text-slate-500">Pausado</span>
                        <span class="relative inline-block w-11 h-6">
                            <input type="checkbox" id="toggleEnabled" class="peer sr-only">
                            <span class="block w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-colors"></span>
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Status do último upload -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Último upload</p>
                    <p class="text-sm font-bold" id="lastUploadAt">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Status</p>
                    <p class="text-sm font-bold" id="lastStatus">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Tamanho</p>
                    <p class="text-sm font-bold" id="lastSize">—</p>
                </div>
                <div class="glass-panel border-slate-200 dark:border-white/5">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Key remota</p>
                    <p class="text-xs font-mono truncate" id="lastKey" title="—">—</p>
                </div>
            </div>

            <!-- Form -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('backup.section_config') ?></h3>
                    <div class="flex gap-2">
                        <button id="btnTest" class="glass-btn text-[10px] uppercase font-black flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Testar conexão</span>
                        </button>
                        <button id="btnUpload" class="glass-btn !bg-purple-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            <span>Upload agora</span>
                        </button>
                        <button id="btnRestoreTest" class="glass-btn !bg-amber-600 !text-white text-[10px] uppercase font-black flex items-center gap-2" title="Baixa o backup mais recente e valida integridade do DuckDB (sem restaurar no DB de produção)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <span>Testar restore</span>
                        </button>
                        <button id="btnSave" class="glass-btn !bg-emerald-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Endpoint (vazio = AWS default)</label>
                        <input type="text" id="fEndpoint" placeholder="https://s3.wasabisys.com  ou  https://<account>.r2.cloudflarestorage.com"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                        <p class="text-[10px] text-slate-500 mt-1">AWS: deixe vazio. MinIO: <code>http://minio.local:9000</code>. Wasabi: <code>https://s3.wasabisys.com</code>. R2: <code>https://&lt;account-id&gt;.r2.cloudflarestorage.com</code>. B2: <code>https://s3.us-west-002.backblazeb2.com</code>.</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Bucket</label>
                        <input type="text" id="fBucket" placeholder="meu-bucket-backup"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Region</label>
                        <input type="text" id="fRegion" placeholder="us-east-1"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Prefix / subpasta</label>
                        <input type="text" id="fPrefix" placeholder="unbound-dashboard/"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Reter (n mais recentes)</label>
                        <input type="number" id="fRetention" min="1" max="999" placeholder="10"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Access Key</label>
                        <input type="text" id="fAccessKey" placeholder="AKIA..."
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Secret Key <span class="font-normal italic text-slate-400 normal-case">(deixe vazio pra preservar atual)</span></label>
                        <input type="password" id="fSecretKey" placeholder="•••• mascarado se já salvo"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40" autocomplete="new-password">
                        <p class="text-[10px] text-slate-500 mt-1" id="secretHint">—</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Schedule (horas)</label>
                        <input type="number" id="fSchedule" min="1" max="168" placeholder="24"
                               class="w-full px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-500/40">
                        <p class="text-[10px] text-slate-500 mt-1">Worker dispara upload a cada N horas.</p>
                    </div>
                </div>
            </div>

            <!-- Histórico remoto -->
            <div class="glass-table-container border-slate-200 dark:border-white/5">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">
                        <?= t('backup.section_history') ?> (<span id="historyCount" class="text-purple-500">—</span>)
                    </h3>
                    <button id="btnRefreshHistory" class="glass-btn text-[10px] uppercase font-black">Atualizar lista</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="glass-table">
                        <thead><tr><th>Key</th><th class="w-32 text-right">Tamanho</th><th class="w-44">Quando</th></tr></thead>
                        <tbody id="historyBody">
                            <tr><td colspan="3" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Configure e salve antes de listar</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Múltiplos destinos S3 (v2.74) -->
            <div class="glass-panel border-blue-200 dark:border-blue-500/30 bg-blue-50/30 dark:bg-blue-500/5 mb-6">
                <div class="px-6 py-4 border-b border-blue-200 dark:border-blue-500/30 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('backup.section_destinations') ?> <span id="destBadge" class="ml-2 text-[10px] text-slate-500"></span></h3>
                        <p class="text-[10px] text-slate-500 mt-1">Backup redundante em N provedores em paralelo (AWS + B2 + Wasabi…). Quando ≥1 destino está ativo, o worker passa a usar este modo no lugar do single-bucket acima. <code>secret_key</code> é cifrado via <code>cipher_service</code>.</p>
                    </div>
                    <div class="flex gap-2">
                        <button id="btnDestAdd" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">+ Adicionar</button>
                        <button id="btnDestUploadAll" class="glass-btn !bg-purple-600 !text-white text-[10px] uppercase font-black">Upload em todos</button>
                    </div>
                </div>
                <div id="destList" class="p-6 space-y-3 text-xs">
                    <div class="text-slate-500 italic">Carregando…</div>
                </div>
            </div>

            <!-- Auto restore-test (RestoreTestRunner) -->
            <div class="glass-panel border-slate-200 dark:border-white/5 mb-6">
                <div class="px-6 py-4 border-b border-slate-900/10 dark:border-white/5 bg-slate-900/5 dark:bg-white/5">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest"><?= t('backup.section_restore_test') ?></h3>
                    <p class="text-[10px] text-slate-500 mt-1">Worker <code>RestoreTestRunner</code> baixa um backup do S3 e valida integridade do DuckDB. Tempo típico 5-30s dependendo do tamanho.</p>
                </div>
                <div class="p-6 flex flex-wrap items-end gap-4 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="rtEnabled" class="w-4 h-4">
                        <span class="text-[11px] font-black uppercase tracking-widest">Habilitar auto-teste</span>
                    </label>
                    <label class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Intervalo (horas, 1..720)</span>
                        <input type="number" id="rtInterval" min="1" max="720" value="168" class="glass-input w-32 font-mono">
                    </label>
                    <button type="button" id="btnRtSave" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Salvar</button>
                    <div class="ml-auto text-right text-[10px] text-slate-500">
                        <p>Última: <span id="rtLastAt" class="font-mono text-slate-700 dark:text-slate-300">—</span> <span id="rtLastOk"></span></p>
                        <p id="rtLastErr" class="text-red-500"></p>
                    </div>
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
    const H = JWT ? { 'Authorization': 'Bearer ' + JWT } : {};
    const HJ = { ...H, 'Content-Type': 'application/json' };

    function fmtSize(b) {
        if (!b || b <= 0) return '—';
        const u = ['B','KB','MB','GB','TB'];
        let i = 0, v = b;
        while (v >= 1024 && i < u.length-1) { v /= 1024; i++; }
        return v.toFixed(1) + ' ' + u[i];
    }
    function fmtDateBR(iso) {
        if (!iso) return '—';
        try {
            const d = new Date(iso);
            return d.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit', year:'2-digit'}) + ' ' +
                   d.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
        } catch { return '—'; }
    }
    function escHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function toast(msg, type='info') {
        if (window.AppUI?.toast) window.AppUI.toast(msg, type);
        else console.log('['+type+']', msg);
    }

    async function loadSettings() {
        try {
            const res = await fetch('/api/v1/backup-offsite/settings', { headers: H });
            const data = await res.json();
            const s = data.settings;
            document.getElementById('fEndpoint').value   = s.backup_s3_endpoint || '';
            document.getElementById('fBucket').value     = s.backup_s3_bucket || '';
            document.getElementById('fRegion').value     = s.backup_s3_region || 'us-east-1';
            document.getElementById('fPrefix').value     = s.backup_s3_prefix || 'unbound-dashboard/';
            document.getElementById('fRetention').value  = s.backup_s3_retention_count || '10';
            document.getElementById('fAccessKey').value  = s.backup_s3_access_key || '';
            document.getElementById('fSchedule').value   = s.backup_s3_schedule_hours || '24';
            // secret_key não é retornado plaintext — só hint
            document.getElementById('fSecretKey').value = '';
            document.getElementById('secretHint').textContent = s.backup_s3_secret_key_masked
                ? 'Atual: ' + s.backup_s3_secret_key_masked
                : 'Sem secret_key salva ainda';

            const enabled = s.backup_s3_enabled === '1';
            document.getElementById('toggleEnabled').checked = enabled;
            updateStatusPanel(enabled);

            renderStatusCards(data.status || {});
        } catch (err) { toast('Erro: ' + err.message, 'error'); }
    }

    function updateStatusPanel(enabled) {
        const panel = document.getElementById('statusPanel');
        const label = document.getElementById('statusLabel');
        const text = document.getElementById('statusText');
        const enabledLabel = document.getElementById('enabledLabel');
        if (enabled) {
            panel.classList.remove('border-amber-500');
            panel.classList.add('border-emerald-500');
            label.textContent = 'Backup S3 — ATIVO';
            label.className = 'text-[10px] font-black uppercase tracking-widest mb-1 text-emerald-600 dark:text-emerald-400';
            text.textContent = 'Worker dispara upload a cada N horas configuradas.';
            enabledLabel.textContent = 'Ativo';
            enabledLabel.className = 'text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400';
        } else {
            panel.classList.remove('border-emerald-500');
            panel.classList.add('border-amber-500');
            label.textContent = 'Backup S3 — PAUSADO';
            label.className = 'text-[10px] font-black uppercase tracking-widest mb-1 text-amber-600 dark:text-amber-400';
            text.textContent = 'Configure credenciais + bucket, teste a conexão e ative o toggle.';
            enabledLabel.textContent = 'Pausado';
            enabledLabel.className = 'text-[10px] font-black uppercase tracking-widest text-slate-500';
        }
    }

    function renderStatusCards(st) {
        document.getElementById('lastUploadAt').textContent = fmtDateBR(st.backup_s3_last_upload_at);
        const statusEl = document.getElementById('lastStatus');
        const status = st.backup_s3_last_status || '';
        if (status === 'ok') {
            statusEl.innerHTML = '<span class="text-emerald-500">✓ OK</span>';
        } else if (status === 'error') {
            const err = st.backup_s3_last_error || 'erro';
            statusEl.innerHTML = `<span class="text-red-500" title="${escHtml(err)}">✗ Erro</span>`;
        } else {
            statusEl.innerHTML = '<span class="text-slate-400">—</span>';
        }
        document.getElementById('lastSize').textContent = fmtSize(parseInt(st.backup_s3_last_size_bytes || 0));
        const k = st.backup_s3_last_key || '—';
        document.getElementById('lastKey').textContent = k;
        document.getElementById('lastKey').title = k;
    }

    function collectBody(includeEnabled = false) {
        const body = {
            backup_s3_endpoint:        document.getElementById('fEndpoint').value.trim(),
            backup_s3_bucket:          document.getElementById('fBucket').value.trim(),
            backup_s3_region:          document.getElementById('fRegion').value.trim() || 'us-east-1',
            backup_s3_prefix:          document.getElementById('fPrefix').value.trim() || 'unbound-dashboard/',
            backup_s3_retention_count: document.getElementById('fRetention').value || '10',
            backup_s3_access_key:      document.getElementById('fAccessKey').value.trim(),
            backup_s3_schedule_hours:  document.getElementById('fSchedule').value || '24',
        };
        const sk = document.getElementById('fSecretKey').value;
        if (sk.trim()) body.backup_s3_secret_key = sk;  // se vazio, backend preserva o anterior
        if (includeEnabled) body.backup_s3_enabled = document.getElementById('toggleEnabled').checked ? '1' : '0';
        return body;
    }

    document.getElementById('btnSave').addEventListener('click', async () => {
        try {
            const body = collectBody(true);
            const res = await fetch('/api/v1/backup-offsite/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
            const data = await res.json();
            toast(`${data.updated} setting(s) salvas`, 'success');
            await loadSettings();
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
    });

    document.getElementById('toggleEnabled').addEventListener('change', async (e) => {
        try {
            await fetch('/api/v1/backup-offsite/settings', { method: 'PUT', headers: HJ, body: JSON.stringify({ backup_s3_enabled: e.target.checked ? '1' : '0' }) });
            updateStatusPanel(e.target.checked);
            toast(`Backup S3 ${e.target.checked ? 'ativado' : 'pausado'}`, 'success');
        } catch (err) {
            e.target.checked = !e.target.checked;
            toast('Falha: ' + err.message, 'error');
        }
    });

    document.getElementById('btnTest').addEventListener('click', async () => {
        const btn = document.getElementById('btnTest');
        btn.disabled = true;
        const span = btn.querySelector('span');
        const orig = span.textContent;
        span.textContent = 'Testando...';
        // Salva primeiro pra usar valores atuais
        await fetch('/api/v1/backup-offsite/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(collectBody(false)) });
        try {
            const res = await fetch('/api/v1/backup-offsite/test', { method: 'POST', headers: H });
            const data = await res.json();
            if (data.success) {
                toast(`Conexão OK · bucket ${data.bucket} acessível`, 'success');
            } else {
                if (window.customAlert) await window.customAlert('<pre class="text-xs whitespace-pre-wrap">' + escHtml(data.error) + '</pre>', 'Teste falhou', 'error');
                else alert('Falha: ' + data.error);
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
        finally { btn.disabled = false; span.textContent = orig; }
    });

    document.getElementById('btnUpload').addEventListener('click', async () => {
        const ok = window.customConfirm
            ? await window.customConfirm('Disparar upload agora pro S3? Pode demorar dependendo do tamanho do DuckDB.', 'Upload agora?')
            : confirm('Upload agora?');
        if (!ok) return;
        const btn = document.getElementById('btnUpload');
        btn.disabled = true;
        const span = btn.querySelector('span');
        const orig = span.textContent;
        span.textContent = 'Enviando...';
        try {
            const res = await fetch('/api/v1/backup-offsite/upload-now', { method: 'POST', headers: H });
            const data = await res.json();
            if (data.success) {
                toast(`Upload OK · ${fmtSize(data.size_bytes)} · key ${data.key}`, 'success');
                await loadSettings();
                loadHistory();
            } else {
                if (window.customAlert) await window.customAlert('<pre class="text-xs whitespace-pre-wrap">' + escHtml(data.error || JSON.stringify(data)) + '</pre>', 'Upload falhou', 'error');
                else alert('Falha: ' + (data.error || ''));
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
        finally { btn.disabled = false; span.textContent = orig; }
    });

    document.getElementById('btnRestoreTest').addEventListener('click', async () => {
        const ok = window.customConfirm
            ? await window.customConfirm('Testar restore? Baixa o backup mais recente e valida o DuckDB extraído em /tmp (NÃO restaura no DB de produção).', 'Testar restore?')
            : confirm('Testar restore?');
        if (!ok) return;
        const btn = document.getElementById('btnRestoreTest');
        btn.disabled = true;
        const span = btn.querySelector('span');
        const orig = span.textContent;
        span.textContent = 'Testando...';
        try {
            const res = await fetch('/api/v1/backup-offsite/restore-test', { method: 'POST', headers: { ...H, 'Content-Type': 'application/json' }, body: '{}' });
            const data = await res.json();
            if (data.success) {
                toast(`Restore OK · ${data.tables_count} tabelas · ${data.users_count} users · ${data.migrations_applied} migrations · ${fmtSize(data.size_bytes)}`, 'success');
            } else {
                if (window.customAlert) await window.customAlert('<pre class="text-xs whitespace-pre-wrap">' + escHtml(data.error || JSON.stringify(data)) + '</pre>', 'Restore falhou', 'error');
                else alert('Falha: ' + (data.error || ''));
            }
        } catch (err) { toast('Falha: ' + err.message, 'error'); }
        finally { btn.disabled = false; span.textContent = orig; }
    });

    async function loadHistory() {
        const body = document.getElementById('historyBody');
        body.innerHTML = `<tr><td colspan="3" class="px-6 py-12 text-center"><div class="w-6 h-6 mx-auto border-2 border-purple-500/30 border-t-purple-500 rounded-full animate-spin"></div></td></tr>`;
        try {
            const res = await fetch('/api/v1/backup-offsite/history?limit=100', { headers: H });
            if (!res.ok) {
                const txt = await res.text();
                body.innerHTML = `<tr><td colspan="3" class="px-6 py-8 text-center text-red-500 text-xs uppercase font-black tracking-widest">${escHtml(txt)}</td></tr>`;
                return;
            }
            const data = await res.json();
            const items = data.items || [];
            document.getElementById('historyCount').textContent = items.length;
            if (!items.length) {
                body.innerHTML = `<tr><td colspan="3" class="px-6 py-12 text-center text-slate-500 text-xs uppercase font-black tracking-widest">Nenhum backup no bucket ainda</td></tr>`;
                return;
            }
            body.innerHTML = items.map(it => `
                <tr>
                    <td class="font-mono text-xs">${escHtml(it.key)}</td>
                    <td class="text-right font-mono text-xs font-bold">${fmtSize(it.size)}</td>
                    <td class="text-[11px] font-mono text-slate-500">${fmtDateBR(it.last_modified)}</td>
                </tr>
            `).join('');
        } catch (err) {
            body.innerHTML = `<tr><td colspan="3" class="px-6 py-8 text-center text-red-500 text-xs uppercase font-black tracking-widest">Erro: ${escHtml(err.message)}</td></tr>`;
        }
    }

    document.getElementById('btnRefreshHistory').addEventListener('click', loadHistory);

    loadSettings();

    // Auto restore-test panel — usa o mesmo /status endpoint via load
    async function loadRestoreTestPanel() {
        const r = await fetch('/api/v1/backup-offsite/settings', { headers: H });
        if (!r.ok) return;
        const data = await r.json();
        const s = data.status || {};
        document.getElementById('rtEnabled').checked = String(s.backup_s3_restore_test_enabled) === '1';
        document.getElementById('rtInterval').value = parseInt(s.backup_s3_restore_test_interval_hours || '168', 10);
        const at = s.backup_s3_last_restore_test_at || '';
        document.getElementById('rtLastAt').textContent = at ? fmtDateBR(at) : 'nunca';
        const ok = s.backup_s3_last_restore_test_ok;
        const okEl = document.getElementById('rtLastOk');
        if (at) okEl.innerHTML = String(ok) === '1' ? '<span class="text-emerald-500">✓</span>' : '<span class="text-red-500">✗</span>';
        else okEl.textContent = '';
        const err = s.backup_s3_last_restore_test_error || '';
        document.getElementById('rtLastErr').textContent = err ? `Erro: ${err.slice(0, 80)}` : '';
    }
    document.getElementById('btnRtSave').addEventListener('click', async () => {
        const body = {
            backup_s3_restore_test_enabled: document.getElementById('rtEnabled').checked ? '1' : '0',
            backup_s3_restore_test_interval_hours: String(Math.max(1, Math.min(720, parseInt(document.getElementById('rtInterval').value || '168', 10)))),
        };
        const r = await fetch('/api/v1/backup-offsite/settings', { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
        toast(r.ok ? 'Salvo. Aplica no próximo ciclo do worker.' : 'Erro ao salvar.', r.ok ? 'success' : 'error');
        loadRestoreTestPanel();
    });
    loadRestoreTestPanel();
    setInterval(loadRestoreTestPanel, 60000);

    // === Múltiplos destinos S3 ===
    async function loadDestinations() {
        const r = await fetch('/api/v1/backup-offsite/destinations', { headers: H });
        if (!r.ok) return;
        const d = await r.json();
        const items = d.items || [];
        document.getElementById('destBadge').textContent = items.length ? `${items.length} cadastrados / ${items.filter(i=>i.enabled).length} ativos` : 'nenhum';
        const list = document.getElementById('destList');
        if (!items.length) {
            list.innerHTML = '<div class="text-slate-500 italic">Nenhum destino adicional. Clique <strong>+ Adicionar</strong> pra ativar modo multi-destino.</div>';
            return;
        }
        list.innerHTML = items.map(it => {
            const stCls = it.last_status === 'ok' ? 'text-emerald-500' : (it.last_status === 'error' ? 'text-red-500' : 'text-slate-500');
            const stText = it.last_status || 'pendente';
            return `<div class="border border-slate-200 dark:border-white/10 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <strong class="text-sm">${escHtml(it.label)}</strong>
                        ${it.enabled ? '<span class="ml-2 text-[10px] text-emerald-500 font-black uppercase">ATIVO</span>' : '<span class="ml-2 text-[10px] text-slate-500 font-black uppercase">(off)</span>'}
                        <span class="ml-2 text-[10px] text-slate-500">prio ${it.priority}</span>
                    </div>
                    <div class="flex gap-2">
                        <button data-id="${it.id}" class="destTestBtn glass-btn text-[10px] uppercase font-black">Testar</button>
                        <button data-id="${it.id}" class="destEditBtn glass-btn text-[10px] uppercase font-black">Editar</button>
                        <button data-id="${it.id}" class="destDelBtn glass-btn !bg-red-600/80 !text-white text-[10px] uppercase font-black">Excluir</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-[10px] font-mono">
                    <div><span class="text-slate-500">Bucket:</span> ${escHtml(it.bucket)}</div>
                    <div><span class="text-slate-500">Region:</span> ${escHtml(it.region)}</div>
                    <div><span class="text-slate-500">Endpoint:</span> ${escHtml(it.endpoint || 'AWS default')}</div>
                    <div><span class="text-slate-500">Prefix:</span> ${escHtml(it.prefix || '—')}</div>
                    <div><span class="text-slate-500">Access key:</span> ${escHtml(it.access_key || '—')}</div>
                    <div><span class="text-slate-500">Secret:</span> ${escHtml(it.secret_key_masked || '(sem)')}</div>
                    <div><span class="text-slate-500">Retenção:</span> ${it.retention_count}</div>
                    <div><span class="text-slate-500">Último:</span> <span class="${stCls}">${escHtml(stText)}</span> ${it.last_upload_at ? fmtDateBR(it.last_upload_at) : ''}</div>
                </div>
                ${it.last_error ? `<p class="mt-2 text-[10px] text-red-500">⚠ ${escHtml(it.last_error.slice(0,200))}</p>` : ''}
            </div>`;
        }).join('');
        document.querySelectorAll('.destTestBtn').forEach(b => b.addEventListener('click', () => destAction(b.dataset.id, 'test')));
        document.querySelectorAll('.destEditBtn').forEach(b => b.addEventListener('click', () => destEdit(b.dataset.id)));
        document.querySelectorAll('.destDelBtn').forEach(b => b.addEventListener('click', () => destAction(b.dataset.id, 'delete')));
    }

    async function destAction(id, kind) {
        if (kind === 'test') {
            const r = await fetch(`/api/v1/backup-offsite/destinations/${id}/test`, { method: 'POST', headers: H });
            const d = await r.json().catch(() => ({}));
            toast(d.success ? 'Conexão OK ✓' : `Falha: ${d.error || r.statusText}`, d.success ? 'success' : 'error');
        } else if (kind === 'delete') {
            const ok = window.customConfirm ? await window.customConfirm('Excluir este destino? Backups já enviados ao S3 não são removidos.', 'Confirma?') : confirm('Excluir?');
            if (!ok) return;
            const r = await fetch(`/api/v1/backup-offsite/destinations/${id}`, { method: 'DELETE', headers: H });
            toast(r.ok || r.status === 204 ? 'Excluído.' : 'Erro.', r.ok ? 'success' : 'error');
            loadDestinations();
        }
    }

    async function destEdit(id) {
        // Edição rápida: usa prompts (form completo seria modal — pra MVP, prompts simples)
        const label = window.prompt('Label:');
        if (label === null) return;
        const body = {};
        if (label) body.label = label;
        const en = window.prompt('Enabled (1/0):');
        if (en !== null) body.enabled = en === '1';
        const sec = window.prompt('Novo secret_key (vazio = preserva):');
        if (sec) body.secret_key = sec;
        if (Object.keys(body).length === 0) return;
        const r = await fetch(`/api/v1/backup-offsite/destinations/${id}`, { method: 'PUT', headers: HJ, body: JSON.stringify(body) });
        toast(r.ok ? 'Atualizado.' : 'Erro.', r.ok ? 'success' : 'error');
        loadDestinations();
    }

    document.getElementById('btnDestAdd').addEventListener('click', async () => {
        const label = window.prompt('Label (ex: "AWS Primary"):');
        if (!label) return;
        const bucket = window.prompt('Bucket:');
        if (!bucket) return;
        const endpoint = window.prompt('Endpoint (vazio = AWS default):') || '';
        const region = window.prompt('Region:', 'us-east-1') || 'us-east-1';
        const prefix = window.prompt('Prefix (opcional, ex: unbound-dashboard/):') || '';
        const access_key = window.prompt('Access Key:') || '';
        const secret_key = window.prompt('Secret Key:') || '';
        const body = { label, bucket, endpoint, region, prefix, access_key, secret_key, retention_count: 10, enabled: true, priority: 100 };
        const r = await fetch('/api/v1/backup-offsite/destinations', { method: 'POST', headers: HJ, body: JSON.stringify(body) });
        const d = await r.json().catch(() => ({}));
        toast(r.ok || r.status === 201 ? 'Destino criado.' : `Falha: ${d.detail || r.statusText}`, r.ok ? 'success' : 'error');
        loadDestinations();
    });

    document.getElementById('btnDestUploadAll').addEventListener('click', async () => {
        const ok = window.customConfirm ? await window.customConfirm('Disparar upload em TODOS os destinos enabled agora? Cada destino gera tarball próprio.', 'Confirma?') : confirm('Upload em todos?');
        if (!ok) return;
        const r = await fetch('/api/v1/backup-offsite/destinations/upload-all', { method: 'POST', headers: H });
        const d = await r.json().catch(() => ({}));
        const ok_count = (d.results || []).filter(x => x.success).length;
        toast(r.ok ? `Upload feito em ${ok_count}/${d.count || 0} destinos.` : 'Erro.', r.ok ? 'success' : 'error');
        loadDestinations();
    });

    loadDestinations();
    setInterval(loadDestinations, 60000);
})();
</script>

</body>
</html>
