<?php

/** Router for the disposable PHP development server, never a production entry. */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/environment.php';
$environment = elemacy_test_environment((string) getenv('ELEMACY_TEST_ENV'));
if (realpath($_SERVER['DOCUMENT_ROOT']) !== $environment['root']) {
    http_response_code(403);
    exit('Wrong integration document root.');
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path === '/__elemacy_integration_health') {
    header('Content-Type: application/json');
    echo json_encode(['token' => $environment['token']]);
    return;
}
if ($path !== '/' && is_file($environment['root'] . $path)) {
    return false;
}
require $environment['root'] . '/index.php';
