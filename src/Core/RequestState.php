<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

/**
 * The boundary of one page render for every cache the rendering pipeline
 * keeps for a request: prepared fragments, style-emission markers, resolved
 * templates, render-once guards. See docs/ARCHITECTURE.md § Caches and schema.
 *
 * A normal web request renders one page and ends, so these caches never
 * outlive it. A long-running process (a WP-CLI command, a batch job, a test
 * harness) renders several pages in one process; each cache registers its
 * reset here, and the flush runs as the first `wp` callback of every page
 * WP::main() serves. Code that renders outside WP::main() calls flush()
 * itself before each page.
 */
final class RequestState
{
    /** @var array<string, callable(): void> */
    private static array $resets = [];

    /**
     * Registers $reset under $name; registering a name again replaces it.
     */
    public static function register(string $name, callable $reset): void
    {
        self::$resets[$name] = $reset;
    }

    /** Clears every registered cache. */
    public static function flush(): void
    {
        foreach (self::$resets as $reset) {
            $reset();
        }
    }

    /**
     * First on `wp`: after the main query is set up and before anything
     * resolves a template (theme adapters boot on `wp`, the location template
     * resolves at `template_include`) or prepares one (`wp_enqueue_scripts`).
     */
    public static function register_hooks(): void
    {
        add_action('wp', [self::class, 'flush'], PHP_INT_MIN, 0);
    }
}
