<!-- includes/head.php -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
// JWT pra chamadas fetch() em endpoints novos da FastAPI (api_service/).
// Frontend lê via: document.querySelector('meta[name="api-jwt"]').content
// Nunca renderiza o token se sessão não autenticada (defesa em profundidade).
if (!empty($_SESSION['logged_in']) && !empty($_SESSION['api_jwt'])) {
    echo '<meta name="api-jwt" content="' . htmlspecialchars((string) $_SESSION['api_jwt'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

// i18n: serializa o dict da locale atual num global JS pra window.t() resolver
// no client. Páginas que carregam I18n via require_once ganham o helper.
if (class_exists('\\App\\I18n')) {
    $__i18nPayload = json_encode([
        'locale'  => \App\I18n::current(),
        'strings' => \App\I18n::all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "<script>window.__i18n = {$__i18nPayload};"
        . "window.t = function(key, vars){"
        . "var parts = String(key).split('.'), v = (window.__i18n && window.__i18n.strings) || {};"
        . "for (var i=0;i<parts.length;i++){"
        . "if (v && typeof v === 'object' && parts[i] in v) v = v[parts[i]]; else { v = null; break; }"
        . "}"
        . "if (typeof v !== 'string') return key;"
        . "if (vars) Object.keys(vars).forEach(function(k){ v = v.split('{'+k+'}').join(String(vars[k])); });"
        . "return v;"
        . "};</script>\n";
}
?>
<link rel="stylesheet" href="src/dashboard.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
    #global-page-loader {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(2, 6, 23, 0.72);
        backdrop-filter: blur(4px);
    }

    #global-page-loader.is-visible {
        display: flex;
    }

    #global-page-loader .loader-card {
        min-width: 240px;
        max-width: 92vw;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.28);
        background: rgba(15, 23, 42, 0.86);
        padding: 18px 22px;
        box-shadow: 0 10px 35px rgba(2, 6, 23, 0.45);
        color: #e2e8f0;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #global-page-loader .loader-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #38bdf8;
        box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.5);
        animation: loader-pulse 1.2s ease-in-out infinite;
    }

    #global-page-loader .loader-progress-track {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 4px;
        overflow: hidden;
        background: rgba(30, 41, 59, 0.9);
        border-top: 1px solid rgba(148, 163, 184, 0.18);
    }

    #global-page-loader .loader-progress-bar {
        position: absolute;
        top: 0;
        left: -35%;
        width: 35%;
        height: 100%;
        background: linear-gradient(90deg, rgba(14, 165, 233, 0.2), rgba(56, 189, 248, 0.95), rgba(14, 165, 233, 0.2));
        box-shadow: 0 0 12px rgba(56, 189, 248, 0.45);
        animation: loader-progress-slide 1.25s ease-in-out infinite;
    }

    @keyframes loader-pulse {
        0% {
            transform: scale(0.85);
            box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.45);
        }
        70% {
            transform: scale(1);
            box-shadow: 0 0 0 10px rgba(56, 189, 248, 0);
        }
        100% {
            transform: scale(0.85);
            box-shadow: 0 0 0 0 rgba(56, 189, 248, 0);
        }
    }

    @keyframes loader-progress-slide {
        0% {
            left: -35%;
        }
        100% {
            left: 100%;
        }
    }
</style>
<script>
    // Configuração do Tailwind para usar a classe 'dark' (DEVE vir DEPOIS do CDN)
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {}
        }
    }

    // Prevenção de FOUC (Flash of Unstyled Content)
    // Aplica o tema ANTES da renderização do body
    ;(function() {
        const theme = localStorage.getItem('theme') || 'system';
        const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();

    ;(function () {
        let loaderVisible = false;

        function ensureLoaderElement() {
            if (document.getElementById('global-page-loader')) {
                return;
            }

            const overlay = document.createElement('div');
            overlay.id = 'global-page-loader';
            overlay.setAttribute('aria-live', 'polite');
            overlay.setAttribute('aria-busy', 'true');
            overlay.innerHTML = '<div class="loader-card"><span class="loader-dot" aria-hidden="true"></span><span id="global-page-loader-text">Carregando painel...</span></div><div class="loader-progress-track" aria-hidden="true"><div class="loader-progress-bar"></div></div>';
            document.body.appendChild(overlay);
        }

        function resolveLoadingMessage(targetUrl) {
            if (!targetUrl) {
                return 'Carregando painel...';
            }

            const page = (targetUrl.pathname.split('/').pop() || '').toLowerCase();
            const messageMap = {
                'index.php': 'Carregando metricas do Dashboard...',
                'history.php': 'Carregando historico de consultas...',
                'logs.php': 'Carregando logs em tempo real...',
                'alerts.php': 'Carregando alertas ativos...',
                'threats.php': 'Carregando monitor de ameacas...',
                'blocklist.php': 'Carregando lista de bloqueio...',
                'dns_benchmark.php': 'Carregando benchmark DNS...',
                'diagnostics.php': 'Carregando diagnosticos do sistema...',
                'exports.php': 'Carregando exportacoes...',
                'config.php': 'Carregando configuracoes...',
                'health.php': 'Carregando saude e auditoria...',
                'changelog.php': 'Carregando changelog de versoes...',
                'not_installed.php': 'Verificando instalacao...',
                'recover.php': 'Carregando recuperacao de acesso...',
                'reset.php': 'Carregando redefinicao de senha...',
                'login.php': 'Carregando tela de login...'
            };

            return messageMap[page] || 'Carregando painel...';
        }

        function showLoader(message) {
            if (loaderVisible) {
                return;
            }

            ensureLoaderElement();
            const loader = document.getElementById('global-page-loader');
            const loaderText = document.getElementById('global-page-loader-text');
            if (loader) {
                if (loaderText) {
                    loaderText.textContent = message || 'Carregando painel...';
                }
                loader.classList.add('is-visible');
                loaderVisible = true;
            }
        }

        function isIgnorableLink(anchor) {
            const rawHref = (anchor.getAttribute('href') || '').trim();
            if (!rawHref || rawHref === '#' || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
                return true;
            }

            if (anchor.hasAttribute('download') || anchor.target === '_blank') {
                return true;
            }

            return false;
        }

        function getInternalTargetUrl(anchor) {
            if (isIgnorableLink(anchor)) {
                return null;
            }

            try {
                const targetUrl = new URL(anchor.href, window.location.href);
                if (targetUrl.origin !== window.location.origin) {
                    return null;
                }

                return targetUrl;
            } catch (e) {
                return null;
            }
        }

        function shouldShowLoaderForTargetUrl(targetUrl) {
            if (!targetUrl) {
                return false;
            }


            const currentPath = window.location.pathname + window.location.search;
            const targetPath = targetUrl.pathname + targetUrl.search;
            return currentPath !== targetPath;
        }

        window.__udShowPageLoader = showLoader;

        document.addEventListener('DOMContentLoaded', function () {
            ensureLoaderElement();

            document.addEventListener('click', function (event) {
                const anchor = event.target.closest('a[href]');
                if (!anchor || event.defaultPrevented || event.button !== 0) {
                    return;
                }

                if ((anchor.getAttribute('data-loader') || '').toLowerCase() === 'off') {
                    return;
                }

                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                const targetUrl = getInternalTargetUrl(anchor);
                if (shouldShowLoaderForTargetUrl(targetUrl)) {
                    showLoader(anchor.getAttribute('data-loader-text') || resolveLoadingMessage(targetUrl));
                }
            });

            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                if (event.defaultPrevented) {
                    return;
                }

                if ((form.getAttribute('data-loader') || '').toLowerCase() === 'off') {
                    return;
                }

                if (form.target && form.target.toLowerCase() === '_blank') {
                    return;
                }

                let targetUrl = null;
                try {
                    targetUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                } catch (e) {}

                showLoader(form.getAttribute('data-loader-text') || resolveLoadingMessage(targetUrl));
            });

            window.addEventListener('beforeunload', function () {
                showLoader('Carregando painel...');
            });
        });
    })();
</script>
