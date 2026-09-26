<?php

namespace Elemacy\Tests\Integration\Fixtures;

use WP;
use WP_Query;

/**
 * Simulates visiting a URL against the real WordPress main query — the same
 * technique WordPress core's own test suite uses (`go_to()`). Needed because
 * the integration bootstrap loads wp-load.php directly and never runs
 * $wp->main(), so is_singular()/is_archive()/$wp_query stay empty otherwise.
 */
final class RequestSimulator
{
    /**
     * Hooks a theme compatibility adapter registers on. visit() fires 'wp',
     * where CompatibilityManager::boot() registers a fresh adapter every
     * time; a real request's process ends after one, but here the closures
     * would accumulate across every later test — and the generic adapter's
     * get_header closure prints a whole document shell. restore() puts these
     * back exactly as they were before the first visit().
     *
     * 'render_block_core/template-part' is where BlockThemeCompatibility
     * attaches. Left untracked, a second render with the same block theme
     * accumulates a second closure; the later one re-hits
     * ThemeBuilderManager's once-per-request render guard and overwrites the
     * first closure's correct output with its own empty return.
     */
    public const ADAPTER_HOOKS = [
        'get_header', 'get_footer', 'wp_body_open', 'wp_enqueue_scripts',
        'astra_header', 'astra_footer', 'template_redirect', 'hello_elementor_header_footer',
        'render_block_core/template-part',
    ];

    /** @var array<string, \WP_Hook|null> */
    private array $previous_hooks = [];
    private $previous_query;
    private $previous_the_query;
    private $previous_wp;
    private bool $visited = false;

    public function visit(string $url): void
    {
        global $wp_query, $wp_the_query, $wp;

        // Only the *first* visit() on this instance may capture "previous"
        // state: a test simulating two requests in sequence (an earlier
        // render, then an unrelated later one) calls visit() twice on one
        // instance, and a single restore() must still revert all the way
        // back to the state before either — not just undo the second call
        // and leave the first visited (possibly since-deleted) document's
        // query installed globally for every later test in this process.
        if (!$this->visited) {
            $this->previous_query = $wp_query;
            $this->previous_the_query = $wp_the_query;
            $this->previous_wp = $wp;
            $this->previous_hooks = [];
            foreach (self::ADAPTER_HOOKS as $hook) {
                $this->previous_hooks[$hook] = isset($GLOBALS['wp_filter'][$hook]) ? clone $GLOBALS['wp_filter'][$hook] : null;
            }
            $this->visited = true;
        }

        $_GET = $_POST = [];
        foreach (['query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow', 'current_screen'] as $global) {
            unset($GLOBALS[$global]);
        }

        $parts = wp_parse_url($url);
        $_SERVER['REQUEST_URI'] = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        if (isset($parts['query'])) {
            parse_str($parts['query'], $_GET);
        }

        $GLOBALS['wp_the_query'] = new WP_Query();
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];

        $public_query_vars = $wp->public_query_vars;
        $private_query_vars = $wp->private_query_vars;
        $GLOBALS['wp'] = new WP();
        $GLOBALS['wp']->public_query_vars = $public_query_vars;
        $GLOBALS['wp']->private_query_vars = $private_query_vars;

        // Before, not after, main(): main() itself fires the 'wp' action,
        // where CompatibilityManager::boot() already checks
        // did_action('wp_body_open') for this simulated request.
        $this->resetPerRequestMemoization();

        $GLOBALS['wp']->main($parts['query'] ?? '');
    }

    /**
     * Resets what a real request starts from but this process carries over.
     * The plugin's own request-scoped caches (resolved templates, matched
     * popups, prepared fragments, render-once guards) are deliberately *not*
     * reset here: Core\RequestState flushes them as the first `wp` callback,
     * which main() fires, exactly as it would between two pages of a
     * long-running process.
     */
    private function resetPerRequestMemoization(): void
    {
        // did_action() counts are global and, on a real request, never reset
        // (each request is its own process) — the Theme Builder shell views'
        // `did_action('wp_body_open') || wp_body_open()` fallback relies on
        // that count starting at zero. Only clearing per-request document
        // lifecycle hooks here — never e.g. 'init'/'plugins_loaded', whose
        // "has bootstrap completed" semantics must stay true for the rest of
        // this process.
        foreach (['wp', 'template_redirect', 'get_header', 'wp_head', 'wp_body_open', 'get_footer', 'wp_footer'] as $hook) {
            unset($GLOBALS['wp_actions'][$hook]);
        }
    }

    public function restore(): void
    {
        if (!$this->visited) {
            return;
        }
        $GLOBALS['wp_query'] = $this->previous_query;
        $GLOBALS['wp_the_query'] = $this->previous_the_query;
        $GLOBALS['wp'] = $this->previous_wp;
        foreach ($this->previous_hooks as $hook => $previous) {
            if (null === $previous) {
                unset($GLOBALS['wp_filter'][$hook]);
            } else {
                $GLOBALS['wp_filter'][$hook] = clone $previous;
            }
        }
        $this->visited = false;
        wp_reset_postdata();
    }
}
