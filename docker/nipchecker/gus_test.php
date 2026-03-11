<?php
/**
 * Test połączenia z GUS API – diagnostyka
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

$nip = $_GET['nip'] ?? '8991722625';
$result = ['ok' => false, 'step' => '', 'error' => null, 'key_length' => strlen(GUS_BIR1_KEY ?? '')];

try {
    $result['step'] = 'init';
    $env = (defined('GUS_BIR1_USE_TEST') && GUS_BIR1_USE_TEST) ? 'dev' : 'prod';
    $gus = new \GusApi\GusApi(GUS_BIR1_KEY, $env);

    $result['step'] = 'login';
    $gus->login();

    $result['step'] = 'search';
    $reports = $gus->getByNip($nip);
    $gus->logout();

    $result['ok'] = true;
    $result['count'] = count($reports);
    if (!empty($reports)) {
        $r = $reports[0];
        $result['name'] = $r->getName();
        $result['regon'] = $r->getRegon();
    }
} catch (Throwable $e) {
    $result['error'] = $e->getMessage();
    $result['class'] = get_class($e);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
