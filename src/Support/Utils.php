<?php

namespace Elemacy\Support;

class Utils
{
    public static function get_plugin_version()
    {
        return ELEMACY_VERSION;
    }

    public static function is_plugin_page()
    {
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return is_admin() && isset($page) && $page === 'elemacy';
    }
}
