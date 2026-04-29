<?php
require_once __DIR__ . '/../src/UnboundConfigManager.php';
$mgr = new \App\UnboundConfigManager();
$config = $mgr->parseConfig();
$res = $mgr->applyConfig($config);
var_dump($res);
