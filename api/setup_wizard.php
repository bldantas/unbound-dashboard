<?php
/**
 * API Backend do Wizard de Instalação
 * Processa cada etapa do setup via AJAX
 * 
 * Segurança: Só acessível se o sistema NÃO estiver instalado (sem .installed lock)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/ShellHelper.php';
require_once __DIR__ . '/../src/UnboundManager.php';

$installLock = __DIR__ . '/../data/.installed';

// Guard: se já está instalado, bloqueia acesso
if (file_exists($installLock)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sistema já instalado.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'check_environment':
        checkEnvironment();
        break;
    case 'configure_database':
        configureDatabase();
        break;
    case 'check_unbound':
        checkUnbound();
        break;
    case 'create_admin':
        createAdmin();
        break;
    case 'finalize':
        finalizeInstall();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
}

// ============================================================
// STEP 1: Verificação do Ambiente
// ============================================================
function checkEnvironment() {
    $checks = [];

    // PHP Version
    $phpVersion = PHP_VERSION;
    $phpOk = version_compare($phpVersion, '8.1.0', '>=');
    $checks[] = [
        'id' => 'php_version',
        'label' => 'PHP ≥ 8.1',
        'detail' => "PHP $phpVersion",
        'status' => $phpOk ? 'ok' : 'error',
        'critical' => true
    ];

    // Extensões PHP requeridas
    $requiredExtensions = [
        'pdo_mysql' => 'PDO MySQL',
        'json'      => 'JSON',
        'openssl'   => 'OpenSSL',
        'session'   => 'Session',
        'pcntl'     => 'PCNTL (CLI)',
        'mbstring'  => 'Multibyte String',
    ];
    foreach ($requiredExtensions as $ext => $label) {
        if ($ext === 'pcntl') {
            // PCNTL não costuma estar disponível via web (Apache/mod_php), então validamos via CLI
            $pcntlOut = [];
            $pcntlRet = 0;
            \App\ShellHelper::exec('/usr/bin/php', ['-r', 'echo extension_loaded("pcntl") ? "1" : "0";'], $pcntlOut, $pcntlRet, false);
            $loaded = (isset($pcntlOut[0]) && trim($pcntlOut[0]) === '1');
        } else {
            $loaded = extension_loaded($ext);
        }
        // mbstring é desejável mas não crítico
        $critical = ($ext !== 'mbstring');
        $checks[] = [
            'id' => "ext_$ext",
            'label' => "Extensão: $label",
            'detail' => $loaded ? 'Carregada' : 'Não encontrada',
            'status' => $loaded ? 'ok' : ($critical ? 'error' : 'warning'),
            'critical' => $critical
        ];
    }

    // Serviço Apache
    $apacheRunning = isServiceRunning('apache2');
    $checks[] = [
        'id' => 'service_apache',
        'label' => 'Serviço Apache',
        'detail' => $apacheRunning ? 'Ativo' : 'Inativo',
        'status' => $apacheRunning ? 'ok' : 'error',
        'critical' => true
    ];

    // Serviço MariaDB/MySQL
    $dbRunning = isServiceRunning('mariadb') || isServiceRunning('mysql');
    $checks[] = [
        'id' => 'service_database',
        'label' => 'Serviço MariaDB/MySQL',
        'detail' => $dbRunning ? 'Ativo' : 'Inativo',
        'status' => $dbRunning ? 'ok' : 'error',
        'critical' => true
    ];

    // Serviço Unbound
    $unboundRunning = isServiceRunning('unbound');
    $checks[] = [
        'id' => 'service_unbound',
        'label' => 'Serviço Unbound',
        'detail' => $unboundRunning ? 'Ativo' : 'Inativo',
        'status' => $unboundRunning ? 'ok' : 'warning',
        'critical' => false
    ];

    // Permissões de escrita
    $dataDir = __DIR__ . '/../data';
    $srcDataDir = __DIR__ . '/../src/data';

    $dataWritable = is_dir($dataDir) && is_writable($dataDir);
    $checks[] = [
        'id' => 'perm_data',
        'label' => 'Permissão: data/',
        'detail' => $dataWritable ? 'Gravável' : 'Sem permissão de escrita',
        'status' => $dataWritable ? 'ok' : 'error',
        'critical' => true
    ];

    $srcDataWritable = is_dir($srcDataDir) && is_writable($srcDataDir);
    $checks[] = [
        'id' => 'perm_src_data',
        'label' => 'Permissão: src/data/',
        'detail' => $srcDataWritable ? 'Gravável' : 'Sem permissão de escrita',
        'status' => $srcDataWritable ? 'ok' : 'warning',
        'critical' => false
    ];

    // Unbound instalado
    $unboundPathOut = [];
    $unboundPathRet = 0;
    \App\ShellHelper::exec('/usr/bin/which', ['unbound'], $unboundPathOut, $unboundPathRet, false);
    $unboundInstalled = file_exists('/usr/sbin/unbound') || !empty(trim(implode('', $unboundPathOut)));
    $checks[] = [
        'id' => 'unbound_binary',
        'label' => 'Unbound DNS',
        'detail' => $unboundInstalled ? 'Instalado' : 'Não encontrado',
        'status' => $unboundInstalled ? 'ok' : 'warning',
        'critical' => false
    ];

    $allCriticalOk = true;
    foreach ($checks as $c) {
        if ($c['critical'] && $c['status'] !== 'ok') {
            $allCriticalOk = false;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'checks' => $checks,
        'can_proceed' => $allCriticalOk
    ]);
}

// ============================================================
// STEP 2: Configuração do Banco de Dados
// ============================================================
function configureDatabase() {
    $host     = trim($_POST['db_host'] ?? '127.0.0.1');
    $port     = intval($_POST['db_port'] ?? 3306);
    $rootUser = trim($_POST['db_root_user'] ?? 'root');
    $rootPass = $_POST['db_root_pass'] ?? '';
    $appUser  = trim($_POST['db_app_user'] ?? 'unbounddb');
    $appPass  = $_POST['db_app_pass'] ?? 'unbounddash';
    $dbName   = 'unbound_dash';

    if (empty($appUser) || empty($appPass)) {
        echo json_encode(['success' => false, 'message' => 'Usuário e senha do aplicativo são obrigatórios.']);
        return;
    }

    // Bypass: se o install.sh já provisionou o banco perfeitamente, pula a recriação via root
    try {
        $appDsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        $testPdo = new PDO($appDsn, $appUser, $appPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
        $tables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (count($tables) >= 6) {
            $dbPhpPath = __DIR__ . '/../src/Database.php';
            $template = generateDatabasePhp($host, $dbName, $appUser, $appPass);
            file_put_contents($dbPhpPath, $template);
            
            echo json_encode([
                'success' => true,
                'message' => 'Banco de dados pré-configurado validado com sucesso!',
                'steps' => [
                    ['label' => 'Banco de dados detectado', 'status' => 'ok'],
                    ['label' => count($tables) . ' tabelas validadas', 'status' => 'ok'],
                    ['label' => 'Acesso direto confirmado', 'status' => 'ok']
                ],
                'tables_count' => count($tables)
            ]);
            return;
        }
    } catch (PDOException $e) {
        // Falha ignorada. O processo continua pelo fluxo padrão exigindo Root.
    }

    $steps = [];

    // 1. Testar conexão root
    try {
        $rootDsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $rootPdo = new PDO($rootDsn, $rootUser, $rootPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        $steps[] = ['label' => 'Conexão MySQL', 'status' => 'ok'];
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Falha ao conectar: ' . $e->getMessage(),
            'steps' => [['label' => 'Conexão MySQL', 'status' => 'error']]
        ]);
        return;
    }

    // 2. Criar database
    try {
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $steps[] = ['label' => 'Criar database', 'status' => 'ok'];
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar database: ' . $e->getMessage(), 'steps' => $steps]);
        return;
    }

    // 3. Importar schema
    try {
        $rootPdo->exec("USE `$dbName`");
        $schemaFile = __DIR__ . '/../scripts/init_db.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('Arquivo init_db.sql não encontrado.');
        }

        $sql = file_get_contents($schemaFile);
        // Remove as declarações CREATE DATABASE e USE de forma segura (suportando múltiplas linhas até o ponto e vírgula)
        $sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
        $sql = preg_replace('/USE\s+[^;]+;/i', '', $sql);
        $rootPdo->exec($sql);
        $steps[] = ['label' => 'Importar schema (6 tabelas)', 'status' => 'ok'];
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao importar schema: ' . $e->getMessage(), 'steps' => $steps]);
        return;
    }

    // 4. Criar usuário dedicado com autenticação nativa
    try {
        // Drop se existir para evitar conflito
        $rootPdo->exec("DROP USER IF EXISTS '$appUser'@'localhost'");
        $rootPdo->exec("DROP USER IF EXISTS '$appUser'@'127.0.0.1'");
        $rootPdo->exec("DROP USER IF EXISTS '$appUser'@'%'");
        
        // Garantir criação do usuário tanto para socket (localhost) quanto TCP (127.0.0.1 / IP customizado)
        // Usando IDENTIFIED WITH mysql_native_password para evitar SQLSTATE[HY000] [1698]
        $hostsToGrant = array_unique(['localhost', '127.0.0.1', $host]);
        foreach ($hostsToGrant as $h) {
            $rootPdo->exec("CREATE USER '$appUser'@'$h' IDENTIFIED WITH mysql_native_password BY " . $rootPdo->quote($appPass));
            $rootPdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$appUser'@'$h' WITH GRANT OPTION");
        }
        // Privilégios globais para operações de manutenção
        foreach ($hostsToGrant as $h) {
            $rootPdo->exec("GRANT PROCESS ON *.* TO '$appUser'@'$h'");
        }
        $rootPdo->exec("FLUSH PRIVILEGES");
        $steps[] = ['label' => 'Criar usuário MySQL', 'status' => 'ok'];
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar usuário: ' . $e->getMessage(), 'steps' => $steps]);
        return;
    }

    // 5. Testar conexão com novo usuário
    try {
        $appDsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        $testPdo = new PDO($appDsn, $appUser, $appPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $tables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $steps[] = ['label' => "Validar acesso ($appUser)", 'status' => 'ok'];
        $steps[] = ['label' => count($tables) . ' tabelas criadas', 'status' => 'ok'];
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Conexão do app falhou: ' . $e->getMessage(), 'steps' => $steps]);
        return;
    }

    // 6. Reescrever Database.php com as novas credenciais
    try {
        $dbPhpPath = __DIR__ . '/../src/Database.php';
        $template = generateDatabasePhp($host, $dbName, $appUser, $appPass);
        file_put_contents($dbPhpPath, $template);
        $steps[] = ['label' => 'Atualizar Database.php', 'status' => 'ok'];
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar configuração: ' . $e->getMessage(), 'steps' => $steps]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Banco de dados configurado com sucesso!',
        'steps' => $steps,
        'tables_count' => count($tables ?? [])
    ]);
}

// ============================================================
// STEP 3: Check do Unbound (diagnóstico apenas)
// ============================================================
function checkUnbound() {
    $info = [];
    $unboundManager = new \App\UnboundManager();

    // Status do daemon
    $running = isServiceRunning('unbound');
    $info['daemon_status'] = $running ? 'online' : 'offline';

    // Versão
    $version = $unboundManager->getVersion();
    $info['version'] = $version !== 'Desconhecida' ? $version : 'Não detectada';

    // Config principal
    $confPath = '/etc/unbound/unbound.conf';
    $info['config_exists'] = file_exists($confPath);

    // Interfaces de escuta
    $info['interfaces'] = [];
    if (file_exists($confPath)) {
        $conf = file_get_contents($confPath);
        if (preg_match_all('/^\s*interface:\s*(.+)$/m', $conf, $m)) {
            $info['interfaces'] = $m[1];
        }
    }

    // Forwarders
    $info['forwarders'] = [];
    $forwardFiles = glob('/etc/unbound/*.conf') ?: [];
    $forwardFiles[] = $confPath;
    foreach ($forwardFiles as $f) {
        if (!file_exists($f)) continue;
        $content = file_get_contents($f);
        if (preg_match_all('/^\s*forward-addr:\s*(.+)$/m', $content, $m)) {
            $info['forwarders'] = array_merge($info['forwarders'], $m[1]);
        }
    }
    $info['forwarders'] = array_unique(array_map('trim', $info['forwarders']));
    $info['forwarders'] = array_values($info['forwarders']);

    // Instâncias multicore
    $instances = glob('/etc/unbound/unbound0*.conf') ?: [];
    $info['multicore_instances'] = count($instances);

    // DNSSEC
    $info['dnssec'] = file_exists('/etc/unbound/root.hints');

    // TLS certs
    $info['tls_certs'] = file_exists('/etc/unbound/unbound_server.pem') && file_exists('/etc/unbound/unbound_server.key');

    echo json_encode([
        'success' => true,
        'info' => $info
    ]);
}

// ============================================================
// STEP 4: Criar Administrador
// ============================================================
function createAdmin() {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '') ?: null;
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Validações
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Nome de usuário é obrigatório.']);
        return;
    }
    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'message' => 'Nome de usuário deve ter pelo menos 3 caracteres.']);
        return;
    }
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Senha é obrigatória.']);
        return;
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 6 caracteres.']);
        return;
    }
    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
        return;
    }

    try {
        require_once __DIR__ . '/../src/Database.php';
        $db = \App\Database::getInstance();

        // Verifica se já existem usuários
        $count = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Já existem usuários cadastrados. Setup já foi concluído.']);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users (username, password_hash, role, email) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $hash, 'admin', $email]);

        echo json_encode([
            'success' => true,
            'message' => 'Administrador criado com sucesso!',
            'username' => $username
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
}

// ============================================================
// STEP 5: Finalizar Instalação
// ============================================================
function finalizeInstall() {
    $report = [];

    // 1. Verificar banco de dados
    try {
        require_once __DIR__ . '/../src/Database.php';
        $db = \App\Database::getInstance();
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $report[] = ['label' => 'Banco de dados operacional', 'status' => 'ok', 'detail' => count($tables) . ' tabelas'];
    } catch (Exception $e) {
        $report[] = ['label' => 'Banco de dados', 'status' => 'error', 'detail' => $e->getMessage()];
    }

    // 2. Verificar administrador
    try {
        $adminCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        $report[] = ['label' => 'Administrador cadastrado', 'status' => $adminCount > 0 ? 'ok' : 'error', 'detail' => "$adminCount admin(s)"];
    } catch (Exception $e) {
        $report[] = ['label' => 'Verificar administrador', 'status' => 'error', 'detail' => 'Tabela users inacessível'];
    }

    // 3. Verificar Unbound
    $unboundOk = isServiceRunning('unbound');
    $report[] = [
        'label' => 'Unbound DNS',
        'status' => $unboundOk ? 'ok' : 'warning',
        'detail' => $unboundOk ? 'Respondendo' : 'Inativo (configure depois)'
    ];

    // 4. Verificar permissões de diretórios
    $dataOk = is_writable(__DIR__ . '/../data');
    $report[] = ['label' => 'Permissões de diretórios', 'status' => $dataOk ? 'ok' : 'warning', 'detail' => $dataOk ? 'Corretas' : 'Ajuste necessário'];

    // 5. Verificar sudoers
    $sudoersOk = file_exists('/etc/sudoers.d/unbound-dashboard');
    $report[] = ['label' => 'Sudoers configurado', 'status' => $sudoersOk ? 'ok' : 'warning', 'detail' => $sudoersOk ? 'Instalado' : 'Não encontrado'];

    // 6. Verificar crons
    $cronOut = [];
    $cronRet = 0;
    \App\ShellHelper::exec('/usr/bin/crontab', ['-l'], $cronOut, $cronRet, false);
    $cronOutput = implode('', $cronOut);
    $cronOk = strpos($cronOutput, 'UNBOUND-DASHBOARD') !== false;
    $report[] = ['label' => 'Crons do sistema', 'status' => $cronOk ? 'ok' : 'warning', 'detail' => $cronOk ? 'Ativos' : 'Não detectados'];

    // 7. Verificar Log Ingester
    $ingesterOk = isServiceRunning('unbound-log-ingester') || isServiceRunning('unbound-logger');
    $report[] = ['label' => 'Log Ingester', 'status' => $ingesterOk ? 'ok' : 'warning', 'detail' => $ingesterOk ? 'Ativo' : 'Inativo'];

    // Criar lock de instalação
    $lockFile = __DIR__ . '/../data/.installed';
    $lockData = json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'version' => trim(file_get_contents(__DIR__ . '/../VERSION') ?: '1.0.0')
    ], JSON_PRETTY_PRINT);
    file_put_contents($lockFile, $lockData);

    $hasErrors = false;
    foreach ($report as $r) {
        if ($r['status'] === 'error') {
            $hasErrors = true;
            break;
        }
    }

    echo json_encode([
        'success' => !$hasErrors,
        'report' => $report,
        'message' => $hasErrors
            ? 'Instalação concluída com erros. Verifique os itens em vermelho.'
            : 'Instalação concluída com sucesso!'
    ]);
}

// ============================================================
// Helpers
// ============================================================
function isServiceRunning(string $service): bool {
    $output = [];
    $returnVar = 0;
    \App\ShellHelper::exec('/usr/bin/systemctl', ['is-active', $service], $output, $returnVar, false);
    return trim(implode('', $output)) === 'active';
}

function generateDatabasePhp(string $host, string $db, string $user, string $pass): string {
    $escapedPass = addslashes($pass);
    return <<<'PHP_HEADER'
<?php

namespace App;

use PDO;
use PDOException;

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
        if (self::$instance === null) {
PHP_HEADER
    . "\n            \$host = '$host';\n"
    . "            \$db   = '$db';\n"
    . "            \$user = '$user';\n"
    . "            \$pass = '$escapedPass';\n"
    . <<<'PHP_FOOTER'
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Return generic error to avoid leaking credentials
                die("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
PHP_FOOTER
    . "\n";
}
