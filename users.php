<?php
// Redirecionamento de compatibilidade.
// A gestão de usuários foi consolidada na aba `usuarios` de config.php
// em 2026-05-12 (v2.6.0). Esta página fica como redirect pra não quebrar
// bookmarks externos ou links antigos. Pode ser removida em release futura.

require_once 'src/Auth.php';
\App\Auth::check();
header('Location: config.php?tab=usuarios#tab-usuarios', true, 301);
exit;
