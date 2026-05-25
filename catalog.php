<?php
// Compat: catalog.php foi substituído por blocklists.php (multi-source).
// A busca paginada que ficava aqui agora é a aba "Busca no Catálogo".
require_once 'src/Auth.php';
\App\Auth::check();
header('Location: blocklists.php#tab-search', true, 302);
exit;
