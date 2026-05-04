<?php

namespace App;

use PDO;
use PDOException;

require_once __DIR__ . '/Environment.php';

$sysTz = ''; 
if (file_exists('/etc/timezone')) {
    $sysTz = trim(file_get_contents('/etc/timezone'));
} else if (is_link('/etc/localtime')) {
    $target = readlink('/etc/localtime');
    if (preg_match('/zoneinfo\/(.*)$/', $target, $matches)) {
        $sysTz = trim($matches[1]);
    }
}

if (!empty($sysTz) && in_array($sysTz, timezone_identifiers_list())) {
    date_default_timezone_set($sysTz);
}

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        // MariaDB descontinuado em 2026-05-04. Stub que sempre lança PDOException
        // pra callers legacy serem pegos por try/catch externo e caírem pro fallback
        // FastAPI. NÃO restaurar `die()` aqui — quebra o handling em callers.
        throw new PDOException(
            'MariaDB descontinuado — use App\\ApiClient para FastAPI/DuckDB',
            0
        );
    }
}
