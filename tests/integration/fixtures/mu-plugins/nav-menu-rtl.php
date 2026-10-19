<?php

/**
 * Renders a request right-to-left when it asks for `?elemacy_fixture_rtl=1`, so
 * the Nav Menu browser runners can check RTL pages without a language pack.
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

add_action('init', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only test switch.
    if (isset($_GET['elemacy_fixture_rtl']) && isset($GLOBALS['wp_locale'])) {
        $GLOBALS['wp_locale']->text_direction = 'rtl';
    }
}, 0);
