<?php

/**
 * Validate the disposable environment before WordPress or database code runs.
 * This file intentionally has no ABSPATH guard: wp-config.php also uses it.
 */
function elemacy_test_environment(string $filename): array
{
    $path = realpath($filename);
    if (!$path || !is_file($path)) {
        throw new RuntimeException('Set ELEMACY_TEST_ENV to the provisioned environment.json file.');
    }

    $environment = json_decode(file_get_contents($path), true);
    if (!is_array($environment) || ($environment['purpose'] ?? '') !== 'elemacy-disposable-integration-v1') {
        throw new RuntimeException('Refusing an unmarked integration environment.');
    }

    $token = $environment['token'] ?? '';
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{16}\z/', $token)) {
        throw new RuntimeException('Invalid disposable environment identity.');
    }

    $root = $environment['root'] ?? '';
    if (!is_string($root) || realpath($root) !== dirname($path) . '/wordpress'
        || basename(dirname($path)) !== 'elemacy-integration-' . $token) {
        throw new RuntimeException('Refusing a WordPress root outside the marked disposable directory.');
    }

    if (($environment['database'] ?? '') !== 'elemacy_integration_' . $token) {
        throw new RuntimeException('Refusing a database that does not belong to this disposable environment.');
    }

    $hash = $environment['wp_config_sha256'] ?? '';
    if (!is_string($hash) || !hash_equals($hash, (string) hash_file('sha256', $root . '/wp-config.php'))) {
        throw new RuntimeException('Refusing a replaced WordPress configuration; provision a fresh environment.');
    }

    $url = parse_url($environment['url'] ?? '');
    if (!is_array($url) || ($url['scheme'] ?? '') !== 'http' || ($url['host'] ?? '') !== '127.0.0.1'
        || empty($url['port']) || isset($url['user']) || isset($url['pass'])) {
        throw new RuntimeException('Integration HTTP must use an explicit loopback port.');
    }

    return $environment;
}
