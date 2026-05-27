<?php
require_once 'src/Auth.php';
require_once 'src/I18n.php';

use App\Auth;

$loginLocale = \App\I18n::current();
$appVersion = @trim((string) @file_get_contents(__DIR__ . '/VERSION')) ?: '?';

// Se o sistema não foi instalado (ou não tem admin), exibe página estática
// com instruções. O wizard PHP foi removido em v2.2.x — install.sh agora
// cria o admin via api_service/tools/create_admin.py.
if (!file_exists(__DIR__ . '/data/.installed') || !\App\Auth::hasUsers()) {
    header('Location: not_installed.php');
    exit;
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$success_message = '';

if (isset($_GET['setup']) && $_GET['setup'] === 'success') {
    $success_message = 'Administrador criado com sucesso! Você já pode fazer o login.';
}

// Mensagens de motivo passadas por logoutWithReason() na Auth.php
$reasonMessages = [
    'jwt_expired' => 'Sua sessão expirou. Faça login novamente para continuar.',
];
if (isset($_GET['reason']) && isset($reasonMessages[$_GET['reason']])) {
    $error = $reasonMessages[$_GET['reason']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // OIDC callback: o frontend recebe #oidc=<jwt> e re-posta pra cá
    if (!empty($_POST['oidc_jwt'])) {
        $jwt = $_POST['oidc_jwt'];
        // Decodifica claims sem verificar (a API já validou ao emitir)
        $parts = explode('.', $jwt);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if ($payload && isset($payload['sub'], $payload['role'])) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id']   = (int)$payload['sub'];
                $_SESSION['username']  = $payload['username'] ?? 'sso-user';
                $_SESSION['role']      = $payload['role'];
                $_SESSION['api_jwt']   = $jwt;
                header('Location: index.php');
                exit;
            }
        }
        $error = 'JWT SSO inválido.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = \App\Auth::login($username, $password);

        if ($result['success']) {
            if (!empty($result['requires_totp'])) {
                header('Location: login_2fa.php');
                exit;
            }
            header('Location: index.php');
            exit;
        } else {
            $error = $result['message'] ?? 'Credenciais incorretas ou usuário inativo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($loginLocale) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Unbound DNS Dashboard</title>
    <script>
        // Aplica tema antes do Tailwind pra evitar FOUC
        (function () {
            try {
                var t = localStorage.getItem('theme');
                var prefDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (t === 'dark' || (t !== 'light' && prefDark)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (_) { document.documentElement.classList.add('dark'); }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light dark;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
        html:not(.dark) body { background: #f1f5f9; color: #0f172a; }
        html.dark body { background: #0b1120; color: #f8fafc; }

        /* Animated mesh background pro hero pane */
        .hero-mesh {
            background:
                radial-gradient(circle at 18% 25%, rgba(59, 130, 246, 0.35), transparent 45%),
                radial-gradient(circle at 80% 70%, rgba(168, 85, 247, 0.28), transparent 50%),
                radial-gradient(circle at 65% 15%, rgba(56, 189, 248, 0.22), transparent 45%),
                linear-gradient(135deg, #0b1120 0%, #1e1b4b 100%);
        }
        .hero-mesh::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 50% 50%, transparent 60%, rgba(2, 6, 23, 0.45) 100%);
            pointer-events: none;
        }
        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.05) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
            pointer-events: none;
        }
        .pulse-ring {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(56, 189, 248, 0.35);
            animation: ring-pulse 3.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes ring-pulse {
            0%   { transform: scale(0.7); opacity: 0.9; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        .login-card-enter {
            animation: card-enter 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        @keyframes card-enter {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        html:not(.dark) .login-card-light {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15);
        }
        html.dark .login-card-light {
            background: rgba(30, 41, 59, 0.45);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .login-input {
            background: rgba(15, 23, 42, 0.4);
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        html:not(.dark) .login-input {
            background: #f8fafc;
            color: #0f172a;
            border: 1px solid rgba(15, 23, 42, 0.1);
        }
        .login-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .login-input::placeholder { opacity: 0.5; }

        #login-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(2, 6, 23, 0.78);
            backdrop-filter: blur(4px);
        }

        #login-loader.is-visible {
            display: flex;
        }

        .login-loader-card {
            min-width: 230px;
            border-radius: 14px;
            border: 1px solid rgba(59, 130, 246, 0.35);
            background: rgba(15, 23, 42, 0.88);
            padding: 16px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 11px;
            font-weight: 800;
            color: #e2e8f0;
        }

        .login-loader-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #38bdf8;
            box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.5);
            animation: login-loader-pulse 1.2s ease-in-out infinite;
        }

        .login-loader-progress-track {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 4px;
            overflow: hidden;
            background: rgba(30, 41, 59, 0.9);
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }

        .login-loader-progress-bar {
            position: absolute;
            top: 0;
            left: -35%;
            width: 35%;
            height: 100%;
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.2), rgba(56, 189, 248, 0.95), rgba(14, 165, 233, 0.2));
            box-shadow: 0 0 12px rgba(56, 189, 248, 0.45);
            animation: login-loader-progress-slide 1.25s ease-in-out infinite;
        }

        @keyframes login-loader-pulse {
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

        @keyframes login-loader-progress-slide {
            0% {
                left: -35%;
            }
            100% {
                left: 100%;
            }
        }
    </style>
</head>

<body class="min-h-screen flex">
    <div id="login-loader" aria-live="polite" aria-busy="true">
        <div class="login-loader-card">
            <span class="login-loader-dot" aria-hidden="true"></span>
            <span>Entrando no painel...</span>
        </div>
        <div class="login-loader-progress-track" aria-hidden="true">
            <div class="login-loader-progress-bar"></div>
        </div>
    </div>

    <!-- LEFT: HERO (escondido em mobile) -->
    <aside class="hero-mesh hidden lg:flex relative w-1/2 xl:w-3/5 flex-col justify-between p-12 overflow-hidden">
        <div class="hero-grid" aria-hidden="true"></div>

        <!-- Top: logo + brand -->
        <div class="relative z-10 flex items-center gap-3">
            <div class="relative w-12 h-12 rounded-2xl bg-blue-600/20 border border-blue-400/40 flex items-center justify-center text-blue-300 shadow-lg shadow-blue-500/20">
                <span class="pulse-ring absolute inset-0"></span>
                <svg class="w-6 h-6 relative" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <div>
                <p class="text-white text-base font-black tracking-tight">Unbound DNS</p>
                <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Recursive Resolver Suite</p>
            </div>
        </div>

        <!-- Middle: tagline + features -->
        <div class="relative z-10 max-w-lg">
            <h1 class="text-white text-4xl xl:text-5xl font-black tracking-tight leading-tight mb-4">
                DNS rápido, privado<br>
                e <span class="bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">sob seu controle</span>.
            </h1>
            <p class="text-slate-300 text-base mb-8 leading-relaxed">
                Resolver recursivo com DoT/DoH inbound, blocklists multi-source, anti-DGA,
                multi-tenant, multi-host e observabilidade Prometheus.
            </p>

            <ul class="space-y-3 text-slate-300 text-sm">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span><strong class="text-white">DoH/DoT inbound</strong> com cert gerenciado + rate-limit per-token</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span><strong class="text-white">10 blocklists curadas</strong> + allowlist + anti-DGA + baseline ML</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span><strong class="text-white">Multi-host + Multi-tenant</strong> com RBAC, 2FA TOTP, SSO OIDC + group mapping</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span><strong class="text-white">Backup S3 cifrado</strong> + restore-test + Prometheus + Grafana</span>
                </li>
            </ul>
        </div>

        <!-- Bottom: version + health -->
        <div class="relative z-10 flex items-center justify-between text-[11px] text-slate-500">
            <span class="font-mono">v<?= htmlspecialchars($appVersion) ?></span>
            <span id="systemHealth" class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-500 animate-pulse"></span>
                <span class="font-black uppercase tracking-widest">Verificando…</span>
            </span>
        </div>
    </aside>

    <!-- RIGHT: form pane -->
    <main class="flex-1 flex items-center justify-center relative p-6 sm:p-10">
        <!-- Top toggles -->
        <div class="absolute top-4 right-4 flex items-center gap-2 z-20">
            <form method="POST" action="/set_locale.php" class="inline-block">
                <input type="hidden" name="lang" value="<?= $loginLocale === 'pt-BR' ? 'en' : 'pt-BR' ?>">
                <button type="submit" class="p-2.5 rounded-2xl bg-slate-200/70 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-white/10 transition-all text-[10px] font-black uppercase tracking-widest border border-transparent dark:border-white/5" title="Idioma / Language">
                    <?= $loginLocale === 'pt-BR' ? 'PT' : 'EN' ?>
                </button>
            </form>
            <button type="button" id="themeToggle" class="p-2.5 rounded-2xl bg-slate-200/70 dark:bg-white/5 text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-white/10 transition-all border border-transparent dark:border-white/5" title="Tema / Theme">
                <svg id="sunIcon" class="w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                <svg id="moonIcon" class="w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
        </div>

        <div class="login-card-enter w-full max-w-md p-8 rounded-3xl login-card-light relative">
            <div class="relative z-10">
                <!-- Mobile-only logo -->
                <div class="lg:hidden flex items-center justify-center mb-6">
                    <div class="w-14 h-14 bg-blue-600/15 text-blue-500 dark:text-blue-400 rounded-2xl flex items-center justify-center border border-blue-500/30">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                </div>

                <h2 class="text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white mb-1">Acessar painel</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mb-6">Use suas credenciais ou faça login via SSO.</p>

                <?php if ($success_message): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 p-3 rounded-xl text-xs mb-4 font-medium">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-300 p-3 rounded-xl text-xs mb-4 font-medium">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" class="space-y-4" id="login-form">
                    <div>
                        <label class="block text-slate-600 dark:text-slate-400 text-[10px] font-black mb-1.5 uppercase tracking-widest">Usuário</label>
                        <input type="text" name="username" required autofocus class="login-input w-full rounded-xl px-4 py-3 transition-all" placeholder="admin" autocomplete="username">
                    </div>
                    <div>
                        <label class="block text-slate-600 dark:text-slate-400 text-[10px] font-black mb-1.5 uppercase tracking-widest">Senha</label>
                        <div class="relative">
                            <input type="password" name="password" id="passwordInput" required class="login-input w-full rounded-xl px-4 py-3 pr-12 transition-all" placeholder="••••••••" autocomplete="current-password">
                            <button type="button" id="togglePassword" tabindex="-1" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors" title="Mostrar/ocultar senha">
                                <svg id="eyeOpen" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg id="eyeClosed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.879 9.878L3 3m6.878 6.878L21 21"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-between gap-3">
                        <a href="recover.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Esqueceu a senha?</a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-3 rounded-xl transition-all shadow-lg shadow-blue-500/30 focus:outline-none focus:ring-2 focus:ring-blue-400 flex items-center gap-2">
                            <span>Acessar</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </div>
                </form>

                <div id="oidc-section" class="hidden mt-6 pt-6 border-t border-slate-300/70 dark:border-white/10">
                    <div class="flex items-center justify-center mb-3">
                        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-black px-3">ou</p>
                    </div>
                    <a href="/api/v1/auth/oidc/login" class="block w-full text-center bg-slate-700 dark:bg-slate-800 hover:bg-slate-600 dark:hover:bg-slate-700 text-white font-bold px-6 py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                        Entrar com SSO
                    </a>
                </div>

                <p class="text-center text-[10px] text-slate-400 dark:text-slate-600 mt-6 font-mono">
                    v<?= htmlspecialchars($appVersion) ?> · <a href="/changelog.php" class="hover:underline">changelog</a>
                </p>
            </div>
        </div>
    </main>

    <script>
        // Hash-based OIDC callback redirect: /login.php#oidc=<jwt> → POST hidden
        (function () {
            const hash = window.location.hash || '';
            const m = hash.match(/oidc=([^&]+)/);
            if (m) {
                const jwt = decodeURIComponent(m[1]);
                // Posta o JWT pro login.php pra criar a session PHP
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'login.php';
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'oidc_jwt';
                inp.value = jwt;
                form.appendChild(inp);
                document.body.appendChild(form);
                form.submit();
                return;
            }
            // Detecta SSO habilitado e mostra botão
            fetch('/api/v1/auth/oidc/public-info').then(r => r.ok ? r.json() : null).then(d => {
                if (d && d.enabled) {
                    document.getElementById('oidc-section')?.classList.remove('hidden');
                }
            }).catch(() => {});
        })();

        (function () {
            const form = document.getElementById('login-form');
            const loader = document.getElementById('login-loader');
            let isSubmitting = false;

            if (!form || !loader) {
                return;
            }

            form.addEventListener('submit', function (event) {
                if (isSubmitting) {
                    return;
                }

                event.preventDefault();
                isSubmitting = true;
                loader.classList.add('is-visible');

                // Permite o browser pintar o overlay antes da navegação.
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        HTMLFormElement.prototype.submit.call(form);
                    });
                });
            });
        })();

        // Theme toggle
        (function () {
            const root = document.documentElement;
            const btn = document.getElementById('themeToggle');
            const sun = document.getElementById('sunIcon');
            const moon = document.getElementById('moonIcon');

            function paint() {
                const isDark = root.classList.contains('dark');
                if (isDark) {
                    sun.classList.remove('hidden');
                    moon.classList.add('hidden');
                } else {
                    sun.classList.add('hidden');
                    moon.classList.remove('hidden');
                }
            }
            paint();
            btn?.addEventListener('click', function () {
                const isDark = root.classList.contains('dark');
                if (isDark) {
                    root.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    root.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
                paint();
            });
        })();

        // Password visibility toggle
        (function () {
            const btn = document.getElementById('togglePassword');
            const inp = document.getElementById('passwordInput');
            const open = document.getElementById('eyeOpen');
            const closed = document.getElementById('eyeClosed');
            btn?.addEventListener('click', function () {
                const showing = inp.type === 'text';
                inp.type = showing ? 'password' : 'text';
                open.classList.toggle('hidden', !showing);
                closed.classList.toggle('hidden', showing);
            });
        })();

        // Public healthz badge no hero (sem auth)
        (function () {
            const badge = document.getElementById('systemHealth');
            if (!badge) return;
            fetch('/api/v1/healthz').then(function (r) {
                return r.ok ? r.json() : null;
            }).then(function (d) {
                if (d && d.status === 'ok') {
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>'
                        + '<span class="font-black uppercase tracking-widest text-emerald-400">Sistema operacional</span>';
                } else {
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>'
                        + '<span class="font-black uppercase tracking-widest text-red-400">API offline</span>';
                }
            }).catch(function () {
                badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>'
                    + '<span class="font-black uppercase tracking-widest text-amber-400">API sem resposta</span>';
            });
        })();
    </script>
</body>

</html>