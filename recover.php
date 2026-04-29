<?php
require_once 'src/Auth.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Implementar a lógica de recuperação de senha.
    // 1. Validar o 'username' recebido.
    // 2. Procurar o usuário no banco de dados.
    // 3. Se o usuário existir, gerar um token de reset de senha com tempo de expiração.
    // 4. Salvar o token no banco de dados associado ao usuário.
    // 5. Enviar um e-mail para o usuário com um link contendo o token.
    // 6. O link levará a uma nova página (ex: reset_password.php?token=...).
    $msg = 'Se o usuário existir, as instruções foram enviadas para o email cadastrado.';
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
        <h2 class="text-2xl font-bold text-white mb-4">Recuperação de Acesso</h2>
        <p class="text-slate-400 text-sm mb-6">Digite seu usuário para receber instruções de recuperação.</p>
        
        <?php if ($msg): ?>
            <div class="bg-emerald-500 text-white shadow-lg shadow-emerald-500/20 p-4 rounded-xl text-sm mb-6 text-center font-bold">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-slate-400 text-xs font-bold mb-2 uppercase tracking-wider">Usuário</label>
                <input type="text" name="username" required class="w-full bg-slate-900/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder-slate-600">
            </div>
            
            <div class="pt-4 flex items-center justify-between">
                <a href="login.php" class="text-xs text-slate-400 hover:text-white transition-colors">Voltar ao Login</a>
                <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-blue-500 transition-colors">Recuperar</button>
            </div>
        </form>
    </div>
</body>
</html>
