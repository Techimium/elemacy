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
    if (!is_array($environment) || !in_array($environment['purpose'] ?? '', ['elemacy-disposable-integration-v1', 'elemacy-managed-browser-v1'], true)) {
        throw new RuntimeException('Refusing an unmarked integration environment.');
    }

    $token = $environment['token'] ?? '';
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{16}\z/', $token)) {
        throw new RuntimeException('Invalid disposable environment identity.');
    }

    $root = $environment['root'] ?? '';
    if ($environment['purpose'] === 'elemacy-managed-browser-v1') {
        $url = parse_url($environment['url'] ?? '');
        $host = $url['host'] ?? '';
        if (basename(dirname($path)) !== 'elemacy-browser-' . $token
            || !preg_match('/\Aelemacy-release(?:-[a-z0-9-]+)?\.test\z/', $host)
            || ($url['scheme'] ?? '') !== 'http' || isset($url['port']) || isset($url['user']) || isset($url['pass'])
            || ($environment['url'] ?? '') !== 'http://' . $host
            || $root !== '/var/www/' . $host || realpath($root) !== $root
            || ($environment['database'] ?? '') !== str_replace(['.', '-'], '_', $host)
            || ($environment['transport'] ?? '') !== 'external') {
            throw new RuntimeException('Refusing an unowned managed browser target.');
        }
        if (!hash_equals($environment['wp_config_sha256'] ?? '', (string) hash_file('sha256', $root . '/wp-config.php'))
            || empty($environment['packages']['elemacy']) || empty($environment['installed_files'])) {
            throw new RuntimeException('Managed browser target configuration or packages changed.');
        }
        foreach ($environment['installed_files'] as $file => $hash) {
            if (!preg_match('#\A(?:elemacy|elemacy-pro)/[a-zA-Z0-9_./-]+\z#', $file) || str_contains($file, '..')) {
                throw new RuntimeException('Invalid package file identity.');
            }
            $installed = $root . '/wp-content/plugins/' . $file;
            if (realpath($installed) !== $installed || !is_file($installed)
                || !hash_equals($hash, (string) hash_file('sha256', $installed))) {
                throw new RuntimeException('Installed release file changed: ' . $file);
            }
        }
        return $environment;
    }
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
