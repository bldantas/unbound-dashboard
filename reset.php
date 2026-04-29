<?php
require_once 'src/Auth.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] === $_POST['confirm_password']) {
        $msg = 'Senha redefinida com sucesso.';
    } else {
        $msg = 'As senhas não coincidem.';
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
    </style>
</head>

<body class="flex items-center justify-center h-screen bg-[#0b1120]">
    <div class="glass-panel w-full max-w-md p-8 rounded-3xl shadow-2xl relative overflow-hidden">
        <h2 class="text-2xl font-bold text-white mb-4">Nova Senha</h2>
        <p class="text-slate-400 text-sm mb-6">Digite sua nova senha abaixo.</p>

        <?php if ($msg): ?>
            <div class="<?= strpos($msg, 'sucesso') !== false ? 'bg-blue-600 shadow-blue-600/20' : 'bg-red-500 shadow-red-500/20' ?> text-white shadow-lg p-4 rounded-xl text-sm mb-6 text-center font-bold">
                <?= htmlspecialchars($msg) ?>
                <?php if (strpos($msg, 'sucesso') !== false): ?>
                    <a href="login.php" class="block mt-2 font-bold underline">Fazer Login</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Nova Senha</label>
                <input type="password" name="password" required class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
            </div>
            <div>
                <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Confirmar Senha</label>
                <input type="password" name="confirm_password" required class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
            </div>
            <div class="pt-4 flex items-center justify-between">
                <a href="login.php" class="text-xs text-slate-400 hover:text-white transition-colors">Voltar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-blue-500 transition-colors">Salvar</button>
            </div>
        </form>
    </div>
</body>

</html>