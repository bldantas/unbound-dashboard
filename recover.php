<?php
require_once 'src/Auth.php';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Email inválido.';
        $msgType = 'error';
    } else {
        // Sempre retorna a mesma mensagem (timing-safe — não revela se email existe).
        // Auth::requestPasswordReset chama o backend; se token gerado, tenta
        // mail() + grava em src/data/password-recovery.log de qualquer forma.
        $res = \App\Auth::requestPasswordReset($email);
        $msg = $res['message'] ?? 'Solicitação processada. Verifique seu email.';
        $msgType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Unbound DNS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0b1120; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>
<body class="flex items-center justify-center h-screen bg-[#0b1120]">
    <div class="glass-panel w-full max-w-md p-8 rounded-3xl shadow-2xl relative overflow-hidden">
        <h2 class="text-2xl font-bold text-white mb-2">Recuperação de Acesso</h2>
        <p class="text-slate-400 text-sm mb-6">Digite o email cadastrado. Você receberá um link válido por 10 minutos.</p>

        <?php if ($msg): ?>
            <div class="<?= $msgType === 'success' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-red-500/15 border-red-500/30 text-red-300' ?> border p-4 rounded-xl text-sm mb-6 font-bold">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Email</label>
                <input type="email" name="email" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder-slate-600"
                       placeholder="seu@email.com">
            </div>

            <div class="pt-4 flex items-center justify-between">
                <a href="login.php" class="text-xs text-slate-400 hover:text-white transition-colors">← Voltar ao Login</a>
                <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-blue-500 transition-colors">Enviar Link</button>
            </div>
        </form>

        <?php if ($msgType === 'success'): ?>
            <p class="text-[10px] text-slate-500 mt-6 text-center">
                Não recebeu? Verifique o spam. Se o servidor não tem MTA configurado, o admin pode<br>
                consultar o link em <code class="text-slate-400">src/data/password-recovery.log</code>.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
