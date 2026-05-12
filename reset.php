<?php
require_once 'src/Auth.php';

$msg = '';
$msgType = '';
$success = false;

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$token = preg_replace('/[^a-zA-Z0-9_.-]/', '', $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($token === '') {
        $msg = 'Token de recuperação ausente.';
        $msgType = 'error';
    } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $msg = 'As senhas não coincidem.';
        $msgType = 'error';
    } elseif (strlen($_POST['password'] ?? '') < 6) {
        $msg = 'A senha deve ter pelo menos 6 caracteres.';
        $msgType = 'error';
    } else {
        $res = \App\Auth::resetPassword($token, $_POST['password']);
        if (!empty($res['success'])) {
            $msg = 'Senha redefinida com sucesso. Você já pode fazer login.';
            $msgType = 'success';
            $success = true;
        } else {
            $msg = $res['message'] ?? 'Falha ao redefinir senha.';
            $msgType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Unbound DNS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0b1120; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>

<body class="flex items-center justify-center h-screen bg-[#0b1120]">
    <div class="glass-panel w-full max-w-md p-8 rounded-3xl shadow-2xl relative overflow-hidden">
        <h2 class="text-2xl font-bold text-white mb-2">Nova Senha</h2>
        <p class="text-slate-400 text-sm mb-6">Defina uma nova senha (mínimo 6 caracteres).</p>

        <?php if ($token === ''): ?>
            <div class="bg-amber-500/15 border border-amber-500/30 text-amber-300 p-4 rounded-xl text-sm mb-6 font-bold">
                Token de recuperação ausente. Acesse o link recebido por email novamente.
                <a href="recover.php" class="block mt-2 underline">Solicitar novo link</a>
            </div>
        <?php elseif ($msg): ?>
            <div class="<?= $msgType === 'success' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-red-500/15 border-red-500/30 text-red-300' ?> border p-4 rounded-xl text-sm mb-6 font-bold">
                <?= htmlspecialchars($msg) ?>
                <?php if ($success): ?>
                    <a href="login.php" class="block mt-2 underline">Ir para Login →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$success && $token !== ''): ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div>
                    <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Nova Senha</label>
                    <input type="password" name="password" required minlength="6" autofocus class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                </div>
                <div>
                    <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Confirmar Senha</label>
                    <input type="password" name="confirm_password" required minlength="6" class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                </div>
                <div class="pt-4 flex items-center justify-between">
                    <a href="login.php" class="text-xs text-slate-400 hover:text-white transition-colors">← Cancelar</a>
                    <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-blue-500 transition-colors">Salvar Senha</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>
