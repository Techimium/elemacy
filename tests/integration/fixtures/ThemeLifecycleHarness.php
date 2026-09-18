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
     * Hooks a compatibility adapter itself registers on — reset between
     * switches/renders so a previous theme's adapter closures never
     * accumulate or leak into a later one. Deliberately excludes wp_head
     * and wp_footer: no adapter in this rewrite hooks either directly (a
     * theme's own <?php wp_footer(); ?> call — where third-party plugins,
     * and this harness's own test-added marker callbacks, hook — is a
     * different thing entirely and must never be reset here).
     */
    private const TRACKED_HOOKS = [
        'get_header', 'get_footer', 'wp_body_open', 'wp_enqueue_scripts',
        'astra_header', 'astra_footer', 'template_redirect', 'hello_elementor_header_footer',
        // BlockThemeCompatibility::register() attaches here on every real
        // 'wp' action firing (CompatibilityManager::boot()), i.e. every
        // render() this harness simulates — never removed on its own since
        // a real request's process only ever runs boot() once. Without
        // tracking it, a second render() using the same block theme (in
        // this test or a later one, same long-lived process) accumulates a
        // second closure on this hook; the filter chain's later closure
        // then re-hits ThemeBuilderManager's own once-per-request render
        // guard and overwrites the first closure's correct output with its
        // own empty return.
        'render_block_core/template-part',
    ];

    private array $construction_baseline = [];
    private array $theme_baseline = [];
    private string $previous_theme;
    private RequestSimulator $request;

    public function __construct()
    {
        $this->construction_baseline = $this->snapshot();
        $this->theme_baseline = $this->construction_baseline;
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
        $this->reset_theme_builder_manager();

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
        }

        return $html;
    }

    public function restore(): void
    {
        $this->restore_snapshot($this->construction_baseline);
        $this->request->restore();
        switch_theme($this->previous_theme);
    }

    /** @return array<string, \WP_Hook|null> */
    private function snapshot(): array
    {
        global $wp_filter;
        $snapshot = [];
        foreach (self::TRACKED_HOOKS as $hook) {
            $snapshot[$hook] = isset($wp_filter[$hook]) ? clone $wp_filter[$hook] : null;
        }

        return $snapshot;
    }

    /** @param array<string, \WP_Hook|null> $snapshot */
    private function restore_snapshot(array $snapshot): void
    {
        global $wp_filter;
        foreach (self::TRACKED_HOOKS as $hook) {
            if (null === $snapshot[$hook]) {
                unset($wp_filter[$hook]);
            } else {
                $wp_filter[$hook] = clone $snapshot[$hook];
            }
        }
    }

    /**
     * ThemeBuilderManager is a singleton whose render_header()/render_footer()
     * track "already rendered this request" in an instance property
     * (design.md D2's exactly-once guard) — correct for a real request,
     * where the whole process (and so the singleton) is thrown away
     * afterward, but not reset between this harness's own simulated
     * requests within one process, which would otherwise make every render
     * after the first silently skip header/footer entirely.
     */
    private function reset_theme_builder_manager(): void
    {
        if (!class_exists(\Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager::class)) {
            return;
        }
        $property = new \ReflectionProperty(\Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager::class, 'rendered_locations');
        $property->setAccessible(true);
        $property->setValue(\Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager::instance(), []);
    }
}
