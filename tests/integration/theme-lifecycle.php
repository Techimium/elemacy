<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;
use Elemacy\Tests\Integration\Fixtures\ThemeLifecycleHarness;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemeLifecycleHarness.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

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
    private array $owned_terms = [];

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
            foreach (array_reverse($this->owned_terms) as $term_id) {
                wp_delete_term($term_id, 'category');
            }
        } finally {
            $this->harness->restore();
            parent::tearDown();
        }
    }

    private function create_header(string $marker): int
    {
        $id = $this->documents->create_everywhere($marker, 'header', 'publish', LibraryDocument::heading($marker));
        $this->owned[] = $id;

        return $id;
    }

    private function create_footer(string $marker): int
    {
        $id = $this->documents->create_everywhere($marker, 'footer', 'publish', LibraryDocument::heading($marker));
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
        $id = $this->documents->create_everywhere($marker, 'header', 'publish', $elements);
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

    public function testAHeaderWhoseWidgetThrowsLeavesTheHeaderAreaEmptyWithoutFailingThePage(): void
    {
        // A published header is used as published, like a Single template:
        // one whose widget throws renders nothing, and the theme's own header
        // stays replaced. Deciding otherwise would mean rendering the
        // template before the page starts, which printed its styles before
        // <!DOCTYPE html>. Rendered in its own process so the theme's
        // header.php loads as it does on a real request.
        $id = $this->documents->create_everywhere('Elemacy throwing header', 'header', 'publish', [[
            'id' => 'eac9401', 'elType' => 'widget', 'widgetType' => 'elemacy-fixture-throws', 'settings' => [], 'elements' => [],
        ]]);
        $this->owned[] = $id;

        $html = ThemePageRequest::render('theme-generic-chrome', get_permalink($this->post_id))['html'];

        self::assertStringContainsString('Theme lifecycle fixture content.', $html, 'The page still renders its content: ' . $html);
        self::assertStringNotContainsString('Generic native header', $html, "The theme's own header stays replaced: " . $html);
        self::assertStringNotContainsString('elementor-' . $id . '"', $html, 'The header area is empty.');
        self::assertStringContainsString('Generic native footer', $html, 'The other side is untouched.');
        $this->assertSingleDocumentShell($html);
    }

    /** A published Entire Site header whose heading has a color, so its CSS is not empty. */
    private function create_styled_header(string $marker, string $color): int
    {
        $elements = LibraryDocument::heading($marker);
        $elements[0]['elements'][0]['settings']['title_color'] = $color;

        $id = $this->documents->create_everywhere($marker, 'header', 'publish', $elements);
        $this->owned[] = $id;

        return $id;
    }

    /** @param array<int, array{id: int, during: string[]}> $renders */
    private static function renders_of(int $id, array $renders): array
    {
        return array_values(array_filter($renders, static fn(array $render): bool => $id === $render['id']));
    }

    public function testInternalEmbeddingPrintsTheHeaderStylesInsideTheHeadAndNothingBeforeTheDoctype(): void
    {
        $id = $this->create_styled_header('Elemacy inline-css header', '#1a2b3c');

        $page = ThemePageRequest::render('elemacy-fixture', get_permalink($this->post_id), 'guest', [], false, [], [
            'options' => ['elementor_css_print_method' => 'internal'],
        ]);
        $html = $page['html'];

        self::assertMatchesRegularExpression('/^<!doctype html>/i', $html, 'The response starts with the doctype: ' . substr($html, 0, 300));
        self::assertSame(1, substr_count($html, 'Elemacy inline-css header'), 'The header renders once.');
        $rule = strpos($html, '#1a2b3c');
        self::assertNotFalse($rule, "The header's styles are printed: " . $html);
        self::assertLessThan(strpos($html, '</head>'), $rule, "The header's styles are inside <head>.");
        foreach (self::renders_of($id, $page['renders']) as $render) {
            self::assertNotContains('wp', $render['during'], 'The header is not rendered on the wp action.');
        }
        $this->assertSingleDocumentShell($html);
    }

    public function testInFileModeTheHeaderStylesheetLoadsAfterElementorsFrontendStylesheet(): void
    {
        $id = $this->create_styled_header('Elemacy file-css header', '#2b3c4d');

        $html = ThemePageRequest::render('elemacy-fixture', get_permalink($this->post_id), 'guest', [], false, [], [
            'options' => ['elementor_css_print_method' => 'external'],
        ])['html'];

        $frontend = strpos($html, "id='elementor-frontend-css'");
        $header = strpos($html, "id='elementor-post-" . $id . "-css'");
        self::assertNotFalse($frontend, "Elementor's frontend stylesheet is linked: " . $html);
        self::assertNotFalse($header, "The header's stylesheet is linked.");
        self::assertGreaterThan($frontend, $header, "The header's stylesheet comes after Elementor's frontend stylesheet.");
    }

    public function testARedirectedRequestNeverRendersTheHeader(): void
    {
        $id = $this->create_styled_header('Elemacy redirect header', '#3c4d5e');
        $page_id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Redirected page', 'post_content' => 'x'], true);
        self::assertIsInt($page_id);
        $this->owned[] = $page_id;

        // ?p= names a post, so WordPress redirects a page's id to ?page_id=.
        $page = ThemePageRequest::render('elemacy-fixture', home_url('/?p=' . $page_id), 'guest', [], false, [], [
            'options' => ['elementor_css_print_method' => 'internal'],
        ]);

        self::assertNotNull($page['redirect'], 'Sanity: the request is redirected.');
        self::assertSame('', $page['html'], 'The redirect response carries no template output.');
        self::assertSame([], self::renders_of($id, $page['renders']), 'The header is never rendered for a redirect.');
    }

    public function testAWooCommerceAjaxRequestRendersNoTemplateAndKeepsItsBody(): void
    {
        $header = $this->create_styled_header('Elemacy wc-ajax header', '#4d5e6f');
        $footer = $this->create_footer('Elemacy wc-ajax footer');
        $body = '{"fragments":{"div.widget_shopping_cart_content":"<div>cart<\/div>"},"cart_hash":""}';

        $page = ThemePageRequest::render('elemacy-fixture', home_url('/?wc-ajax=get_refreshed_fragments'), 'guest', [], false, [], [
            'options' => ['elementor_css_print_method' => 'internal'],
            'exit_on_template_redirect' => $body,
        ]);

        self::assertSame($body, $page['html'], 'The JSON response is exactly what the endpoint printed.');
        self::assertSame([], self::renders_of($header, $page['renders']), 'The header is not rendered.');
        self::assertSame([], self::renders_of($footer, $page['renders']), 'The footer is not rendered.');
    }

    public function testABlockThemeKeepsItsOwnHeaderAndFooterAroundASingleTemplate(): void
    {
        $this->assertSkippedIfThemeMissing('twentytwentyfive');
        $id = $this->documents->create_everywhere('Elemacy block single', 'single', 'publish', LibraryDocument::heading('Elemacy block single'));
        $this->owned[] = $id;

        $html = ThemePageRequest::render('twentytwentyfive', get_permalink($this->post_id))['html'];

        self::assertSame(1, substr_count($html, 'Elemacy block single'), 'The Single template renders once: ' . $html);
        self::assertSame(1, substr_count($html, '<header class="wp-block-template-part'), "Twenty Twenty-Five's own header part is shown.");
        self::assertSame(1, substr_count($html, '<footer class="wp-block-template-part'), "Twenty Twenty-Five's own footer part is shown.");
        $blocks = strpos($html, '<div class="wp-site-blocks">');
        self::assertNotFalse($blocks, "The page is laid out in the theme's site wrapper.");
        self::assertLessThan(strpos($html, '<header class="wp-block-template-part'), $blocks, 'The header part is inside the site wrapper.');
        self::assertLessThan(strpos($html, 'Elemacy block single'), strpos($html, '<header class="wp-block-template-part'), 'The Single template follows the header.');
        self::assertLessThan(strpos($html, '<footer class="wp-block-template-part'), strpos($html, 'Elemacy block single'), 'The footer follows the Single template.');
        $this->assertSingleDocumentShell($html);
    }

    public function testABlockThemeShowsAnElemacyHeaderOnceAndItsOwnFooterAroundASingleTemplate(): void
    {
        $this->assertSkippedIfThemeMissing('twentytwentyfive');
        $id = $this->documents->create_everywhere('Elemacy block single two', 'single', 'publish', LibraryDocument::heading('Elemacy block single two'));
        $this->owned[] = $id;
        $this->create_header('Elemacy block header');

        $html = ThemePageRequest::render('twentytwentyfive', get_permalink($this->post_id))['html'];

        self::assertSame(1, substr_count($html, 'Elemacy block header'), 'The Elemacy header renders once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy block single two'), 'The Single template renders once.');
        self::assertSame(0, substr_count($html, '<header class="wp-block-template-part'), "The theme's header part is replaced.");
        self::assertSame(1, substr_count($html, '<footer class="wp-block-template-part'), "The theme's own footer part is shown.");
        $this->assertSingleDocumentShell($html);
    }

    public function testInternalEmbeddingOnABlockThemeStartsWithTheDoctypeAndStylesTheHeader(): void
    {
        $this->assertSkippedIfThemeMissing('twentytwentyfive');
        $this->create_styled_header('Elemacy block inline header', '#6f7081');

        $html = ThemePageRequest::render('twentytwentyfive', get_permalink($this->post_id), 'guest', [], false, [], [
            'options' => ['elementor_css_print_method' => 'internal'],
        ])['html'];

        self::assertMatchesRegularExpression('/^<!DOCTYPE html>/i', $html, 'The response starts with the doctype: ' . substr($html, 0, 300));
        self::assertSame(1, substr_count($html, 'Elemacy block inline header'), 'The header renders once.');
        self::assertStringContainsString('#6f7081', $html, 'The header is styled.');
        $this->assertSingleDocumentShell($html);
    }

    public function testPopupPreviewRendersWithoutTheSiteHeaderAndFooter(): void
    {
        $this->create_header('Elemacy Preview header');
        $this->create_footer('Elemacy Preview footer');
        $popup = $this->documents->create('Elemacy preview popup', 'popup', 'publish', LibraryDocument::heading('Elemacy popup body marker'));
        $this->owned[] = $popup;

        // A popup preview is served through Elementor Canvas. The generic adapter
        // injects the header on wp_body_open, which Canvas fires: the path that used
        // to leak the header into a popup.
        // That every ordinary page still gets its header is asserted by the other
        // tests in this class; this one is deliberately a single render, since the
        // generic adapter require_once's header.php and its sibling tests share
        // this process.
        $this->harness->switch_theme('elemacy-fixture');
        $preview = $this->harness->render(get_permalink($popup));

        self::assertStringContainsString('Elemacy popup body marker', $preview, 'The popup preview must render the popup itself: ' . $preview);
        self::assertSame(0, substr_count($preview, 'Elemacy Preview header'), 'A popup preview is a standalone canvas: the site header must not render beside it.');
        self::assertSame(0, substr_count($preview, 'Elemacy Preview footer'), 'A popup preview must not render the site footer either.');
    }

    public function testATemplateIsNeverRenderedAsItsOwnHeaderOrFooter(): void
    {
        // Entire Site, so each would resolve for the very request that edits
        // it. Elementor's Full Width layout then asks for
        // the header/footer around the template and refuses to render it inside
        // itself ("The Template ID cannot be the same as the currently edited
        // template"). Deliberately resolution only: a real render would need the
        // generic adapter's require_once'd header.php.
        $header = $this->create_header('Elemacy Self header');
        $footer = $this->create_footer('Elemacy Self footer');

        $request = new RequestSimulator();

        try {
            $manager = ThemeBuilderManager::instance();

            $request->visit(get_permalink($header));
            self::assertNull($manager->get_header_id(), 'The header being edited must not be rendered as its own header.');
            self::assertSame($footer, $manager->get_footer_id(), 'The site footer still renders around the header being edited.');

            $request->visit(get_permalink($footer));
            self::assertSame($header, $manager->get_header_id(), 'The site header still renders around the footer being edited.');
            self::assertNull($manager->get_footer_id(), 'The footer being edited must not be rendered as its own footer.');

            $request->visit(get_permalink($this->post_id));
            self::assertSame($header, $manager->get_header_id(), 'An ordinary page still gets the header.');
            self::assertSame($footer, $manager->get_footer_id(), 'An ordinary page still gets the footer.');
        } finally {
            $request->restore();
        }
    }

    public function testAnArchiveKeepsItsHeaderWhenTheQueriedObjectIdMatchesATemplateId(): void
    {
        $header = $this->create_header('Elemacy Archive header');
        $footer = $this->create_footer('Elemacy Archive footer');

        $term = wp_insert_term('Elemacy archive term ' . wp_generate_uuid4(), 'category');
        self::assertIsArray($term, 'Could not create the fixture category.');
        $this->owned_terms[] = (int) $term['term_id'];
        // An archive with no posts is a 404, which is not the request under test.
        wp_set_post_terms($this->post_id, [(int) $term['term_id']], 'category');

        $link = get_term_link((int) $term['term_id'], 'category');
        self::assertIsString($link, 'Could not build the fixture category link.');

        $request = new RequestSimulator();

        try {
            $request->visit($link);
            self::assertTrue(is_category(), 'The fixture request must really be a category archive.');
            self::assertFalse(is_singular(), 'A category archive is not a singular request.');

            // On an archive get_queried_object_id() is a TERM id — on an author
            // archive, a user id. Those are numbered independently of posts, so a
            // site can perfectly well have a term carrying its header template's
            // number, and a template-vs-queried-object comparison that does not
            // first establish the request is *for* a library post would drop the
            // header from that one archive. Forcing the number is the only
            // arranged part of this request; everything else is a real archive.
            // get_queried_object() resolves lazily and rewrites queried_object_id
            // as it goes, so let it resolve the real term first, then renumber.
            $GLOBALS['wp_query']->get_queried_object();
            $GLOBALS['wp_query']->queried_object_id = $header;
            self::assertSame($header, (int) get_queried_object_id(), 'Sanity: the collision must actually be in place.');

            $manager = ThemeBuilderManager::instance();
            self::assertSame($header, $manager->get_header_id(), 'A category archive keeps its header even when a term carries the header template\'s number.');

            $GLOBALS['wp_query']->queried_object_id = $footer;
            self::assertSame($footer, $manager->get_footer_id(), 'The same holds for the footer.');
        } finally {
            $request->restore();
        }
    }

    public function testElementorCanvasPageRendersWithoutTheSiteHeaderAndFooter(): void
    {
        $this->create_header('Elemacy Canvas header');
        $this->create_footer('Elemacy Canvas footer');
        $page = wp_insert_post([
            'post_type' => 'page', 'post_status' => 'publish',
            'post_title' => 'Canvas page', 'post_content' => 'Canvas page body marker.',
            'meta_input' => ['_wp_page_template' => 'elementor_canvas'],
        ], true);
        self::assertIsInt($page);
        $this->owned[] = $page;

        // Like Elementor Pro's Theme Builder, Canvas is a blank page: the generic
        // adapter used to print the header on wp_body_open, which Canvas fires.
        $this->harness->switch_theme('elemacy-fixture');
        $html = $this->harness->render(get_permalink($page));

        self::assertStringContainsString('elementor-template-canvas', $html, 'The page must actually be served through Elementor Canvas: ' . $html);
        self::assertStringContainsString('Canvas page body marker.', $html, 'The page content must still render.');
        self::assertSame(0, substr_count($html, 'Elemacy Canvas header'), 'No site header on an Elementor Canvas page.');
        self::assertSame(0, substr_count($html, 'Elemacy Canvas footer'), 'No site footer on an Elementor Canvas page.');
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
        // The header also carries a widget whose own output reuses a common
        // theme class name ("site-header") deep inside the rendered content:
        // replacing the theme's chrome must never touch builder content.
        // Discard, one-sided replacement and hook integrity are covered in
        // native-chrome-replacement.php, which renders each page in its own
        // process.
        $nested_marker = 'Elemacy nested site-header marker';
        $header = $this->create_header_with_nested_site_header_marker('Elemacy Generic header', $nested_marker);
        $footer = $this->create_footer('Elemacy Generic footer');

        $this->harness->switch_theme('elemacy-fixture');
        $html = $this->harness->render(get_permalink($this->post_id));

        self::assertSame(1, substr_count($html, 'Elemacy Generic header'), 'Header must render exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Generic footer'), 'Footer must render exactly once.');
        self::assertSame(0, substr_count($html, 'fixture-native-header'), "The theme's own header is not in the response.");
        self::assertSame(0, substr_count($html, 'fixture-native-footer'), "The theme's own footer is not in the response.");
        self::assertStringContainsString($nested_marker, $html, 'Builder content bearing a reused ".site-header" class must still render: ' . $html);
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
        $id = $this->documents->create_everywhere($marker, 'single', 'publish', LibraryDocument::heading($marker));
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
            $id = $this->documents->create_everywhere($marker, $type, 'publish', LibraryDocument::heading($marker));

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
