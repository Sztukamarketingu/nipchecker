<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
echo json_encode([
    'gus_configured' => GUS_BIR1_KEY !== '',
    'gus_use_test' => defined('GUS_BIR1_USE_TEST') && GUS_BIR1_USE_TEST,
    'version' => APP_VERSION
], JSON_UNESCAPED_UNICODE);
