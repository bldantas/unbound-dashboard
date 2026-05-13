<?php
require_once 'src/Auth.php';

use App\Auth;

// Acesso direto sem ter completado o passo 1 → manda pro login
if (empty($_SESSION['totp_challenge']) || empty($_SESSION['totp_username'])) {
    header('Location: login.php');
    exit;
}

// Challenge expira em 5min (TTL do JWT challenge no backend). Se já passou
// disso aqui no PHP, evita roundtrip e manda re-login.
$startedAt = (int) ($_SESSION['totp_started_at'] ?? 0);
if ($startedAt > 0 && (time() - $startedAt) > 300) {
    unset($_SESSION['totp_challenge'], $_SESSION['totp_username'], $_SESSION['totp_started_at']);
    header('Location: login.php?reason=jwt_expired');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string) ($_POST['code'] ?? ''));
    $result = \App\Auth::login2faSubmit($code);
    if ($result['success']) {
        header('Location: index.php');
        exit;
    }
    $error = $result['message'] ?? 'Código inválido.';
}

$username = htmlspecialchars((string) $_SESSION['totp_username']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação 2FA - Unbound DNS Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0b1120; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .otp-input { letter-spacing: 0.5em; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    </style>
</head>
<body class="flex items-center justify-center h-screen bg-[#0b1120]">
    <div class="glass-panel w-full max-w-md p-8 rounded-3xl shadow-2xl relative overflow-hidden">
        <div class="relative z-10">
            <div class="flex items-center justify-center mb-6">
                <div class="w-14 h-14 bg-amber-600/20 text-amber-400 rounded-2xl flex items-center justify-center border border-amber-500/30">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path>
                    </svg>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-center text-white mb-1">Verificação 2FA</h1>
            <p class="text-slate-400 text-center text-sm mb-6">
                Bem-vindo, <span class="text-blue-400 font-bold"><?= $username ?></span>.<br>
                Abra seu app autenticador e digite o código de 6 dígitos.
            </p>

            <?php if ($error): ?>
                <div class="bg-red-500 text-white shadow-lg shadow-red-500/20 p-3 rounded-xl text-sm mb-4 text-center font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5" autocomplete="off">
                <div>
                    <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Código TOTP</label>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7"
                           required autofocus
                           class="otp-input w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white text-center text-xl font-bold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                           placeholder="000000">
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="logout.php" class="text-xs text-slate-400 hover:text-blue-400 transition-colors">Cancelar</a>
                    <button type="submit" class="bg-amber-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-amber-500 transition-colors shadow-lg shadow-amber-900/40">
                        Verificar
                    </button>
                </div>
            </form>

            <p class="text-[10px] text-slate-500 text-center mt-6 leading-relaxed">
                Perdeu o acesso ao app autenticador? Peça pra um admin resetar seu 2FA pela aba <em>Gestão de Usuários</em>.
            </p>
        </div>
    </div>

    <script>
        // Auto-submit ao digitar 6 dígitos numéricos
        const input = document.querySelector('input[name="code"]');
        input.addEventListener('input', function () {
            const digits = this.value.replace(/\D/g, '');
            if (digits.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
