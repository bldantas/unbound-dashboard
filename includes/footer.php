<?php
if (!isset($unbound)) {
    require_once __DIR__ . '/../src/UnboundManager.php';
    $versionCacheTtl = 600;
    $needsRefresh = !isset($_SESSION['unbound_version_cached'])
        || empty($_SESSION['unbound_version_cached'])
        || $_SESSION['unbound_version_cached'] === 'Desconhecida'
        || !isset($_SESSION['unbound_version_cached_at'])
        || ((time() - (int) $_SESSION['unbound_version_cached_at']) >= $versionCacheTtl);

    if ($needsRefresh) {
        $footerUnbound = new \App\UnboundManager();
        $_SESSION['unbound_version_cached'] = $footerUnbound->getVersion();
        $_SESSION['unbound_version_cached_at'] = time();
    }
    $unboundVersion = $_SESSION['unbound_version_cached'];
} else {
    $unboundVersion = $unbound->getVersion();
}
?>
<footer class="mt-auto py-8 text-center border-t border-white/5">
    <div class="flex flex-col md:flex-row items-center justify-center gap-4 md:gap-8 text-sm text-slate-500">
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2 text-blue-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="font-medium text-slate-400">Bruno Dantas</span>
        </div>
        
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2 text-emerald-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
            </svg>
            <a href="https://www.redeconexaonet.com.br" target="_blank" class="hover:text-blue-400 transition-colors">www.redeconexaonet.com.br</a>
        </div>
        
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2 text-purple-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-7 0V4"></path>
            </svg>
            <span>Unbound v<?= $unboundVersion ?></span>
        </div>
    </div>
    <div class="mt-4 text-[10px] text-slate-600 uppercase tracking-widest font-bold">
        Unbound DNS Manager &copy; <?= date('Y') ?>
    </div>
</footer>

<div id="appToastRoot" class="app-toast-root" aria-live="polite" aria-atomic="true"></div>
<div id="appModalRoot"></div>

<script>
    if (!window.AppUI) {
        window.AppUI = (() => {
            const toastRoot = document.getElementById('appToastRoot');
            const modalRoot = document.getElementById('appModalRoot');

            function getToastIcon(type) {
                if (type === 'success') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                if (type === 'error') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M10.29 3.86l-7.5 13A2 2 0 004.5 20h15a2 2 0 001.71-3.14l-7.5-13a2 2 0 00-3.42 0z"></path></svg>';
                if (type === 'warning') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M10.29 3.86l-7.5 13A2 2 0 004.5 20h15a2 2 0 001.71-3.14l-7.5-13a2 2 0 00-3.42 0z"></path></svg>';
                return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
            }

            function toast(message, type = 'info', options = {}) {
                if (!toastRoot || !message) return;

                const timeout = options.timeout ?? 5200;
                const toastEl = document.createElement('div');
                toastEl.className = `app-toast app-toast-${type}`;
                toastEl.innerHTML = `
                    <div class="app-toast-icon">${getToastIcon(type)}</div>
                    <div class="app-toast-content">
                        <div class="app-toast-title">${options.title || (type === 'success' ? 'Concluído' : type === 'error' ? 'Falha' : type === 'warning' ? 'Atenção' : 'Informação')}</div>
                        <div class="app-toast-message"></div>
                    </div>
                    <button type="button" class="app-toast-close" aria-label="Fechar notificação">×</button>
                `;
                toastEl.querySelector('.app-toast-message').textContent = message;

                const removeToast = () => {
                    toastEl.classList.add('is-leaving');
                    window.setTimeout(() => toastEl.remove(), 220);
                };

                toastEl.querySelector('.app-toast-close').addEventListener('click', removeToast);
                toastRoot.appendChild(toastEl);
                window.setTimeout(() => toastEl.classList.add('is-visible'), 20);
                if (timeout > 0) {
                    window.setTimeout(removeToast, timeout);
                }
            }

            function closeModal(modalEl, result, resolve) {
                modalEl.classList.remove('is-visible');
                window.setTimeout(() => {
                    modalEl.remove();
                    resolve(result);
                }, 180);
            }

            function confirm(options = {}) {
                return new Promise((resolve) => {
                    const title = options.title || 'Confirmar ação';
                    const message = options.message || 'Deseja continuar?';
                    const confirmText = options.confirmText || 'Confirmar';
                    const cancelText = options.cancelText || 'Cancelar';
                    const variant = options.variant || 'default';

                    const modalEl = document.createElement('div');
                    modalEl.className = 'app-modal-backdrop';
                    modalEl.innerHTML = `
                        <div class="app-modal-window app-modal-${variant}" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">
                            <div class="app-modal-caption">
                                <span class="app-modal-caption-dot"></span>
                                <span class="app-modal-caption-text">Sistema</span>
                            </div>
                            <div class="app-modal-body">
                                <h3 id="app-modal-title" class="app-modal-title"></h3>
                                <p class="app-modal-message"></p>
                            </div>
                            <div class="app-modal-actions">
                                <button type="button" class="app-modal-btn app-modal-cancel">${cancelText}</button>
                                <button type="button" class="app-modal-btn app-modal-confirm">${confirmText}</button>
                            </div>
                        </div>
                    `;

                    modalEl.querySelector('.app-modal-title').textContent = title;
                    modalEl.querySelector('.app-modal-message').textContent = message;

                    const cancelBtn = modalEl.querySelector('.app-modal-cancel');
                    const confirmBtn = modalEl.querySelector('.app-modal-confirm');

                    const finalize = (result) => {
                        document.removeEventListener('keydown', keyHandler);
                        closeModal(modalEl, result, resolve);
                    };

                    cancelBtn.addEventListener('click', () => finalize(false));
                    confirmBtn.addEventListener('click', () => finalize(true));
                    modalEl.addEventListener('click', (event) => {
                        if (event.target === modalEl) {
                            finalize(false);
                        }
                    });

                    const keyHandler = (event) => {
                        if (!document.body.contains(modalEl)) {
                            document.removeEventListener('keydown', keyHandler);
                            return;
                        }
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            finalize(false);
                        }
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            finalize(true);
                        }
                    };

                    document.addEventListener('keydown', keyHandler);
                    modalRoot.appendChild(modalEl);
                    window.setTimeout(() => {
                        modalEl.classList.add('is-visible');
                        confirmBtn.focus();
                    }, 20);
                });
            }

            document.addEventListener('submit', async (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) return;
                if (!form.dataset.confirmMessage || form.dataset.confirmed === 'true') return;

                event.preventDefault();
                const confirmed = await confirm({
                    title: form.dataset.confirmTitle || 'Confirmar ação',
                    message: form.dataset.confirmMessage,
                    confirmText: form.dataset.confirmText || 'Confirmar',
                    cancelText: form.dataset.confirmCancel || 'Cancelar',
                    variant: form.dataset.confirmVariant || 'default'
                });

                if (confirmed) {
                    form.dataset.confirmed = 'true';
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            }, true);

            return { toast, confirm };
        })();
    }
</script>
