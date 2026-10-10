<?php

namespace Elemacy\Tests\Integration\Fixtures;

/**
 * Switches the active theme and renders a real page through WordPress's own
 * template-loader.php, so get_header(), the_content() and get_footer() all
 * execute exactly as they do on a real request.
 *
 * Two WordPress behaviors this harness works around, both artifacts of
 * driving several themes/renders from one long-lived PHPUnit process rather
 * than the one-theme-one-request lifetime WordPress assumes:
 *
 * - A theme's functions.php is normally included exactly once, for whatever
 *   theme is active when WordPress bootstraps. switch_theme() alone never
 *   (re-)includes a *different* theme's functions.php later — so this
 *   include()s the target theme's own functions.php explicitly on every
 *   switch_theme() call (require_once dedupes by resolved path, so
 *   switching back to an already-loaded theme is a safe no-op).
 * - CompatibilityManager::boot() (fired by WordPress's own 'wp' action, once
 *   per real request) registers a fresh set of get_header/get_footer/etc.
 *   closures on every visit(). Repeated visits in one process would
 *   otherwise accumulate them (WordPress never deduplicates anonymous
 *   closures), double-rendering chrome by the second render. Rather than
 *   indiscriminately clearing shared hooks like wp_body_open/wp_footer —
 *   which other modules (e.g. Popups) also register on once, at bootstrap,
 *   not per-request, and would then never fire again — this snapshots the
 *   exact WP_Hook state for the hooks compatibility adapters touch,
 *   immediately after each switch_theme() (i.e. with that theme's own
 *   functions.php hooks already in place, but no adapter closures yet), and
 *   restores that snapshot before every render() for the same theme.
 */
final class ThemeLifecycleHarness
{
    /**
     * Reset between switches/renders so a previous theme's adapter closures
     * never accumulate or leak into a later one: RequestSimulator::ADAPTER_HOOKS,
     * which lists every hook an adapter registers on. Deliberately excludes
     * wp_head and wp_footer: no adapter adds callbacks to either, and tests add
     * their own third-party markers there before switching theme, which a
     * reset to the switch-time snapshot would wipe. See RENDER_SCOPED_HOOKS.
     */
    private const TRACKED_HOOKS = RequestSimulator::ADAPTER_HOOKS;

    /**
     * Shell hooks the generic adapter empties with remove_all_actions() once
     * its own shell has run them — safe in a real request, which ends right
     * after, but in this process it would strip every later test's wp_head/
     * wp_footer (and so wp_enqueue_scripts). Restored after each render() to
     * exactly what was there before it, test-added callbacks included.
     */
    private const RENDER_SCOPED_HOOKS = ['wp_head', 'wp_footer'];

    private array $construction_baseline = [];
    private array $theme_baseline = [];
    /** @var string[] Elementor's per-request frontend body classes before any simulated render. */
    private array $body_classes_baseline = [];
    private string $previous_theme;
    private RequestSimulator $request;

    public function __construct()
    {
        $this->construction_baseline = $this->snapshot();
        $this->theme_baseline = $this->construction_baseline;
        $this->body_classes_baseline = $this->elementor_body_classes();
        $this->previous_theme = get_stylesheet();
        $this->request = new RequestSimulator();
    }

    public function switch_theme(string $stylesheet): void
    {
        // Undo whatever the previous theme's compatibility adapter
        // registered (get_header/get_footer/etc. closures) before adopting
        // a new theme_baseline — otherwise a prior theme's adapter hooks
        // (e.g. GlobalCompatibility's own 'get_header' force-loader) leak
        // into every later switch_theme()'s baseline and keep firing.
        $this->restore_snapshot($this->construction_baseline);

        switch_theme($stylesheet);
        elemacy_test_assert(get_stylesheet() === $stylesheet, "Could not switch to fixture theme '$stylesheet'.");

        $functions = get_theme_root() . '/' . $stylesheet . '/functions.php';
        if (is_file($functions)) {
            require_once $functions;
        }

        // WordPress core only ever adds 'block-templates' theme support once,
        // reactively, via its own after_setup_theme callback
        // (wp_enable_block_templates(), default-filters.php) — which fires
        // exactly once per real request, for whichever theme was active at
        // bootstrap. This harness's repeated mid-process switch_theme()
        // calls never re-trigger after_setup_theme, so a block theme
        // switched to here would otherwise never get that support declared,
        // making locate_block_template() bail out early and return no
        // template at all. Mirrors wp_enable_block_templates()'s own
        // condition and keeps it in sync on every switch, both ways.
        if (wp_is_block_theme() || wp_theme_has_theme_json()) {
            add_theme_support('block-templates');
        } else {
            remove_theme_support('block-templates');
        }

        // WP_Theme_JSON_Resolver caches resolved theme.json/global-styles data
        // statically for the lifetime of the process, keyed by whatever theme
        // was active when first resolved — normally safe, since a real
        // request's active theme never changes mid-request. This harness's
        // own repeated theme switches within one PHPUnit process would
        // otherwise leak a stale theme's resolved style handles/paths into a
        // later render (WordPress core provides this exact reset for its own
        // multi-theme test scenarios).
        if (class_exists(\WP_Theme_JSON_Resolver::class)) {
            \WP_Theme_JSON_Resolver::clean_cached_data();
        }

        $this->theme_baseline = $this->snapshot();
    }

    /** @return string The full rendered page output. */
    public function render(string $url): string
    {
        // wp-blog-header.php normally defines this before loading wp-load.php;
        // this harness's bootstrap loads wp-load.php directly, so
        // wp_using_themes() would otherwise report false and
        // template-loader.php would render nothing at all.
        if (!defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', true);
        }

        // $wp_styles/$wp_scripts accumulate registrations across each
        // simulated request in this same process (a real request only ever
        // renders once); reset them so a prior render's theme-registered
        // handles never leak into this one.
        global $wp_styles, $wp_scripts;
        $wp_styles = null;
        $wp_scripts = null;
        $this->restore_snapshot($this->theme_baseline);
        $this->set_elementor_body_classes($this->body_classes_baseline);

        $shell_hooks = $this->snapshot(self::RENDER_SCOPED_HOOKS);
        $this->request->visit($url);
        $baseline_level = ob_get_level();
        ob_start();
        try {
            require ABSPATH . 'wp-includes/template-loader.php';
        } finally {
            // Elementor's own image-loading-optimization module opens an
            // output buffer on 'get_header' and only closes it once
            // the_content() fires (its 'the_content' filter flushes it) —
            // a real WordPress request's standard post/page rendering
            // always calls the_content(), but this wrapper's Theme Builder
            // location content never does, so that buffer is left nested
            // inside ours. A real request's own PHP process would silently
            // auto-flush it at script end; ob_end_flush() folds it back
            // into ours the same way, in the same order, right here instead.
            while (ob_get_level() > $baseline_level + 1) {
                ob_end_flush();
            }
            $html = ob_get_clean();
            $this->restore_snapshot($shell_hooks);
        }

        return $html;
    }

    public function restore(): void
    {
        $this->request->restore();
        $this->restore_snapshot($this->construction_baseline);
        $this->set_elementor_body_classes($this->body_classes_baseline);
        switch_theme($this->previous_theme);
    }

    /**
     * @param string[]|null $hooks Defaults to TRACKED_HOOKS.
     * @return array<string, \WP_Hook|null>
     */
    private function snapshot(?array $hooks = null): array
    {
        global $wp_filter;
        $snapshot = [];
        foreach ($hooks ?? self::TRACKED_HOOKS as $hook) {
            $snapshot[$hook] = isset($wp_filter[$hook]) ? clone $wp_filter[$hook] : null;
        }

        return $snapshot;
    }

    /** @param array<string, \WP_Hook|null> $snapshot */
    private function restore_snapshot(array $snapshot): void
    {
        global $wp_filter;
        foreach (array_keys($snapshot) as $hook) {
            if (null === $snapshot[$hook]) {
                unset($wp_filter[$hook]);
            } else {
                $wp_filter[$hook] = clone $snapshot[$hook];
            }
        }
    }

    /**
     * Elementor's frontend keeps its body classes for the life of the PHP
     * process; canvas.php adds `elementor-template-canvas` to them. A real
     * request is one process, but this harness simulates many, so a Canvas
     * render would otherwise make every later render look like Canvas.
     *
     * @return string[]
     */
    private function elementor_body_classes(): array
    {
        $property = new \ReflectionProperty(\Elementor\Frontend::class, 'body_classes');
        $property->setAccessible(true);

        return (array) $property->getValue(\Elementor\Plugin::$instance->frontend);
    }

    /** @param string[] $classes */
    private function set_elementor_body_classes(array $classes): void
    {
        $property = new \ReflectionProperty(\Elementor\Frontend::class, 'body_classes');
        $property->setAccessible(true);
        $property->setValue(\Elementor\Plugin::$instance->frontend, $classes);
    }
}
