<?php

/**
 * Dev-only: records the Elementor Pro Nav Menu widget's control catalogue as
 * metadata (no Pro source) in tests/integration/fixtures/nav-menu-reference-controls.json.
 * The nav-menu integration test compares Elemacy's Nav Menu controls against it,
 * so that test runs without Elementor Pro.
 *
 *   php tests/integration/tools/dump-nav-menu-reference.php /path/to/wordpress [output.json]
 *
 * The site needs Elementor Pro installed; it does not have to be active. Pro is
 * loaded for this process only. Every database write and outbound HTTP request is
 * refused, so the run is read-only even against a development site.
 */

use Elemacy\Tests\Integration\Fixtures\NavMenuControlCatalog;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$wordpress = rtrim((string) ($argv[1] ?? ''), '/');
$output = $argv[2] ?? dirname(__DIR__) . '/fixtures/nav-menu-reference-controls.json';

if ($wordpress === '' || !is_file($wordpress . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php dump-nav-menu-reference.php /path/to/wordpress [output.json]\n");
    exit(2);
}

const ELEMACY_REFERENCE_PLUGIN = 'elementor-pro/elementor-pro.php';

// Hooks registered before WordPress loads (WP_Hook::build_preinitialized_hooks).
$GLOBALS['wp_filter'] = [
    'option_active_plugins' => [10 => [[
        'function' => static function ($plugins) {
            $plugins = is_array($plugins) ? $plugins : [];

            return in_array(ELEMACY_REFERENCE_PLUGIN, $plugins, true) ? $plugins : array_merge($plugins, [ELEMACY_REFERENCE_PLUGIN]);
        },
        'accepted_args' => 1,
    ]]],
    'query' => [10 => [[
        'function' => static function ($query) {
            return preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE|RENAME)\b/i', (string) $query) ? '' : $query;
        },
        'accepted_args' => 1,
    ]]],
    'pre_http_request' => [10 => [[
        'function' => static function () {
            return new WP_Error('elemacy_reference_offline', 'Outbound HTTP is disabled while dumping the reference.');
        },
        'accepted_args' => 1,
    ]]],
];

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $wordpress . '/wp-load.php';
require_once dirname(__DIR__) . '/fixtures/NavMenuControlCatalog.php';

if (!defined('ELEMENTOR_PRO_VERSION') || !class_exists('\Elementor\Plugin')) {
    fwrite(STDERR, "Elementor Pro could not be loaded from {$wordpress}.\n");
    exit(3);
}

$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types('nav-menu');

if (!$widget) {
    fwrite(STDERR, "Elementor Pro did not register its nav-menu widget.\n");
    exit(4);
}

$controls = NavMenuControlCatalog::describe($widget);
$snapshot = [
    'reference' => [
        'widget' => 'nav-menu',
        'elementor_pro' => ELEMENTOR_PRO_VERSION,
        'elementor' => ELEMENTOR_VERSION,
    ],
    'controls' => $controls,
];

$json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($json === false || file_put_contents($output, $json . "\n") === false) {
    fwrite(STDERR, "Could not write {$output}.\n");
    exit(5);
}

fwrite(STDOUT, sprintf("Wrote %d controls from Elementor Pro %s to %s\n", count($controls), ELEMENTOR_PRO_VERSION, $output));
