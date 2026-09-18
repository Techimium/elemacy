<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemeLifecycleHarness;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemeLifecycleHarness.php';

/**
 * Test double proving Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION lets
 * an add-on contribute a theme mapping entirely from outside the plugin's own
 * adapter classes — it is a wholly independent class the resolver must be
 * able to select without any built-in adapter class knowing it exists.
 */
final class ExternalMarkerCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
    }
}

/**
 * design.md D2: theme adapters own header/footer replacement without
 * destroying WordPress hooks. Verifies, per mapped theme, that Elemacy's
 * content renders exactly once, the theme's own chrome does not
 * double-render, wp_head()/wp_footer() each fire exactly once (so another
 * plugin's callback on them still runs, exactly once), and the document
 * shell is not duplicated.
 */
final class ThemeLifecycleTest extends IntegrationTestCase
{
    private ThemeLifecycleHarness $harness;
    private int $post_id;
    private array $owned = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new ThemeLifecycleHarness();
        $this->post_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Theme lifecycle post', 'post_content' => 'Theme lifecycle fixture content.',
        ], true);
        self::assertIsInt($this->post_id);
    }

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
            wp_delete_post($this->post_id, true);
        } finally {
            $this->harness->restore();
            parent::tearDown();
        }
    }

    private function create_header(string $marker): int
    {
        $id = $this->documents->create($marker, 'header', 'publish', LibraryDocument::heading($marker));
        $this->owned[] = $id;

        return $id;
    }

    private function create_footer(string $marker): int
    {
        $id = $this->documents->create($marker, 'footer', 'publish', LibraryDocument::heading($marker));
        $this->owned[] = $id;

        return $id;
    }

    /** A header document with an extra widget whose raw output nests a `.site-header`-classed element inside it, simulating builder content that reuses a common class name. */
    private function create_header_with_nested_site_header_marker(string $marker, string $nested_marker): int
    {
        $elements = LibraryDocument::heading($marker);
        $elements[] = [
            'id' => 'eac2001',
            'elType' => 'container',
            'settings' => [],
            'elements' => [[
                'id' => 'eac2002',
                'elType' => 'widget',
                'widgetType' => 'html',
                'settings' => ['html' => '<div class="site-header">' . $nested_marker . '</div>'],
                'elements' => [],
            ]],
        ];
        $id = $this->documents->create($marker, 'header', 'publish', $elements);
        $this->owned[] = $id;

        return $id;
    }

    public function testHelloElementorRendersElemacyChromeExactlyOnceAndPreservesThirdPartyHooks(): void
    {
        $this->assertSkippedIfThemeMissing('hello-elementor');

        $header = $this->create_header('Elemacy Hello header');
        $footer = $this->create_footer('Elemacy Hello footer');

        $third_party_head = 0;
        $third_party_footer = 0;
        add_action('wp_head', static function () use (&$third_party_head) {
            $third_party_head++;
        }, 999);
        add_action('wp_footer', static function () use (&$third_party_footer) {
            $third_party_footer++;
        }, 999);

        $this->harness->switch_theme('hello-elementor');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, 'Elemacy Hello header'), 'Header must render exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Hello footer'), 'Footer must render exactly once.');
        self::assertSame(0, substr_count($html, 'id="site-header"'), "Hello's own default header block must not render.");
        self::assertSame(0, substr_count($html, 'id="site-footer"'), "Hello's own default footer block must not render.");
        self::assertSame(1, $third_party_head, 'A third-party wp_head callback must still fire exactly once.');
        self::assertSame(1, $third_party_footer, 'A third-party wp_footer callback must still fire exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    public function testDedicatedAdapterRemovesOnlyItsOwnCallbackAndPreservesThirdPartyHooks(): void
    {
        $header = $this->create_header('Elemacy Astra header');
        $footer = $this->create_footer('Elemacy Astra footer');

        $third_party_head = 0;
        $third_party_footer = 0;
        add_action('wp_head', static function () use (&$third_party_head) {
            $third_party_head++;
        }, 999);
        add_action('wp_footer', static function () use (&$third_party_footer) {
            $third_party_footer++;
        }, 999);

        $this->harness->switch_theme('astra');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, 'Elemacy Astra header'), 'Header must render exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Astra footer'), 'Footer must render exactly once.');
        self::assertSame(0, substr_count($html, 'Astra-like native header'), "Astra's own default header callback must be removed.");
        self::assertSame(0, substr_count($html, 'Astra-like native footer'), "Astra's own default footer callback must be removed.");
        self::assertSame(1, $third_party_head, 'A third-party wp_head callback must still fire exactly once.');
        self::assertSame(1, $third_party_footer, 'A third-party wp_footer callback must still fire exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    public function testGenericThemeWithBodyOpenRendersElemacyHeaderOnce(): void
    {
        // The header also carries a widget whose own output happens to reuse
        // a common class name ("site-header") deep inside the rendered
        // content — task 2.5: GlobalCompatibility's suppression CSS must be
        // scoped so it can never reach into builder-authored content like
        // this, only the theme's own near-root chrome.
        $nested_marker = 'Elemacy nested site-header marker';
        $header = $this->create_header_with_nested_site_header_marker('Elemacy Generic header', $nested_marker);
        $footer = $this->create_footer('Elemacy Generic footer');

        $this->harness->switch_theme('elemacy-fixture');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, 'Elemacy Generic header'), 'Header must render exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Generic footer'), 'Footer must render exactly once.');
        // GlobalCompatibility cannot safely remove unidentified native chrome,
        // only CSS-hide it — the native markup itself remains in the HTML.
        self::assertSame(1, substr_count($html, 'fixture-native-header'), 'Native chrome remains in the markup (CSS-hidden, not removed).');
        self::assertStringContainsString('.elemacy-force-stretched-header', $html, 'A suppression stylesheet must be present.');
        self::assertStringContainsString($nested_marker, $html, 'Builder content bearing a reused ".site-header" class must still render in the markup: ' . $html);
        self::assertMatchesRegularExpression('/:is\([^)]*\)\s*>\s*\.site-header/', $html, 'The suppression rule for .site-header must be scoped with a child combinator off the near-root selector list.');
        self::assertDoesNotMatchRegularExpression('/(?<!>)\s\.site-header\s*\{/', $html, 'There must be no unscoped ".site-header { ... }" rule that could reach nested builder content.');
        $this->assertSingleDocumentShell($html);
    }

    public function testGenericThemeWithoutBodyOpenStillRendersElemacyHeaderOnce(): void
    {
        $header = $this->create_header('Elemacy NoBodyOpen header');

        $this->harness->switch_theme('theme-no-body-open');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, 'Elemacy NoBodyOpen header'), 'A theme that never calls wp_body_open() must still get the forced fallback firing exactly once: ' . $html);
        $this->assertSingleDocumentShell($html);
    }

    public function testNamedHeaderFooterTemplatesStillRenderElemacyChromeExactlyOnce(): void
    {
        $header = $this->create_header('Elemacy Named header');
        $footer = $this->create_footer('Elemacy Named footer');

        update_post_meta($this->post_id, '_wp_page_template', 'page-named.php');

        $this->harness->switch_theme('elemacy-fixture');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, 'Elemacy Named header'), 'Header must render exactly once with a named template: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Named footer'), 'Footer must render exactly once with a named template.');
        $this->assertSingleDocumentShell($html);
    }

    public function testBlockThemeRendersElemacyChromeExactlyOnceReplacingTheNativeTemplatePart(): void
    {
        $this->assertSkippedIfThemeMissing('twentytwentyfive');

        $header = $this->create_header('Elemacy Block header');
        $footer = $this->create_footer('Elemacy Block footer');

        $this->harness->switch_theme('twentytwentyfive');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, 'Elemacy Block header'), 'Header must render exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Block footer'), 'Footer must render exactly once.');
        self::assertSame(0, substr_count($html, 'wp-block-template-part'), 'The native header/footer template parts must be replaced, not merely hidden.');
        $this->assertSingleDocumentShell($html);
    }

    /**
     * design.md D2/task 2.2: adapter selection is registrable — an add-on
     * can contribute a theme mapping through
     * Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION without editing
     * CompatibilityManager or any other adapter, and existing mappings keep
     * resolving to their own built-in adapter. Exercised directly against
     * CompatibilityManager's resolution — a full page render adds nothing
     * here, since selection (not rendering) is what this task changed, and
     * every mapped theme's actual rendering is already covered above.
     */
    public function testExternallyRegisteredAdapterWinsForItsExactSlugWithoutEditingOtherAdapters(): void
    {
        $manager = \Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager::instance();

        $register = static function (\Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager $registry) {
            $registry->register('elemacy-contributed-fixture-slug', ExternalMarkerCompatibility::class);
        };
        add_action(\Elemacy\Core\Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION, $register);

        try {
            $register_defaults = new \ReflectionMethod($manager, 'register_default_adapters');
            $register_defaults->setAccessible(true);
            $register_defaults->invoke($manager);

            $resolve = new \ReflectionMethod($manager, 'resolve_compatibility');
            $resolve->setAccessible(true);

            $resolved_astra = $resolve->invoke($manager, ['astra']);
            $resolved_contributed = $resolve->invoke($manager, ['elemacy-contributed-fixture-slug']);
        } finally {
            remove_action(\Elemacy\Core\Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION, $register);
        }

        self::assertInstanceOf(\Elemacy\Modules\ThemeBuilder\Compatibility\Themes\AstraCompatibility::class, $resolved_astra, "An unrelated, already-registered theme mapping ('astra') must still resolve to its own built-in adapter, untouched by the new registration.");
        self::assertInstanceOf(ExternalMarkerCompatibility::class, $resolved_contributed, 'A newly contributed theme slug must resolve to the externally-registered adapter, defined entirely outside CompatibilityManager and every built-in adapter class.');
    }

    /**
     * design.md D2/task 2.4: theme-builder-wrapper.php (used when a Theme
     * Builder template replaces an entire content location, not just
     * header/footer) must still let a classic theme with its own header.php
     * own the document shell exactly once. A dedicated fixture theme is used
     * here — every other fixture theme's header.php has already been
     * require_once'd by an earlier test in this same long-lived PHPUnit
     * process, which would otherwise silently no-op a second load.
     */
    public function testLocationWrapperLetsClassicThemeHeaderOwnTheShellExactlyOnce(): void
    {
        $marker = 'Elemacy Location Wrapper single';
        $id = $this->documents->create($marker, 'single', 'publish', LibraryDocument::heading($marker));
        $this->owned[] = $id;

        $this->harness->switch_theme('theme-location-wrapper');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, $marker), 'The location template must render exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Location wrapper native header'), "The theme's own header.php must own the document shell exactly once.");
        self::assertSame(1, substr_count($html, 'Location wrapper native footer'), "The theme's own footer.php must own the document shell exactly once.");
        $this->assertSingleDocumentShell($html);
    }

    /**
     * design.md D2/task 2.4: for all four content locations (single, archive,
     * search, 404), when the active theme has no header.php at all (the same
     * situation a block theme is always in), theme-builder-wrapper.php must
     * own the whole document shell itself — via theme-support-header.php/
     * theme-support-footer.php — exactly once, with body-open firing exactly
     * once. A block theme is reused across all four sub-cases here because
     * its absent header.php is never require_once'd, so — unlike a classic
     * fixture theme — repeated renders in one process never collide.
     */
    public function testLocationWrapperFallsBackToOwnShellForAllFourContentLocationsWhenThemeHasNoHeaderFile(): void
    {
        $this->assertSkippedIfThemeMissing('twentytwentyfive');
        $this->harness->switch_theme('twentytwentyfive');

        $uncategorized = get_category_link(get_option('default_category'));
        $cases = [
            'single' => get_permalink($this->post_id),
            'archive' => $uncategorized,
            'search' => home_url('/?s=elemacy-location-wrapper-search'),
            // A bare path segment never 404s under this environment's plain
            // permalink structure (no rewrite rules recognize it, so it's
            // just ignored and WordPress falls back to the home query
            // instead) — a nonexistent numeric post id is a query var
            // WordPress always recognizes and correctly fails to match,
            // regardless of permalink structure.
            '404' => home_url('/?p=999999999'),
        ];

        foreach ($cases as $type => $url) {
            $marker = 'Elemacy Location Wrapper ' . $type;
            $id = $this->documents->create($marker, $type, 'publish', LibraryDocument::heading($marker));

            $html = $this->harness->render($url);

            self::assertSame(1, substr_count($html, $marker), "The '$type' location template must render exactly once: " . $html);
            $this->assertSingleDocumentShell($html);

            wp_delete_post($id, true);
        }
    }

    private function assertSkippedIfThemeMissing(string $stylesheet): void
    {
        if (!wp_get_theme($stylesheet)->exists()) {
            self::markTestSkipped("Theme '$stylesheet' is not available in this environment.");
        }
    }

    /**
     * One <head>/</head> pair is itself proof wp_head() fired exactly once —
     * a second firing would mean header.php (which contains it) ran twice,
     * duplicating the whole <head> block, not just its own contents.
     */
    private function assertSingleDocumentShell(string $html): void
    {
        self::assertSame(1, preg_match_all('/<html[ >]/', $html), 'Exactly one <html> tag: ' . $html);
        self::assertSame(1, substr_count($html, '<head>'), 'Exactly one <head> tag (wp_head() fired exactly once).');
        self::assertSame(1, substr_count($html, '</head>'), 'Exactly one </head> tag.');
        self::assertSame(1, substr_count($html, '<body'), 'Exactly one <body> tag.');
        self::assertSame(1, substr_count($html, '</body>'), 'Exactly one </body> tag.');
        self::assertSame(1, substr_count($html, '</html>'), 'Exactly one </html> tag.');
    }
}
