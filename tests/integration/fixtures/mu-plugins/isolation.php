<?php

/** Only copied into a marked disposable environment, never installed on development. */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

// Tests do not send email or contact plugin update/telemetry endpoints.
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static function ($preempt, $arguments, $url) {
    if (wp_parse_url($url, PHP_URL_HOST) !== '127.0.0.1') {
        return new WP_Error('integration_external_http', 'External HTTP disabled in the disposable test site.');
    }
    return $preempt;
}, 10, 3);
