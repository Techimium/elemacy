<?php

/** Actual WordPress bootstrap; never fall back to the development wp-load.php. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/environment.php';

$elemacy_test_environment = elemacy_test_environment((string) getenv('ELEMACY_TEST_ENV'));
$elemacy_test_url = parse_url($elemacy_test_environment['url']);
$_SERVER['HTTP_HOST'] = $elemacy_test_url['host'] . (isset($elemacy_test_url['port']) ? ':' . $elemacy_test_url['port'] : '');
$_SERVER['SERVER_NAME'] = $elemacy_test_url['host'];
$_SERVER['SERVER_PORT'] = (string) ($elemacy_test_url['port'] ?? 80);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

require $elemacy_test_environment['root'] . '/wp-load.php';

if (DB_NAME !== $elemacy_test_environment['database']
    || realpath(ABSPATH) !== $elemacy_test_environment['root']
    || get_option('elemacy_integration_environment') !== $elemacy_test_environment['token']) {
    throw new RuntimeException('The loaded WordPress instance does not match the disposable environment.');
}
if (!defined('ELEMACY_INTEGRATION_TOKEN')) {
    define('ELEMACY_INTEGRATION_TOKEN', $elemacy_test_environment['token']);
}

if (!did_action('elementor/loaded') || !class_exists(Elemacy\Core\Elemacy::class)
    || !post_type_exists('elemacy_library')) {
    throw new RuntimeException('The isolated site must activate Elementor and Elemacy before testing.');
}

// Packaged verification must not fall back to a development PSR-4 implementation.
if (!empty($elemacy_test_environment['packages'])) {
    foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
        if (strpos(realpath($vendor), $elemacy_test_environment['root'] . '/') !== 0) {
            $loader->setPsr4('Elemacy\\', []);
            $loader->setPsr4('ElemacyPro\\', []);
        }
    }
    foreach (['elemacy' => Elemacy\Core\Elemacy::class, 'elemacy-pro' => ElemacyPro\Core\ElemacyPro::class] as $slug => $class) {
        if (isset($elemacy_test_environment['packages'][$slug])
            && strpos((new ReflectionClass($class))->getFileName(), $elemacy_test_environment['root'] . '/wp-content/plugins/' . $slug . '/') !== 0) {
            throw new RuntimeException('Packaged test loaded development code for ' . $slug);
        }
    }
}

require_once __DIR__ . '/fixtures/LibraryDocument.php';

function elemacy_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
