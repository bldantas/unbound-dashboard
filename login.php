<?php
require_once 'src/Auth.php';

use App\Auth;

// Se o sistema não foi instalado, redireciona para o wizard de instalação
if (!file_exists(__DIR__ . '/data/.installed')) {
    header('Location: setup.php');
    exit;
}

// Se não houver usuários (edge case), também redireciona para o setup
if (!\App\Auth::hasUsers()) {
    header('Location: setup.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = \App\Auth::login($username, $password);

    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $result['message'] ?? 'Credenciais incorretas ou usuário inativo.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Unbound DNS Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b1120;
            color: #f8fafc;
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

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

<body class="flex items-center justify-center h-screen bg-[#0b1120]">
    <div id="login-loader" aria-live="polite" aria-busy="true">
        <div class="login-loader-card">
            <span class="login-loader-dot" aria-hidden="true"></span>
            <span>Entrando no painel...</span>
        </div>
        <div class="login-loader-progress-track" aria-hidden="true">
            <div class="login-loader-progress-bar"></div>
        </div>
    </div>

    <div class="glass-panel w-full max-w-md p-8 rounded-3xl shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4 opacity-5 text-blue-500">
            <svg class="w-32 h-32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path>
            </svg>
        </div>

        <div class="relative z-10">
            <div class="flex items-center justify-center mb-8">
                <div class="w-14 h-14 bg-blue-600/20 text-blue-400 rounded-2xl flex items-center justify-center border border-blue-500/30">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
            </div>

            <h1 class="text-3xl font-bold text-center text-white mb-2">Unbound DNS</h1>
            <p class="text-slate-400 text-center text-sm mb-8">Gestão de Infraestrutura e Camada de Segurança</p>

            <?php if ($success_message): ?>
                <div class="bg-blue-600 text-white shadow-lg shadow-blue-600/20 p-4 rounded-xl text-sm mb-6 text-center font-bold">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-500 text-white shadow-lg shadow-red-500/20 p-4 rounded-xl text-sm mb-6 text-center font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-5" id="login-form">
                <div>
                    <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Usuário</label>
                    <input type="text" name="username" required class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder-slate-600" placeholder="admin">
                </div>

                <div>
                    <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Senha</label>
                    <input type="password" name="password" required class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder-slate-600" placeholder="••••••••">
                </div>

                <div class="pt-4 flex items-center justify-between">
                    <a href="recover.php" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">Esqueceu a senha?</a>
                    <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-blue-500 transition-colors shadow-lg shadow-blue-900/50 focus:outline-none focus:ring-2 focus:ring-blue-400 flex items-center gap-2">
                        <span>Acessar</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
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
    </script>
</body>

</html>