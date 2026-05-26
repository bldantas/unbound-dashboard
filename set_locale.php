<?php
/**
 * Endpoint simples pra trocar o locale via toggle do topbar.
 *
 * Sem dependência da API FastAPI — mantém puro PHP (toggle de UI
 * não precisa do JWT). Aceita POST com `?lang=pt-BR|en` e redireciona
 * de volta pra página de origem.
 */

require_once 'src/Auth.php';
require_once 'src/I18n.php';

use App\Auth;
use App\I18n;

Auth::check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = $_POST['lang'] ?? '';
    if (in_array($lang, I18n::supported(), true)) {
        I18n::set($lang);
    }
}

$ref = $_SERVER['HTTP_REFERER'] ?? '/index.php';
header('Location: ' . $ref);
exit;
