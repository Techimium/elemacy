<?php

namespace Elemacy\Core\Cache;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;

/**
 * Best-effort purge of the object cache plus whichever supported page-cache
 * plugin, if any, is active — called when a change to feature availability
 * means previously suspended or previously visible content must stop being
 * served from a stale cached page on the very next request.
 */
final class PageCachePurger
{
    public static function purge_all(): void
    {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // WP Rocket.
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        // W3 Total Cache.
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // WP Super Cache.
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // LiteSpeed Cache.
        if (class_exists('LiteSpeed\Purge')) {
            do_action('litespeed_purge_all');
        }

        // SiteGround Optimizer.
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
        }

        // WP Engine.
        if (method_exists('WpeCommon', 'purge_memcached')) {
            \WpeCommon::purge_memcached();
        }

        if (method_exists('WpeCommon', 'purge_varnish_cache')) {
            \WpeCommon::purge_varnish_cache();
        }

        do_action(Hooks::CACHE_PURGE_ACTION);
    }
}
