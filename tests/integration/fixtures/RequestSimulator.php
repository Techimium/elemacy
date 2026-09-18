<?php

namespace Elemacy\Tests\Integration\Fixtures;

use Elemacy\TemplateLibrary\TemplateResolver;
use ReflectionProperty;
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
        // which (via CompatibilityManager::boot()) already resolves
        // TemplateResolver types and checks did_action('wp_body_open') for
        // this simulated request — resetting only afterward would still
        // hand that first, request-defining resolution a stale value from
        // whatever visit() ran before this one.
        $this->resetPerRequestMemoization();

        $GLOBALS['wp']->main($parts['query'] ?? '');
    }

    /**
     * TemplateResolver::resolve() and PopupManager's matched-popup cache
     * memoize for the lifetime of a real WordPress request, which normally
     * ends the PHP process. Simulating several requests in one long-lived
     * PHPUnit process needs an explicit reset so a later visit() is not
     * served a stale resolution from an earlier one.
     */
    private function resetPerRequestMemoization(): void
    {
        $resolved = new ReflectionProperty(TemplateResolver::class, 'resolved');
        $resolved->setAccessible(true);
        $resolved->setValue(null, []);

        if (class_exists(\Elemacy\Modules\Popups\Services\PopupManager::class)) {
            $matched = new ReflectionProperty(\Elemacy\Modules\Popups\Services\PopupManager::class, 'matched');
            $matched->setAccessible(true);
            $matched->setValue(\Elemacy\Modules\Popups\Services\PopupManager::instance(), null);
        }

        // did_action() counts are global and, on a real request, never reset
        // (each request is its own process) — code like GlobalCompatibility's
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
        $this->visited = false;
        wp_reset_postdata();
    }
}
