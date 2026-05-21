<?php
// includes/custom_modals.php — modais genéricos de confirm/alert,
// substituem window.confirm() / window.alert() nativos do browser.
//
// Uso:
//   include 'includes/custom_modals.php';
//
// JS exposto globalmente:
//   window.customConfirm(title, body, opts) → Promise<bool>
//     opts: { variant: 'info'|'success'|'warning'|'error'|'danger', okLabel, cancelLabel }
//   window.customAlert(title, body, variant) → Promise<void>
//     variant: 'info'|'success'|'warning'|'error'|'danger'
//
// ESC fecha; click no backdrop fecha; foco automático no botão primário.
?>

<!-- Modal genérico: confirmação -->
<div id="generic-confirm-modal" class="hidden fixed inset-0 z-[120] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="glass-panel max-w-md w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
        <div class="flex items-start gap-3 mb-4">
            <div id="generic-confirm-icon" class="shrink-0 w-10 h-10 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg font-black">!</div>
            <div class="min-w-0">
                <h3 id="generic-confirm-title" class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Confirmar</h3>
                <p id="generic-confirm-body" class="text-[12px] text-slate-600 dark:text-slate-400 mt-1 break-words whitespace-pre-line"></p>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" id="generic-confirm-cancel" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
            <button type="button" id="generic-confirm-ok" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">Confirmar</button>
        </div>
    </div>
</div>

<!-- Modal genérico: notificação (alert) -->
<div id="generic-alert-modal" class="hidden fixed inset-0 z-[120] flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="glass-panel max-w-md w-full !p-6 border-slate-200 dark:border-white/10 shadow-2xl">
        <div class="flex items-start gap-3 mb-4">
            <div id="generic-alert-icon" class="shrink-0 w-10 h-10 rounded-full bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 flex items-center justify-center text-lg font-black">i</div>
            <div class="min-w-0">
                <h3 id="generic-alert-title" class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-widest">Aviso</h3>
                <p id="generic-alert-body" class="text-[12px] text-slate-600 dark:text-slate-400 mt-1 break-words whitespace-pre-line"></p>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="button" id="generic-alert-ok" class="glass-btn !bg-cyan-600 !text-white text-[10px] uppercase font-black">OK</button>
        </div>
    </div>
</div>

<script>
(function () {
    // Guard: se o partial foi incluído mais de uma vez, mantém só a primeira.
    if (window.customConfirm) return;

    const ICON_VARIANTS = {
        info:    { glyph: 'i', cls: 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400' },
        success: { glyph: '✓', cls: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' },
        warning: { glyph: '!', cls: 'bg-amber-500/15 text-amber-600 dark:text-amber-400' },
        error:   { glyph: '✗', cls: 'bg-red-500/15 text-red-600 dark:text-red-400' },
        danger:  { glyph: '!', cls: 'bg-red-500/15 text-red-600 dark:text-red-400' },
    };

    const confirmModal = {
        root:    document.getElementById('generic-confirm-modal'),
        title:   document.getElementById('generic-confirm-title'),
        body:    document.getElementById('generic-confirm-body'),
        icon:    document.getElementById('generic-confirm-icon'),
        okBtn:   document.getElementById('generic-confirm-ok'),
        cancel:  document.getElementById('generic-confirm-cancel'),
        pending: null,
    };
    const alertModal = {
        root:    document.getElementById('generic-alert-modal'),
        title:   document.getElementById('generic-alert-title'),
        body:    document.getElementById('generic-alert-body'),
        icon:    document.getElementById('generic-alert-icon'),
        okBtn:   document.getElementById('generic-alert-ok'),
        pending: null,
    };

    function applyIcon(iconEl, variant) {
        const v = ICON_VARIANTS[variant] || ICON_VARIANTS.info;
        iconEl.textContent = v.glyph;
        iconEl.className = 'shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-lg font-black ' + v.cls;
    }

    window.customConfirm = function (title, body, opts) {
        opts = opts || {};
        return new Promise(resolve => {
            confirmModal.title.textContent = title || 'Confirmar';
            confirmModal.body.textContent  = body  || '';
            applyIcon(confirmModal.icon, opts.variant || 'warning');
            confirmModal.okBtn.textContent = opts.okLabel || 'Confirmar';
            confirmModal.cancel.textContent = opts.cancelLabel || 'Cancelar';
            confirmModal.okBtn.classList.remove('!bg-cyan-600', '!bg-red-600', '!bg-amber-600');
            confirmModal.okBtn.classList.add(
                opts.variant === 'danger' || opts.variant === 'error' ? '!bg-red-600' :
                opts.variant === 'warning' ? '!bg-amber-600' : '!bg-cyan-600'
            );
            confirmModal.pending = resolve;
            confirmModal.root.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => confirmModal.okBtn.focus(), 30);
        });
    };
    function closeConfirm(value) {
        if (!confirmModal.pending) return;
        const fn = confirmModal.pending;
        confirmModal.pending = null;
        confirmModal.root.classList.add('hidden');
        if (alertModal.root.classList.contains('hidden')) document.body.style.overflow = '';
        fn(value);
    }
    confirmModal.okBtn.addEventListener('click', () => closeConfirm(true));
    confirmModal.cancel.addEventListener('click', () => closeConfirm(false));
    confirmModal.root.addEventListener('click', (e) => { if (e.target === confirmModal.root) closeConfirm(false); });

    window.customAlert = function (title, body, variant) {
        return new Promise(resolve => {
            alertModal.title.textContent = title || 'Aviso';
            alertModal.body.textContent  = body  || '';
            applyIcon(alertModal.icon, variant || 'info');
            alertModal.okBtn.classList.remove('!bg-cyan-600', '!bg-red-600', '!bg-emerald-600', '!bg-amber-600');
            alertModal.okBtn.classList.add(
                variant === 'error' || variant === 'danger' ? '!bg-red-600' :
                variant === 'success' ? '!bg-emerald-600' :
                variant === 'warning' ? '!bg-amber-600' : '!bg-cyan-600'
            );
            alertModal.pending = resolve;
            alertModal.root.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => alertModal.okBtn.focus(), 30);
        });
    };
    function closeAlert() {
        if (!alertModal.pending) return;
        const fn = alertModal.pending;
        alertModal.pending = null;
        alertModal.root.classList.add('hidden');
        if (confirmModal.root.classList.contains('hidden')) document.body.style.overflow = '';
        fn();
    }
    alertModal.okBtn.addEventListener('click', closeAlert);
    alertModal.root.addEventListener('click', (e) => { if (e.target === alertModal.root) closeAlert(); });

    // ESC fecha qualquer um dos dois
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (confirmModal.pending) closeConfirm(false);
            else if (alertModal.pending) closeAlert();
        }
    });
})();
</script>
