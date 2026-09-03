<?php

/**
 * Executes the pure-logic unit tests for release-critical server code:
 * condition evaluation semantics, the migration runner, and the request
 * sanitizer. Run with:  php tests/php/run.php
 */

require __DIR__ . '/bootstrap.php';

use Elemacy\Conditions\BaseCondition;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\ConditionEvaluator;
use Elemacy\Conditions\MockCondition;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Hooks;
use Elemacy\Core\Migrator;
use Elemacy\Core\Rendering\AtomicStylesRenderer;
use Elemacy\Core\Rendering\ClassicCssRenderer;
use Elemacy\Core\Rendering\TemplateAssetsRegistrar;
use Elemacy\Core\Rendering\TemplateRenderer;
use Elemacy\Core\Sanitizer;
use Elemacy\Modules\Popups\Services\EditorPreview;
use Elemacy\Modules\Popups\Support\DisplayDefaults;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elemacy\Modules\ThemeBuilder\Compatibility\Themes\GlobalCompatibility;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elemacy\Modules\Widgets\Services\LoopItemStyles;

/* ── Test doubles ───────────────────────────────────────────────── */

/**
 * Condition whose match result is fixed at construction.
 */
final class FixedCondition extends BaseCondition
{
    private string $name;
    private bool $matches;

    public function __construct(string $name, bool $matches)
    {
        $this->name    = $name;
        $this->matches = $matches;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return $this->name;
    }

    public function check(ConditionRuleDTO $rule): bool
    {
        return $this->matches;
    }
}

final class FixedMockCondition extends MockCondition
{
    public function get_name(): string
    {
        return 'mock/locked';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return 'Locked';
    }
}

/**
 * Migrator with an injectable, deliberately unsorted migration map that logs
 * each execution.
 */
final class RecordingMigrator extends Migrator
{
    /** @var array<int, array{string, string, string}> */
    public array $log = [];

    protected function migrations(): array
    {
        // Deliberately out of order: run() must sort by version itself.
        return [
            '1.2.0'  => function ($from, $to) {
                $this->log[] = ['1.2.0', $from, $to];
            },
            '1.0.5'  => function ($from, $to) {
                $this->log[] = ['1.0.5', $from, $to];
            },
            '1.10.0' => function ($from, $to) {
                $this->log[] = ['1.10.0', $from, $to];
            },
        ];
    }
}

final class TestableEditorPreview extends EditorPreview
{
    public function get_empty_document_state_css(): string
    {
        return $this->empty_document_state_css();
    }

    public function get_frame_css(string $wrapper): string
    {
        return $this->frame_css($wrapper);
    }

    public function get_topbar_css(string $wrapper): string
    {
        return $this->topbar_css($wrapper);
    }

    public function get_overlay_css(): string
    {
        return $this->overlay_css();
    }
}

/* ── Popup editor empty state ───────────────────────────────────── */

check('popup creation prompt is hidden only after content exists', static function () {
    $css = (new TestableEditorPreview())->get_empty_document_state_css();

    return false !== strpos(
        $css,
        '[data-elementor-type="elemacy_popup"] .elementor-section-wrap:not(:empty) + #elementor-add-new-section'
    );
});

check('popup preview wrapper adds no visual decoration', static function () {
    $preview = new TestableEditorPreview();
    $css     = $preview->get_frame_css('.elementor-123')
        . $preview->get_topbar_css('.elementor-123');

    return false === strpos($css, 'background')
        && false === strpos($css, 'border-radius')
        && false === strpos($css, 'box-shadow');
});

check('popup frontend wrapper adds no visual decoration', static function () {
    $css = file_get_contents(
        dirname(__DIR__, 2) . '/src/Modules/Popups/assets/styles/popups.css'
    );

    $box_rules = [];
    preg_match_all('/[^{}]*\.elemacy-popup__box[^{}]*\{([^}]*)\}/', $css, $box_rules);
    $box_css = implode('', $box_rules[1]);

    return false === strpos($box_css, 'background')
        && false === strpos($box_css, 'border-radius')
        && false === strpos($box_css, 'box-shadow');
});

check('popup overlay blur defaults preserve existing appearance', static function () {
    foreach ([PopupTypes::POPUP, PopupTypes::TOPBAR, PopupTypes::BANNER, PopupTypes::FLOATING] as $type) {
        $defaults = DisplayDefaults::for_type($type);

        if (DisplayDefaults::DEFAULT_OVERLAY_BLUR !== $defaults['overlay']['blur']) {
            return false;
        }
    }

    return true;
});

check('popup preview supports prefixed and standard backdrop blur', static function () {
    $css = (new TestableEditorPreview())->get_overlay_css();

    return false !== strpos($css, '-webkit-backdrop-filter:blur(var(--elemacy-ov-blur,0px))')
        && false !== strpos($css, 'backdrop-filter:blur(var(--elemacy-ov-blur,0px))');
});

/* ── Action hook stub semantics ─────────────────────────────────── */

check('do_action stub fires callbacks in ascending priority order', static function () {
    $order = [];
    add_action('test/priority-order', static function () use (&$order) {
        $order[] = 'twenty';
    }, 20);
    add_action('test/priority-order', static function () use (&$order) {
        $order[] = 'nineteen';
    }, 19);

    do_action('test/priority-order');

    return $order === ['nineteen', 'twenty'];
});

/* ── Core\Rendering\TemplateRenderer (Elementor not yet loaded) ─── */

check('renderer returns empty string when Elementor is not loaded', static function () {
    return (new TemplateRenderer())->render(101) === '';
});

/* ── Elementor stand-ins required from here on ──────────────────── */

require __DIR__ . '/elementor-stubs.php';

final class FakeElementorFrontend
{
    /** @var string[] */
    public array $calls = [];

    public function enqueue_styles(): void
    {
        $this->calls[] = 'enqueue_styles';
    }

    public function enqueue_scripts(): void
    {
        $this->calls[] = 'enqueue_scripts';
    }

    public function get_builder_content_for_display($id)
    {
        return "<fake-content id=\"{$id}\">";
    }
}

check('renderer delegates to Elementor when loaded', static function () {
    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->frontend = new FakeElementorFrontend();

    return (new TemplateRenderer())->render(303) === '<fake-content id="303">';
});

/* ── Core\Rendering\TemplateAssetsRegistrar ─────────────────────── */

/**
 * The stub harness accumulates hook registrations for the whole run (see
 * bootstrap.php), so tests that self-register on the real 'wp_enqueue_scripts'
 * / collect-action hooks must clear them first to avoid picking up listeners
 * left behind by earlier checks.
 */
function reset_template_assets_hooks(): void
{
    unset(
        $GLOBALS['__wp_actions']['wp_enqueue_scripts'],
        $GLOBALS['__wp_actions'][Hooks::TEMPLATE_ASSETS_COLLECT_ACTION],
        $GLOBALS['__wp_actions']['elementor/post/render'],
        $GLOBALS['__wp_actions']['elementor/document/related_posts']
    );
}

check('registrar collect() passes itself to the collect action, and register() fires elementor/post/render exactly once per id', static function () {
    reset_template_assets_hooks();

    $registered = [];
    add_action('elementor/post/render', static function ($post_id) use (&$registered) {
        $registered[] = $post_id;
    });

    $received = null;
    add_action(Hooks::TEMPLATE_ASSETS_COLLECT_ACTION, static function ($registrar) use (&$received) {
        $received = $registrar;
        $registrar->register(101);
        $registrar->register(101);
    });

    $registrar = new TemplateAssetsRegistrar();
    $registrar->register_hooks();

    do_action('wp_enqueue_scripts');

    return $received === $registrar && $registered === [101];
});

check('registrar registers before a simulated priority-20 style pass runs', static function () {
    reset_template_assets_hooks();

    $registered = [];
    add_action('elementor/post/render', static function ($post_id) use (&$registered) {
        $registered[] = $post_id;
    });
    add_action(Hooks::TEMPLATE_ASSETS_COLLECT_ACTION, static function ($registrar) {
        $registrar->register(404);
    });

    $seen_at_priority_20 = null;
    add_action('wp_enqueue_scripts', static function () use (&$registered, &$seen_at_priority_20) {
        $seen_at_priority_20 = $registered;
    }, 20);

    (new TemplateAssetsRegistrar())->register_hooks();

    do_action('wp_enqueue_scripts');

    return $seen_at_priority_20 === [404];
});

check('registrar registers nothing when nothing is collected', static function () {
    reset_template_assets_hooks();

    $fired = false;
    add_action('elementor/post/render', static function () use (&$fired) {
        $fired = true;
    });

    (new TemplateAssetsRegistrar())->collect();

    return false === $fired;
});

check('registrar collect() force-enqueues Elementor base frontend assets only when something was registered', static function () {
    reset_template_assets_hooks();

    \Elementor\Plugin::$instance = new \Elementor\Plugin();
    \Elementor\Plugin::$instance->frontend = new FakeElementorFrontend();

    $registrar = new TemplateAssetsRegistrar();
    $registrar->collect();
    $calls_with_nothing_registered = \Elementor\Plugin::$instance->frontend->calls;

    $registrar->register(505);
    $registrar->collect();
    $calls_after_register = \Elementor\Plugin::$instance->frontend->calls;

    return $calls_with_nothing_registered === []
        && $calls_after_register === ['enqueue_styles', 'enqueue_scripts'];
});

check('registrar wires related_posts to merge registered ids only for the currently queried post', static function () {
    reset_template_assets_hooks();

    $registrar = new TemplateAssetsRegistrar();
    $registrar->register_hooks();
    $registrar->register(606);

    $GLOBALS['__current_post_id'] = 55;
    $for_queried_post = apply_filters('elementor/document/related_posts', [9], 55);
    $for_other_post   = apply_filters('elementor/document/related_posts', [9], 999);
    $GLOBALS['__current_post_id'] = 0;

    return $for_queried_post === [9, 606] && $for_other_post === [9];
});

/* ── Core\Rendering\AtomicStylesRenderer (Elementor atomic classes
     not loaded — elementor-stubs.php only defines \Elementor\Plugin and
     Dynamic_Prop_Type) ──────────────────────────────────────────── */

check('AtomicStylesRenderer::render_base() degrades to empty string when Elementor atomic-widgets internals are unavailable', static function () {
    return (new AtomicStylesRenderer())->render_base(101) === '';
});

check('AtomicStylesRenderer::render_dynamic() degrades to empty string when Elementor atomic-widgets internals are unavailable', static function () {
    return (new AtomicStylesRenderer())->render_dynamic(101, '.scope') === '';
});

check('AtomicStylesRenderer::has_dynamic_styles() degrades to false when Elementor atomic-widgets internals are unavailable', static function () {
    return (new AtomicStylesRenderer())->has_dynamic_styles(101) === false;
});

/**
 * Elementor's real element traversal (Utils::traverse_post_elements,
 * Atomic_Elements_Utils) isn't stubbed — collect_all_element_styles() is
 * the one method that needs it, so it's overridden here with fixture data,
 * isolating the thing this change actually fixes: given each element's
 * whole style definition, does collect_styles()'s dynamic/static split
 * route it to the right pass. Dynamic_Prop_Type::is_dynamic_prop_value()
 * (the only other real-class dependency in the path) is faithfully stubbed
 * in elementor-stubs.php, so this exercises the real filtering logic.
 */
final class FixtureAtomicStylesRenderer extends AtomicStylesRenderer
{
    /** @var array<int, array> */
    public array $fixture_element_styles = [];

    protected function collect_all_element_styles(int $post_id): array
    {
        return $this->fixture_element_styles;
    }

    public function collect_styles_for_test(int $post_id, bool $want_dynamic): array
    {
        return $this->collect_styles($post_id, $want_dynamic);
    }
}

check('AtomicStylesRenderer routes a style definition containing a dynamic value to the dynamic pass only', static function () {
    $dynamic_style = [
        'e-dyn1' => [
            'id' => 'e-dyn1',
            'variants' => [
                [
                    'props' => [
                        'background' => ['$$type' => 'dynamic', 'value' => ['name' => 'featured-image']],
                    ],
                ],
            ],
        ],
    ];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$dynamic_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $dynamic_result === $dynamic_style && $base_result === [];
});

check('AtomicStylesRenderer routes a purely static style definition to the base pass only', static function () {
    $static_style = [
        'e-static1' => [
            'id' => 'e-static1',
            'variants' => [
                [
                    'props' => [
                        'border-radius' => ['$$type' => 'size', 'value' => ['size' => 11, 'unit' => 'px']],
                    ],
                ],
            ],
        ],
    ];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$static_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $base_result === $static_style && $dynamic_result === [];
});

check('AtomicStylesRenderer keeps a mixed dynamic+static style definition whole, never split, in the dynamic pass', static function () {
    // Whole-definition exclusion (design.md's Decisions): a style with both
    // a dynamic prop and a static sibling prop moves entirely to the
    // dynamic pass, rather than being partially split between passes.
    $mixed_style = [
        'e-mixed1' => [
            'id' => 'e-mixed1',
            'variants' => [
                [
                    'props' => [
                        'border-radius' => ['$$type' => 'size', 'value' => ['size' => 11, 'unit' => 'px']],
                        'background' => ['$$type' => 'dynamic', 'value' => ['name' => 'featured-image']],
                    ],
                ],
            ],
        ],
    ];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$mixed_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $dynamic_result === $mixed_style && $base_result === [];
});

check('AtomicStylesRenderer merges multiple elements\' styles, each routed independently', static function () {
    $dynamic_style = ['e-dyn2' => ['id' => 'e-dyn2', 'variants' => [['props' => ['background' => ['$$type' => 'dynamic', 'value' => []]]]]]];
    $static_style = ['e-static2' => ['id' => 'e-static2', 'variants' => [['props' => ['color' => ['$$type' => 'color', 'value' => '#fff']]]]]];

    $renderer = new FixtureAtomicStylesRenderer();
    $renderer->fixture_element_styles = [$dynamic_style, $static_style];

    $dynamic_result = $renderer->collect_styles_for_test(1, true);
    $base_result = $renderer->collect_styles_for_test(1, false);

    return $dynamic_result === $dynamic_style && $base_result === $static_style;
});

/* ── Core\Rendering\ClassicCssRenderer (Elementor classic CSS/dynamic-tag
     classes not loaded) ──────────────────────────────────────────── */

check('ClassicCssRenderer::render_base() degrades to empty string when Post_CSS is unavailable', static function () {
    return (new ClassicCssRenderer())->render_base(101) === '';
});

check('ClassicCssRenderer::render_dynamic() degrades to empty string when Dynamic_CSS is unavailable', static function () {
    return (new ClassicCssRenderer())->render_dynamic(101, 202, '.scope') === '';
});

check('ClassicCssRenderer::has_dynamic_settings() degrades to false when Elementor internals are unavailable', static function () {
    return (new ClassicCssRenderer())->has_dynamic_settings(101) === false;
});

check('ClassicCssRenderer::suppress_automatic_dynamic_css() makes elementor/css-file/dynamic/should_enqueue return false only for the registered post id', static function () {
    (new ClassicCssRenderer())->suppress_automatic_dynamic_css(64);

    $suppressed = apply_filters('elementor/css-file/dynamic/should_enqueue', true, 64);
    $unrelated = apply_filters('elementor/css-file/dynamic/should_enqueue', true, 999);

    return false === $suppressed && true === $unrelated;
});

check('ClassicCssRenderer::suppress_automatic_dynamic_css() hooks the filter only once, even across multiple post ids', static function () {
    // add_filter() shares add_action()'s __wp_actions storage in this stub
    // harness — [hook][priority] is a list of registered callbacks.
    $hook = 'elementor/css-file/dynamic/should_enqueue';
    $count = static fn () => count($GLOBALS['__wp_actions'][$hook][10] ?? []);

    $renderer = new ClassicCssRenderer();
    $before = $count();

    $renderer->suppress_automatic_dynamic_css(111);
    $renderer->suppress_automatic_dynamic_css(111);
    $renderer->suppress_automatic_dynamic_css(222);

    // Whatever the starting count (0 if this is the first filter test to run,
    // 1 if a prior check already hooked it), three more suppress_...() calls
    // — including a repeat of the same id — must add at most one callback.
    $added = $count() - $before;

    $both_suppressed = false === apply_filters($hook, true, 111)
        && false === apply_filters($hook, true, 222);

    return $added <= 1 && $both_suppressed;
});

/* ── Modules\Widgets\Services\LoopItemStyles ──────────────────────── */

/**
 * Stand-ins that skip Elementor entirely and let each test control exactly
 * what the two renderers return, so LoopItemStyles's own orchestration
 * (concatenation, per-request idempotency, the dynamic-content cache, and
 * the no-op-when-nothing-to-print guard) is what's under test here — the
 * real CSS these renderers produce is covered by ClassicCssRenderer's and
 * AtomicStylesRenderer's own tests (and was verified live against the real
 * Elementor installation; see design.md).
 */
final class FakeClassicCssRenderer extends ClassicCssRenderer
{
    public string $base_css = '';
    public string $dynamic_css = '';
    public bool $has_dynamic = false;
    /** @var array<int, array{0:int,1:int,2:string}> */
    public array $dynamic_calls = [];
    public int $has_dynamic_calls = 0;
    /** @var array<int, int> */
    public array $suppress_calls = [];

    public function render_base(int $post_id): string
    {
        return $this->base_css;
    }

    public function render_dynamic(int $template_id, int $item_post_id, string $selector_prefix): string
    {
        $this->dynamic_calls[] = [$template_id, $item_post_id, $selector_prefix];

        return $this->dynamic_css;
    }

    public function has_dynamic_settings(int $post_id): bool
    {
        $this->has_dynamic_calls++;

        return $this->has_dynamic;
    }

    public function suppress_automatic_dynamic_css(int $post_id): void
    {
        // Spy only — never touches the real, shared suppression registry,
        // keeping these tests isolated from ClassicCssRenderer's own.
        $this->suppress_calls[] = $post_id;
    }
}

final class FakeAtomicStylesRenderer extends AtomicStylesRenderer
{
    public string $base_css = '';
    public string $dynamic_css = '';
    public bool $has_dynamic = false;
    /** @var array<int, int> */
    public array $render_base_calls = [];
    /** @var array<int, array{0:int,1:string}> */
    public array $render_dynamic_calls = [];
    public int $has_dynamic_calls = 0;

    public function render_base(int $post_id): string
    {
        $this->render_base_calls[] = $post_id;

        return $this->base_css;
    }

    public function render_dynamic(int $post_id, string $selector_prefix): string
    {
        $this->render_dynamic_calls[] = [$post_id, $selector_prefix];

        return $this->dynamic_css;
    }

    public function has_dynamic_styles(int $post_id): bool
    {
        $this->has_dynamic_calls++;

        return $this->has_dynamic;
    }
}

check('LoopItemStyles::print_base_css() echoes classic and atomic base CSS wrapped in one style tag', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->base_css = '.a{color:red;}';
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->base_css = '.b{color:blue;}';

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_base_css(9001);
    $output = ob_get_clean();

    return $output === '<style id="elemacy-loop-base-9001">.a{color:red;}.b{color:blue;}</style>';
});

check('LoopItemStyles::print_base_css() prints nothing when both renderers return empty CSS', static function () {
    ob_start();
    (new LoopItemStyles(new FakeClassicCssRenderer(), new FakeAtomicStylesRenderer()))->print_base_css(9002);
    $output = ob_get_clean();

    return $output === '';
});

check('LoopItemStyles::print_base_css() only prints once per template id per request', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->base_css = '.a{color:red;}';

    $styles = new LoopItemStyles($classic, new FakeAtomicStylesRenderer());

    ob_start();
    $styles->print_base_css(9003);
    $styles->print_base_css(9003);
    $output = ob_get_clean();

    return 1 === substr_count($output, '<style');
});

check('LoopItemStyles::print_item_css() is a no-op when the template has no dynamic content', static function () {
    $classic = new FakeClassicCssRenderer();
    $atomic = new FakeAtomicStylesRenderer();

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_item_css(9004, 55);
    $output = ob_get_clean();

    return '' === $output && [] === $classic->dynamic_calls && [] === $atomic->render_dynamic_calls;
});

check('LoopItemStyles::print_item_css() prints scoped CSS from both renderers when the template has dynamic content', static function () {
    $classic = new FakeClassicCssRenderer();
    $classic->has_dynamic = true;
    $classic->dynamic_css = '.c{color:green;}';
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->dynamic_css = '.d{color:yellow;}';

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_item_css(9005, 77);
    $output = ob_get_clean();

    return $output === '<style id="elemacy-loop-item-77">.c{color:green;}.d{color:yellow;}</style>'
        && $classic->dynamic_calls === [[9005, 77, '.elemacy-loop-item-77']]
        && $atomic->render_dynamic_calls === [[9005, '.elemacy-loop-item-77']];
});

check('LoopItemStyles::print_base_css() suppresses Elementor\'s automatic dynamic CSS for the template before anything else runs', static function () {
    $classic = new FakeClassicCssRenderer();
    $atomic = new FakeAtomicStylesRenderer();

    ob_start();
    (new LoopItemStyles($classic, $atomic))->print_base_css(9007);
    ob_get_clean();

    return $classic->suppress_calls === [9007];
});

check('LoopItemStyles caches has_dynamic_content() per template id across multiple items', static function () {
    // classic false, atomic true: forces the || in has_dynamic_content() to
    // actually evaluate both sides (a classic-true short-circuit would never
    // call the atomic side at all), so this also proves atomic content alone
    // is enough to turn dynamic printing on.
    $classic = new FakeClassicCssRenderer();
    $atomic = new FakeAtomicStylesRenderer();
    $atomic->has_dynamic = true;

    $styles = new LoopItemStyles($classic, $atomic);

    ob_start();
    $styles->print_item_css(9006, 1);
    $styles->print_item_css(9006, 2);
    $styles->print_item_css(9006, 3);
    ob_get_clean();

    return 1 === $classic->has_dynamic_calls && 1 === $atomic->has_dynamic_calls;
});

/* ── ThemeBuilder single/archive/search/404 content wrapper ──────── */

/**
 * Real theme-builder-wrapper.php, required fresh per test: it's a plain
 * top-level script (no declarations), so re-requiring it re-runs get_header(),
 * the wp_body_open guard, and get_footer() each time.
 */
$theme_builder_wrapper_path = dirname(__DIR__, 2) . '/src/Modules/ThemeBuilder/Views/theme-builder-wrapper.php';

function reset_wrapper_hooks(): void
{
    unset($GLOBALS['__wp_actions']['get_header'], $GLOBALS['__wp_actions']['get_footer']);
    $GLOBALS['__wp_actions_fired']['wp_body_open'] = 0;
}

check('wrapper fires wp_body_open when the theme header does not (deprecated theme-compat fallback)', static function () use ($theme_builder_wrapper_path) {
    reset_wrapper_hooks();
    // No listener on 'get_header' — mirrors WordPress's deprecated
    // wp-includes/theme-compat/header.php, which never calls wp_body_open().

    ob_start();
    require $theme_builder_wrapper_path;
    ob_get_clean();

    return did_action('wp_body_open') === 1;
});

check('wrapper does not double-fire wp_body_open when the theme header already fired it', static function () use ($theme_builder_wrapper_path) {
    reset_wrapper_hooks();
    add_action('get_header', static function () {
        wp_body_open(); // simulates a modern theme's header.php calling it directly
    });

    ob_start();
    require $theme_builder_wrapper_path;
    ob_get_clean();

    return did_action('wp_body_open') === 1;
});

/* ── ThemeBuilder\Compatibility\Themes\GlobalCompatibility ───────── */

final class FixedHeaderThemeBuilderManager extends ThemeBuilderManager
{
    /** @var int */
    public $render_header_calls = 0;

    public function get_header_id()
    {
        return 20;
    }

    public function get_footer_id()
    {
        return null;
    }

    public function render_header(): void
    {
        $this->render_header_calls++;
        echo '<div class="fixed-header-marker"></div>';
    }
}

function reset_global_compat_hooks(): void
{
    unset($GLOBALS['__wp_actions']['get_header'], $GLOBALS['__wp_actions']['wp_body_open'], $GLOBALS['__locate_template_stub']);
    $GLOBALS['__wp_actions_fired']['wp_body_open'] = 0;
}

check('GlobalCompatibility renders the configured header exactly once when the theme never fires wp_body_open', static function () {
    reset_global_compat_hooks();

    $manager = new FixedHeaderThemeBuilderManager();
    (new GlobalCompatibility())->register($manager);
    // No __locate_template_stub set — mirrors a theme with no header.php
    // (or the deprecated theme-compat fallback), which never calls wp_body_open().

    ob_start();
    do_action('get_header');
    ob_get_clean();

    return $manager->render_header_calls === 1 && did_action('wp_body_open') === 1;
});

check('GlobalCompatibility does not render the configured header twice when the theme already fires wp_body_open', static function () {
    reset_global_compat_hooks();

    $manager = new FixedHeaderThemeBuilderManager();
    (new GlobalCompatibility())->register($manager);
    $GLOBALS['__locate_template_stub'] = static function () {
        wp_body_open(); // simulates the located header.php calling it directly
    };

    ob_start();
    do_action('get_header');
    ob_get_clean();

    return $manager->render_header_calls === 1 && did_action('wp_body_open') === 1;
});

/* ── ConditionEvaluator semantics ───────────────────────────────── */

$manager = ConditionManager::instance();
$manager->register(new FixedCondition('test/yes', true));
$manager->register(new FixedCondition('test/no', false));
$manager->register(new FixedMockCondition());

$evaluator = ConditionEvaluator::instance();

$rule = static function (string $type, string $operator = 'include'): ConditionRuleDTO {
    return ConditionRuleDTO::from_array(['type' => $type, 'operator' => $operator]);
};

check('no conditions → show everywhere', static fn() => $evaluator->evaluate([]) === true);

check('single matching include → shown', static fn() =>
    $evaluator->evaluate([$rule('test/yes')]) === true);

check('single non-matching include → hidden', static fn() =>
    $evaluator->evaluate([$rule('test/no')]) === false);

check('includes OR together (no + yes → shown)', static fn() =>
    $evaluator->evaluate([$rule('test/no'), $rule('test/yes')]) === true);

check('matching exclude vetoes a matching include', static fn() =>
    $evaluator->evaluate([$rule('test/yes'), $rule('test/yes', 'exclude')]) === false);

check('non-matching exclude alone → show everywhere', static fn() =>
    $evaluator->evaluate([$rule('test/no', 'exclude')]) === true);

check('unknown include type is skipped (→ show everywhere)', static fn() =>
    $evaluator->evaluate([$rule('test/unregistered')]) === true);

check('unknown type skipped but real include still decides', static fn() =>
    $evaluator->evaluate([$rule('test/unregistered'), $rule('test/no')]) === false);

check('mock include fails closed (pro deactivated → template hidden)', static fn() =>
    $evaluator->evaluate([$rule('mock/locked')]) === false);

check('mock exclude never vetoes (check is false)', static fn() =>
    $evaluator->evaluate([$rule('mock/locked', 'exclude'), $rule('test/yes')]) === true);

/* ── Migrator ordering, range and stamping ──────────────────────── */

check('runs due migrations in ascending version order', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');
    $versions = array_column($migrator->log, 0);

    return $versions === ['1.0.5', '1.2.0', '1.10.0'];
});

check('stamps DB version after the last completed step', static function () {
    update_option(OptionKeys::DB_VERSION, '1.0.0');
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');

    return get_option(OptionKeys::DB_VERSION) === '1.10.0';
});

check('skips migrations at or below the installed version', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.5', '2.0.0');

    return array_column($migrator->log, 0) === ['1.2.0', '1.10.0'];
});

check('skips migrations above the target version', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '1.2.0');

    return array_column($migrator->log, 0) === ['1.0.5', '1.2.0'];
});

check('nothing runs when already current', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.10.0', '1.10.0');

    return $migrator->log === [];
});

check('callbacks receive (from, to)', static function () {
    $migrator = new RecordingMigrator();
    $migrator->run('1.0.0', '2.0.0');

    return $migrator->log[0][1] === '1.0.0' && $migrator->log[0][2] === '2.0.0';
});

/* ── Sanitizer rules ────────────────────────────────────────────── */

check('ARRAY: JSON string decodes to array', static fn() =>
    Sanitizer::apply_rule('{"a":1,"b":[2,3]}', Sanitizer::ARRAY) === ['a' => 1, 'b' => [2, 3]]);

check('ARRAY: array passes through untouched', static fn() =>
    Sanitizer::apply_rule([1, 2], Sanitizer::ARRAY) === [1, 2]);

check('ARRAY: scalar wraps instead of unserializing (object-injection guard)', static function () {
    $payload = 'O:8:"stdClass":0:{}'; // PHP-serialized — must NOT be unserialized.
    $result  = Sanitizer::apply_rule($payload, Sanitizer::ARRAY);

    return $result === [$payload];
});

check('ARRAY: empty string becomes empty array', static fn() =>
    Sanitizer::apply_rule('', Sanitizer::ARRAY) === []);

check('ARRAY_DEEP: string leaves sanitized, non-strings untouched', static function () {
    $result = Sanitizer::apply_rule(
        ['a' => "<script>x</script>ok", 'b' => 5, 'c' => ['d' => true, 'e' => " spaced  out "]],
        Sanitizer::ARRAY_DEEP
    );

    return $result['a'] === 'ok' && $result['b'] === 5 && $result['c']['d'] === true && $result['c']['e'] === 'spaced out';
});

check('DATETIME: valid input normalized to Y-m-d H:i:s', static fn() =>
    Sanitizer::apply_rule('2026-07-17T10:30:00+00:00', Sanitizer::DATETIME) === '2026-07-17 10:30:00');

check('DATETIME: garbage returns null', static fn() =>
    Sanitizer::apply_rule('not-a-date', Sanitizer::DATETIME) === null);

check('INT/BOOL casts', static fn() =>
    Sanitizer::apply_rule('42abc', Sanitizer::INT) === 42 && Sanitizer::apply_rule('1', Sanitizer::BOOL) === true);

check('null passes through every rule untouched', static fn() =>
    Sanitizer::apply_rule(null, Sanitizer::TEXT) === null);

conclude();
