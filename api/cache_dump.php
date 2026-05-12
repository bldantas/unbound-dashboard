<?php
/**
 * API: dump do cache do Unbound (rrset + msg).
 *
 * Executa `unbound-control dump_cache`, parseia rrset_cache e msg_cache,
 * limita a 5000 entries por seção (mais que isso o browser engasga ao
 * filtrar client-side), agrega stats e retorna como JSON.
 *
 * Cache em arquivo `src/data/tmp/unbound_cache_dump.json` por 30s
 * pra não estressar o daemon a cada page reload — `?force=1` ignora.
 */
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ShellHelper.php';

use App\Auth;
use App\ShellHelper;

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

const ENTRY_LIMIT = 5000;
const CACHE_TTL_SECONDS = 30;
$cacheFile = __DIR__ . '/../src/data/tmp/unbound_cache_dump.json';

$force = !empty($_GET['force']);
if (!$force && file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < CACHE_TTL_SECONDS) {
        readfile($cacheFile);
        exit;
    }
}

// Chama unbound-control dump_cache (sudoers já permite wildcard).
$out = [];
$ret = 0;
ShellHelper::exec('/usr/sbin/unbound-control', ['dump_cache'], $out, $ret, true);
if ($ret !== 0) {
    http_response_code(500);
    echo json_encode([
        'error' => 'unbound-control dump_cache falhou',
        'detail' => implode("\n", $out),
    ]);
    exit;
}

$rrset = [];
$msg = [];
$ttlBuckets = ['expired' => 0, 'lt_60' => 0, 'lt_300' => 0, 'lt_3600' => 0, 'lt_86400' => 0, 'gte_86400' => 0];
$typesCount = [];
$tldsCount = [];

$section = '';
$rrsetCount = 0;
$rrsetTruncated = false;
$msgCount = 0;
$msgTruncated = false;

foreach ($out as $line) {
    $line = rtrim($line);
    if ($line === '') continue;

    if ($line === 'START_RRSET_CACHE') { $section = 'rrset'; continue; }
    if ($line === 'END_RRSET_CACHE')   { $section = '';      continue; }
    if ($line === 'START_MSG_CACHE')   { $section = 'msg';   continue; }
    if ($line === 'END_MSG_CACHE')     { $section = '';      continue; }

    if ($section === 'rrset') {
        if (strncmp($line, ';rrset ', 7) === 0) {
            // Header — só descarta (próximas linhas são as records)
            continue;
        }
        if (strncmp($line, ';', 1) === 0) continue;

        // Formato: owner\tttl\tIN\ttype\trdata
        $parts = preg_split('/\t+/', $line, 5);
        if (count($parts) < 5) continue;
        [$owner, $ttl, $class, $type, $rdata] = $parts;
        if ($class !== 'IN') continue;

        $ttlInt = (int) $ttl;
        // Buckets de TTL pra gráfico
        if ($ttlInt <= 0)        $ttlBuckets['expired']++;
        elseif ($ttlInt < 60)    $ttlBuckets['lt_60']++;
        elseif ($ttlInt < 300)   $ttlBuckets['lt_300']++;
        elseif ($ttlInt < 3600)  $ttlBuckets['lt_3600']++;
        elseif ($ttlInt < 86400) $ttlBuckets['lt_86400']++;
        else                     $ttlBuckets['gte_86400']++;

        // Type counter
        $typesCount[$type] = ($typesCount[$type] ?? 0) + 1;

        // TLD counter (último label antes do trailing dot)
        $tld = '';
        if (preg_match('/\.([a-zA-Z0-9-]+)\.$/', $owner, $m)) {
            $tld = strtolower($m[1]);
            $tldsCount[$tld] = ($tldsCount[$tld] ?? 0) + 1;
        }

        if ($rrsetCount < ENTRY_LIMIT) {
            $rrset[] = [
                'owner'  => rtrim($owner, '.'),
                'ttl'    => $ttlInt,
                'type'   => $type,
                'rdata'  => $rdata,
                'tld'    => $tld,
            ];
        } else {
            $rrsetTruncated = true;
        }
        $rrsetCount++;
    } elseif ($section === 'msg') {
        if (strncmp($line, 'msg ', 4) !== 0) continue;
        // Formato: msg <qname> IN <qtype> <flags> <ttl> <an> <ns> <ar> <queries> <result> [security]
        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 6) continue;
        $qname = $parts[1];
        $qtype = $parts[3] ?? '';
        $flags = (int) ($parts[4] ?? 0);
        $ttl   = (int) ($parts[5] ?? 0);

        if ($msgCount < ENTRY_LIMIT) {
            $msg[] = [
                'qname' => rtrim($qname, '.'),
                'qtype' => $qtype,
                'ttl'   => $ttl,
                'flags' => $flags,
            ];
        } else {
            $msgTruncated = true;
        }
        $msgCount++;
    }
}

// Ordena rrset por TTL desc (entries mais "frescas" primeiro)
usort($rrset, fn($a, $b) => $b['ttl'] <=> $a['ttl']);
usort($msg,   fn($a, $b) => $b['ttl'] <=> $a['ttl']);

// Top types / TLDs (limit 10)
arsort($typesCount);
$topTypes = array_slice($typesCount, 0, 10, true);
arsort($tldsCount);
$topTlds = array_slice($tldsCount, 0, 10, true);

$payload = [
    'generated_at' => time(),
    'stats' => [
        'rrset_total'      => $rrsetCount,
        'rrset_shown'      => count($rrset),
        'rrset_truncated'  => $rrsetTruncated,
        'msg_total'        => $msgCount,
        'msg_shown'        => count($msg),
        'msg_truncated'    => $msgTruncated,
        'ttl_buckets'      => $ttlBuckets,
        'top_types'        => $topTypes,
        'top_tlds'         => $topTlds,
        'distinct_types'   => count($typesCount),
        'distinct_tlds'    => count($tldsCount),
    ],
    'rrset' => $rrset,
    'msg'   => $msg,
];

$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Cacheia em arquivo. Falha de write é silenciosa (próxima call regenera).
@mkdir(dirname($cacheFile), 0775, true);
@file_put_contents($cacheFile, $json);

echo $json;
