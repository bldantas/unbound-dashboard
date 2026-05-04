<?php
/**
 * Página exibida quando o sistema é acessado antes do install.sh ter
 * concluído (data/.installed ausente OU sem usuários no DuckDB).
 *
 * Substitui o antigo wizard PHP (setup.php), removido em v2.2.x junto
 * com o tear-down do MariaDB. O bootstrap do admin agora é feito
 * exclusivamente pelo install.sh / api_service/tools/create_admin.py.
 */
http_response_code(503);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema não instalado — Unbound Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0b1120; color: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 24px; }
        .card { max-width: 640px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 40px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4); }
        h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 8px; color: #f59e0b; }
        h2 { font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin: 24px 0 8px; }
        p { line-height: 1.6; color: #cbd5e1; margin: 8px 0; }
        code { background: rgba(15, 23, 42, 0.8); padding: 2px 8px; border-radius: 4px; font-size: 0.9em; color: #67e8f9; }
        pre { background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 16px; overflow-x: auto; font-size: 0.85rem; color: #e2e8f0; }
        .muted { color: #64748b; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚠ Sistema ainda não instalado</h1>
        <p>O Unbound Dashboard não detectou uma instalação concluída neste servidor.</p>
        <p class="muted">Falta o marcador <code>data/.installed</code> ou não há admin criado no DuckDB.</p>

        <h2>Como instalar</h2>
        <p>Use o pacote oficial:</p>
        <pre>tar xzf unbound-dashboard-v&lt;X.Y.Z&gt;.tar.gz
cd unbound-dashboard-v&lt;X.Y.Z&gt;
sudo bash install.sh</pre>
        <p>O instalador pede o usuário/senha do admin no fim do processo. Para modo não-interativo:</p>
        <pre>ADMIN_USERNAME=admin ADMIN_PASSWORD='senhaSegura' \
    sudo -E bash install.sh</pre>

        <h2>Já instalou e está vendo isto?</h2>
        <p>Verifique:</p>
        <pre>ls -la /var/www/html/unbound-dashboard/data/.installed
sudo systemctl status unbound-dashboard-api</pre>
        <p>Logs e troubleshooting em <code>docs/TROUBLESHOOTING.md</code>.</p>
    </div>
</body>
</html>
