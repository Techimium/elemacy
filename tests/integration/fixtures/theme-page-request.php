<?php

/**
 * Renders one front-end page in its own PHP process, the way a real request
 * does, and prints {html, calls} as JSON. See ThemePageRequest.
 *
 * ThemeLifecycleHarness renders many pages in one process, which three
 * things in native chrome replacement cannot survive: a theme's header.php
 * is require_once'd (a second render never loads it), the admin bar renders
 * at most once per process (a function-static guard), and the generic
 * adapter empties the shell hooks once its own shell has run them.
 *
 * Input on STDIN: {theme, url, user: "admin"|"guest", keep_native: string[], force_generic?: bool, third_party?: array<string,string>,
 * options?: array<string,mixed>, exit_on_template_redirect?: string}.
 * The theme and any `options` are forced through pre_option filters seeded
 * before WordPress loads, so the site's stored values are never changed.
 *
 * Everything the request prints, from before the `wp` action onwards, is the
 * page, and it is reported from a shutdown function, so a request that ends
 * early (a redirect, an AJAX endpoint that exits) is reported as it would be
 * sent. Each Elementor document render is recorded with the actions running
 * at the time.
 */

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$calls = ['wp_head' => 0, 'wp_body_open' => 0, 'wp_footer' => 0];
$renders = [];
$redirect = null;

$theme = static fn() => $input['theme'];
$third_party = static function (string $hook, string $marker) use (&$calls): callable {
    return static function () use ($hook, $marker, &$calls): void {
        $calls[$hook]++;
        echo $marker;
    };
};

// A third-party plugin's callback on each shell hook, registered before
// WordPress loads, as a plugin's would be.
$GLOBALS['wp_filter'] = [
    'pre_option_stylesheet' => [10 => [['function' => $theme, 'accepted_args' => 1]]],
    'pre_option_template' => [10 => [['function' => $theme, 'accepted_args' => 1]]],
    'wp_head' => [999 => [['function' => $third_party('wp_head', '<meta name="elemacy-third-party-head">'), 'accepted_args' => 1]]],
    'wp_body_open' => [999 => [['function' => $third_party('wp_body_open', '<!-- elemacy-third-party-body-open -->'), 'accepted_args' => 1]]],
    'wp_footer' => [999 => [['function' => $third_party('wp_footer', '<!-- elemacy-third-party-footer -->'), 'accepted_args' => 1]]],
    'elemacy/filter/theme_builder/suppress_native_chrome' => [10 => [[
        'function' => static fn(bool $suppress, string $side): bool => in_array($side, $input['keep_native'], true) ? false : $suppress,
        'accepted_args' => 2,
    ]]],
];

// Serve a theme that has a dedicated adapter through the generic one, to
// compare the two paths on the same real theme. The hook is the adapter
// registration action (Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION),
// spelled out because Hooks is not loadable until WordPress has.
if (!empty($input['force_generic'])) {
    $GLOBALS['wp_filter']['elemacy/action/theme_builder/compatibility/register'] = [10 => [[
        'function' => static function ($compatibility) use ($input): void {
            $compatibility->register($input['theme'], \Elemacy\Modules\ThemeBuilder\Compatibility\Themes\ClassicThemeCompatibility::class);
        },
        'accepted_args' => 1,
    ]]];
}

foreach ((array) ($input['options'] ?? []) as $option => $value) {
    $GLOBALS['wp_filter']['pre_option_' . $option][10][] = ['function' => static fn() => $value, 'accepted_args' => 1];
}

// Every Elementor document render, and which actions were running at the time.
$GLOBALS['wp_filter']['elementor/frontend/builder_content_data'][10][] = ['function' => static function ($data, $post_id) use (&$renders) {
    $renders[] = ['id' => (int) $post_id, 'during' => array_values(array_diff($GLOBALS['wp_current_filter'], ['elementor/frontend/builder_content_data']))];

    return $data;
}, 'accepted_args' => 2];

$GLOBALS['wp_filter']['wp_redirect'][PHP_INT_MAX][] = ['function' => static function ($location) use (&$redirect) {
    $redirect = $location;

    return $location;
}, 'accepted_args' => 1];

// An endpoint that answers on template_redirect and exits, as WooCommerce's
// `?wc-ajax=` does (WC_AJAX::do_wc_ajax at priority 0).
if (isset($input['exit_on_template_redirect'])) {
    $GLOBALS['wp_filter']['template_redirect'][0][] = ['function' => static function () use ($input): void {
        echo $input['exit_on_template_redirect'];
        exit;
    }, 'accepted_args' => 1];
}

// A plugin-style callback on any hook, to prove an adapter does not remove it.
foreach ((array) ($input['third_party'] ?? []) as $hook => $marker) {
    $GLOBALS['wp_filter'][$hook][10][] = ['function' => static function () use ($marker): void {
        echo $marker;
    }, 'accepted_args' => 1];
}

// Registered before WordPress loads, so it runs before WordPress's own
// shutdown handler flushes every open buffer straight to the output.
$level = null;
register_shutdown_function(static function () use (&$level, &$calls, &$renders, &$redirect): void {
    if (null === $level) {
        return;
    }
    // Elementor's image-loading buffer opens on get_header and closes on
    // the_content, which a Theme Builder location never calls; a real request
    // flushes it at shutdown.
    while (ob_get_level() > $level + 1) {
        ob_end_flush();
    }
    $html = ob_get_level() > $level ? ob_get_clean() : '';

    // Chrome callbacks a dedicated adapter declares but the theme no longer
    // registers: a theme update renamed them, and its chrome would stay visible.
    $adapter = \Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager::instance()->active();
    $missing = $adapter instanceof \Elemacy\Modules\ThemeBuilder\Compatibility\HookedThemeCompatibility ? $adapter->missing_callbacks() : [];

    echo json_encode(['html' => $html, 'calls' => $calls, 'missing_callbacks' => $missing, 'renders' => $renders, 'redirect' => $redirect], JSON_THROW_ON_ERROR);
});

define('WP_USE_THEMES', true);
require dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/RequestSimulator.php';

elemacy_test_assert(get_stylesheet() === $input['theme'], 'Could not force the fixture theme ' . $input['theme'] . '.');

if ('admin' === $input['user']) {
    wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
    // is_admin_bar_showing() cached "no" while WordPress loaded as a guest.
    unset($GLOBALS['show_admin_bar']);
}

$level = ob_get_level();
ob_start();

(new \Elemacy\Tests\Integration\Fixtures\RequestSimulator())->visit($input['url']);

require ABSPATH . WPINC . '/template-loader.php';
