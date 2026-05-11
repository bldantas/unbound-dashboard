<?php
require_once 'src/Auth.php';

\App\Auth::check();
if (!\App\Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';
$tempPassword = null;
$tempPasswordUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = 'CSRF token inválido. Recarregue a página.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_user') {
            $res = \App\Auth::addUser(
                $_POST['new_username'] ?? '',
                $_POST['new_password'] ?? '',
                $_POST['new_role'] ?? 'viewer',
                $_POST['new_email'] ?? null
            );
            $message = $res['message'];
            $messageType = $res['success'] ? 'success' : 'error';
        } elseif ($action === 'toggle_user') {
            $res = \App\Auth::toggleUserStatus((int)$_POST['user_id']);
            $message = $res['message'];
            $messageType = $res['success'] ? 'success' : 'error';
        } elseif ($action === 'delete_user') {
            $res = \App\Auth::deleteUser((int)$_POST['user_id']);
            $message = $res['message'];
            $messageType = $res['success'] ? 'success' : 'error';
        } elseif ($action === 'update_role') {
            $res = \App\Auth::updateRole((int)$_POST['user_id'], $_POST['new_role_value'] ?? '');
            $message = $res['message'];
            $messageType = $res['success'] ? 'success' : 'error';
        } elseif ($action === 'update_email') {
            $userId = (int)$_POST['user_id'];
            $newEmail = trim($_POST['new_email_value'] ?? '');
            $username = $_POST['target_username'] ?? '';
            if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $message = 'Email inválido.';
                $messageType = 'error';
            } else {
                $res = \App\Auth::updateEmail($username, $newEmail);
                $message = $res['message'];
                $messageType = $res['success'] ? 'success' : 'error';
            }
        } elseif ($action === 'reset_password') {
            $res = \App\Auth::adminResetPassword((int)$_POST['user_id']);
            $message = $res['message'];
            $messageType = $res['success'] ? 'success' : 'error';
            if (!empty($res['success'])) {
                $tempPassword = $res['temporary_password'] ?? null;
                $tempPasswordUser = $_POST['target_username'] ?? '';
            }
        }
    }
}

$allUsers = \App\Auth::getAllUsers();
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

// Helpers de formatação local
$fmtDate = function ($iso) {
    if (!$iso) return null;
    try {
        $dt = new DateTime($iso);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $iso;
    }
};
$relativeTime = function ($iso) {
    if (!$iso) return 'nunca';
    try {
        $ts = (new DateTime($iso))->getTimestamp();
        $diff = time() - $ts;
        if ($diff < 60) return 'agora';
        if ($diff < 3600) return floor($diff / 60) . ' min atrás';
        if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
        if ($diff < 86400 * 30) return floor($diff / 86400) . 'd atrás';
        return floor($diff / (86400 * 30)) . 'mes atrás';
    } catch (Exception $e) {
        return $iso;
    }
};

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title>Gestão de Usuários - Unbound Dashboard</title>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans antialiased h-screen overflow-hidden">
    <div class="flex h-full">
        <?php include 'includes/sidebar.php'; ?>

        <main class="flex-1 overflow-y-auto bg-slate-950">
            <?php include 'includes/topbar.php'; ?>
            <div class="page-container">
                <header class="page-header mb-8">
                    <div>
                        <h1 class="page-title flex items-center gap-3">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            Gestão de Usuários
                        </h1>
                        <p class="page-subtitle">Listar, criar, editar role/email, suspender, resetar senha e excluir contas.</p>
                    </div>
                    <button onclick="document.getElementById('modal-new-user').classList.remove('hidden')"
                            class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                        Novo Usuário
                    </button>
                </header>

                <?php if ($message): ?>
                    <div class="glass-panel mb-6 border-l-4 <?= $messageType === 'success' ? 'border-emerald-500' : 'border-red-500' ?>">
                        <p class="text-sm <?= $messageType === 'success' ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' ?>">
                            <?= htmlspecialchars($message) ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($tempPassword): ?>
                    <div class="glass-panel mb-6 border-l-4 border-amber-500">
                        <p class="text-[10px] font-black text-amber-700 dark:text-amber-300 uppercase tracking-widest mb-2">Senha temporária gerada</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300 mb-3">Entregue manualmente ao usuário <strong><?= htmlspecialchars($tempPasswordUser ?? '') ?></strong>. Esta senha não será exibida novamente.</p>
                        <div class="flex items-center gap-3 bg-slate-900/5 dark:bg-white/5 p-4 rounded-2xl border border-slate-200 dark:border-white/10">
                            <code id="temp-pw" class="font-mono text-base font-bold text-slate-900 dark:text-white tracking-wider"><?= htmlspecialchars($tempPassword) ?></code>
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('temp-pw').textContent); this.textContent='Copiada!';"
                                    class="glass-btn text-[10px] uppercase font-black">Copiar</button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Toolbar: busca + filtros -->
                <div class="glass-panel mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Buscar (username ou email)</label>
                            <input type="text" id="filter-search" oninput="filterUsers()" placeholder="ex: admin@empresa.com" class="glass-input w-full">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Role</label>
                            <select id="filter-role" onchange="filterUsers()" class="glass-input w-full uppercase text-[10px] font-black">
                                <option value="">TODOS</option>
                                <option value="admin">ADMIN</option>
                                <option value="viewer">VIEWER</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Status</label>
                            <select id="filter-status" onchange="filterUsers()" class="glass-input w-full uppercase text-[10px] font-black">
                                <option value="">TODOS</option>
                                <option value="active">ATIVOS</option>
                                <option value="inactive">SUSPENSOS</option>
                                <option value="locked">BLOQUEADOS</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-3">
                        Total: <span id="users-count-total"><?= count($allUsers) ?></span> · Visíveis: <span id="users-count-visible"><?= count($allUsers) ?></span>
                    </p>
                </div>

                <!-- Tabela de usuários -->
                <div class="glass-panel overflow-x-auto">
                    <table class="w-full text-sm" id="users-table">
                        <thead class="text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-900/10 dark:border-white/5">
                            <tr>
                                <th class="text-left py-3 px-2">Usuário</th>
                                <th class="text-left py-3 px-2">Email</th>
                                <th class="text-left py-3 px-2">Role</th>
                                <th class="text-left py-3 px-2">Status</th>
                                <th class="text-left py-3 px-2">Último Login</th>
                                <th class="text-left py-3 px-2">Criado</th>
                                <th class="text-right py-3 px-2">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u):
                                $isSelf = ((int)$u['id']) === $currentUserId;
                                $isLocked = !empty($u['locked_until']) && (new DateTime($u['locked_until']))->getTimestamp() > time();
                                $statusKey = $isLocked ? 'locked' : (!empty($u['is_active']) ? 'active' : 'inactive');
                                $username = $u['username'] ?? '';
                                $email = $u['email'] ?? '';
                                ?>
                                <tr class="user-row border-b border-slate-900/5 dark:border-white/5 hover:bg-slate-900/5 dark:hover:bg-white/5"
                                    data-username="<?= htmlspecialchars(strtolower($username)) ?>"
                                    data-email="<?= htmlspecialchars(strtolower($email)) ?>"
                                    data-role="<?= htmlspecialchars($u['role'] ?? '') ?>"
                                    data-status="<?= $statusKey ?>">
                                    <td class="py-3 px-2">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-2xl bg-<?= ($u['role'] ?? '') === 'admin' ? 'red' : 'blue' ?>-600/10 text-<?= ($u['role'] ?? '') === 'admin' ? 'red' : 'blue' ?>-500 flex items-center justify-center font-black text-xs">
                                                <?= strtoupper(substr($username, 0, 2)) ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($username) ?>
                                                    <?php if ($isSelf): ?><span class="text-[9px] uppercase font-black text-blue-500 ml-1">(você)</span><?php endif; ?>
                                                </p>
                                                <p class="text-[10px] text-slate-500 font-mono">ID #<?= (int)$u['id'] ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2">
                                        <form method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="update_email">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="target_username" value="<?= htmlspecialchars($username) ?>">
                                            <input type="email" name="new_email_value" value="<?= htmlspecialchars($email) ?>" placeholder="—"
                                                   class="glass-input !py-1 !px-2 text-xs w-48 font-mono">
                                            <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black opacity-50 hover:opacity-100" title="Salvar email">✓</button>
                                        </form>
                                    </td>
                                    <td class="py-3 px-2">
                                        <?php if ($isSelf): ?>
                                            <span class="text-xs font-mono uppercase font-bold text-slate-500"><?= htmlspecialchars($u['role'] ?? '') ?></span>
                                        <?php else: ?>
                                            <form method="POST" class="flex items-center gap-1">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <select name="new_role_value" onchange="this.form.submit()" class="glass-input !py-1 !px-2 text-xs uppercase font-black">
                                                    <option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>>ADMIN</option>
                                                    <option value="viewer" <?= ($u['role'] ?? '') === 'viewer' ? 'selected' : '' ?>>VIEWER</option>
                                                </select>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-2">
                                        <?php if ($isLocked): ?>
                                            <span class="inline-block px-2 py-1 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 text-[10px] uppercase font-black">
                                                Bloqueado
                                            </span>
                                            <p class="text-[9px] text-slate-500 mt-1"><?= htmlspecialchars($u['failed_logins'] ?? 0) ?> falhas · até <?= htmlspecialchars($fmtDate($u['locked_until']) ?? '') ?></p>
                                        <?php elseif (!empty($u['is_active'])): ?>
                                            <span class="inline-block px-2 py-1 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[10px] uppercase font-black">
                                                Ativo
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-block px-2 py-1 rounded-lg bg-red-500/15 text-red-600 dark:text-red-400 text-[10px] uppercase font-black">
                                                Suspenso
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-2 text-xs text-slate-600 dark:text-slate-400"><?= htmlspecialchars($relativeTime($u['last_login_at'] ?? null)) ?>
                                        <?php if (!empty($u['last_login_at'])): ?>
                                            <p class="text-[9px] text-slate-500"><?= htmlspecialchars($fmtDate($u['last_login_at'])) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-2 text-xs text-slate-600 dark:text-slate-400"><?= htmlspecialchars($fmtDate($u['created_at'] ?? null) ?? '—') ?></td>
                                    <td class="py-3 px-2 text-right">
                                        <div class="flex justify-end gap-1 flex-wrap">
                                            <form method="POST" data-confirm-message="Gerar nova senha temporária para <?= htmlspecialchars($username) ?>?" data-confirm-title="Reset de senha" data-confirm-text="Gerar senha">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="target_username" value="<?= htmlspecialchars($username) ?>">
                                                <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black" title="Resetar senha">🔑</button>
                                            </form>
                                            <?php if (!$isSelf): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="action" value="toggle_user">
                                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                    <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black" title="<?= !empty($u['is_active']) ? 'Suspender' : 'Ativar' ?>">
                                                        <?= !empty($u['is_active']) ? '⏸' : '▶' ?>
                                                    </button>
                                                </form>
                                                <form method="POST" data-confirm-message="Excluir permanentemente <?= htmlspecialchars($username) ?>? Esta ação não pode ser desfeita." data-confirm-title="Confirmar exclusão" data-confirm-text="Excluir" data-confirm-variant="danger">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                    <button type="submit" class="glass-btn !py-1 !px-2 text-[9px] uppercase font-black bg-red-500/10 text-red-500" title="Excluir">✕</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p id="users-empty" class="hidden text-center text-slate-500 text-sm py-8">Nenhum usuário atende aos filtros.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal: Novo Usuário -->
    <div id="modal-new-user" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[100] flex items-center justify-center p-4" onclick="if (event.target === this) this.classList.add('hidden')">
        <div class="glass-panel max-w-md w-full">
            <h3 class="text-sm font-black uppercase tracking-widest mb-4 text-slate-900 dark:text-white">Novo Usuário</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add_user">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Login</label>
                    <input type="text" name="new_username" required pattern="[a-zA-Z0-9._-]+" class="glass-input w-full" placeholder="ex: maria.silva">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Email (opcional)</label>
                    <input type="email" name="new_email" class="glass-input w-full" placeholder="maria@empresa.com">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Senha (mín. 6)</label>
                    <input type="password" name="new_password" required minlength="6" class="glass-input w-full" placeholder="••••••">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Role</label>
                    <select name="new_role" class="glass-input w-full uppercase text-[10px] font-black">
                        <option value="viewer">VIEWER (somente leitura)</option>
                        <option value="admin">ADMIN (acesso total)</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-4 border-t border-slate-900/10 dark:border-white/5">
                    <button type="button" onclick="document.getElementById('modal-new-user').classList.add('hidden')" class="glass-btn text-[10px] uppercase font-black">Cancelar</button>
                    <button type="submit" class="glass-btn !bg-blue-600 !text-white text-[10px] uppercase font-black">Criar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function filterUsers() {
            const q = (document.getElementById('filter-search').value || '').trim().toLowerCase();
            const role = document.getElementById('filter-role').value;
            const status = document.getElementById('filter-status').value;
            let visible = 0;
            document.querySelectorAll('.user-row').forEach(row => {
                const matchQ = !q || row.dataset.username.includes(q) || row.dataset.email.includes(q);
                const matchRole = !role || row.dataset.role === role;
                const matchStatus = !status || row.dataset.status === status;
                const show = matchQ && matchRole && matchStatus;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            document.getElementById('users-count-visible').textContent = visible;
            document.getElementById('users-empty').classList.toggle('hidden', visible !== 0);
        }
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
<?php
echo ob_get_clean();
