<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

/**
 * The Page Layout of a Theme Builder location template (Single, Archive,
 * Search, 404), and of the post being viewed, decides whether the site header
 * and footer render, as in Elementor Pro: Canvas is bare, Elementor Full Width
 * and Default keep the header and footer, and a post's own non-default layout
 * wins over the Single template. Each page is rendered as a visitor in its own
 * PHP process.
 */
final class LocationLayoutsTest extends IntegrationTestCase
{
    private const CANVAS = 'elementor_canvas';
    private const FULL_WIDTH = 'elementor_header_footer';

    private const HEADER = 'Elemacy layout header';
    private const FOOTER = 'Elemacy layout footer';
    private const LOCATION = 'Elemacy layout location';
    private const POST_BODY = 'Layout post body marker.';

    private int $post_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Layout post', 'post_content' => self::POST_BODY,
        ], true);
        self::assertIsInt($this->post_id);
    }

    protected function tearDown(): void
    {
        try {
            wp_delete_post($this->post_id, true);
        } finally {
            parent::tearDown();
        }
    }

    /** A published template of $type, with the Page Layout $layout when given. */
    private function template(string $type, string $marker, string $layout = ''): int
    {
        $id = $this->documents->create($marker, $type, 'publish', LibraryDocument::heading($marker));
        if ('' !== $layout) {
            update_post_meta($id, '_wp_page_template', $layout);
        }

        return $id;
    }

    private function site_chrome(): void
    {
        $this->template('header', self::HEADER);
        $this->template('footer', self::FOOTER);
    }

    private function url(string $type): string
    {
        switch ($type) {
            case '404':
                // Plain permalinks: an id nothing owns is a 404.
                return home_url('/?p=999999999');
            case 'archive':
                return (string) get_term_link(1, 'category');
            default:
                return get_permalink($this->post_id);
        }
    }

    /**
     * Native chrome markers of each theme, so "gone" is checked on the theme's
     * own markup and not only on the Elemacy templates.
     *
     * @return array<string, array{string, string, string[]}>
     */
    public function bareLocations(): array
    {
        $classic = ['Location wrapper native header', 'Location wrapper native footer'];

        return [
            'single on a classic theme' => ['theme-location-wrapper', 'single', $classic],
            '404 on a classic theme' => ['theme-location-wrapper', '404', $classic],
            'archive on a classic theme' => ['theme-location-wrapper', 'archive', $classic],
            'single on a theme with no header.php' => ['theme-compat-stub', 'single', ['id="header"']],
            'single on Hello Elementor' => ['hello-elementor', 'single', ['<header id="site-header"', '<footer id="site-footer"']],
            'single on a block theme' => ['twentytwentyfive', 'single', ['<header class="wp-block-template-part"', '<footer class="wp-block-template-part"']],
        ];
    }

    private function skip_unless_installed(string $theme): void
    {
        if (!wp_get_theme($theme)->exists()) {
            self::markTestSkipped("Theme '$theme' is not installed; provision with setup.py.");
        }
    }

    /**
     * @dataProvider bareLocations
     * @param string[] $native Markers of the theme's own header and footer.
     */
    public function testALocationTemplateSetToCanvasIsServedBareWhateverThePostsOwnLayout(string $theme, string $type, array $native): void
    {
        $this->skip_unless_installed($theme);
        $this->site_chrome();
        $this->template($type, self::LOCATION, self::CANVAS);

        $page = ThemePageRequest::render($theme, $this->url($type));
        $html = $page['html'];

        self::assertSame(1, substr_count($html, self::LOCATION), 'The location template renders once: ' . $html);
        self::assertSame(0, substr_count($html, self::HEADER), 'No Elemacy header on a Canvas page.');
        self::assertSame(0, substr_count($html, self::FOOTER), 'No Elemacy footer on a Canvas page.');
        foreach ($native as $marker) {
            self::assertStringNotContainsString($marker, $html, "The theme's own chrome ($marker) is absent.");
        }
        self::assertMatchesRegularExpression('/<body[^>]*class="[^"]*elementor-template-canvas/', $html, 'The page is marked as an Elementor Canvas page.');
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each shell callback runs once.');
        $this->assert_single_document_shell($html);
    }

    public function testABareLocationTemplateStillGetsItsStylesAndTheSitesScripts(): void
    {
        $this->site_chrome();
        $id = $this->template('single', self::LOCATION, self::CANVAS);

        $page = ThemePageRequest::render('theme-location-wrapper', $this->url('single'));
        $html = $page['html'];

        self::assertMatchesRegularExpression('/post-' . $id . '\.css|elementor-post-' . $id . '/', $html, "The template's own stylesheet is present: " . $html);
        self::assertStringContainsString('elementor-frontend', $html, "Elementor's frontend script is enqueued on the bare page.");
        // Popups and every other plugin's footer output print on wp_footer.
        self::assertSame(1, $page['calls']['wp_footer'], 'wp_footer runs once, so popups and footer scripts still print.');
    }

    /** @return array<string, array{string}> */
    public function chromeKeepingLayouts(): array
    {
        return ['Default' => [''], 'WordPress default' => ['default'], 'Elementor Full Width' => [self::FULL_WIDTH]];
    }

    /** @dataProvider chromeKeepingLayouts */
    public function testOtherLayoutsKeepTheHeaderAndFooter(string $layout): void
    {
        $this->site_chrome();
        $this->template('single', self::LOCATION, $layout);

        $html = ThemePageRequest::render('theme-location-wrapper', $this->url('single'))['html'];

        self::assertSame(1, substr_count($html, self::LOCATION), 'The location template renders once: ' . $html);
        self::assertSame(1, substr_count($html, self::HEADER), 'The Elemacy header renders once.');
        self::assertSame(1, substr_count($html, self::FOOTER), 'The Elemacy footer renders once.');
        self::assertStringNotContainsString('Location wrapper native header', $html);
        self::assertDoesNotMatchRegularExpression('/<body[^>]*class="[^"]*elementor-template-canvas/', $html, 'Only Canvas is marked as a Canvas page.');
    }

    public function testElementorFullWidthIsMarkedWithElementorsFullWidthClass(): void
    {
        $this->site_chrome();
        $this->template('single', self::LOCATION, self::FULL_WIDTH);

        $html = ThemePageRequest::render('theme-location-wrapper', $this->url('single'))['html'];

        self::assertMatchesRegularExpression('/<body[^>]*class="[^"]*elementor-template-full-width/', $html, 'The body carries the class Elementor adds to a Full Width page: ' . $html);
    }

    public function testAPostSetToCanvasIsServedAsItsOwnCanvasPageAndTheSingleTemplateIsNotApplied(): void
    {
        update_post_meta($this->post_id, '_wp_page_template', self::CANVAS);
        $this->site_chrome();
        $this->template('single', self::LOCATION);

        $html = ThemePageRequest::render('theme-location-wrapper', $this->url('single'))['html'];

        self::assertSame(1, substr_count($html, self::POST_BODY), "The post's own content renders once: " . $html);
        self::assertSame(0, substr_count($html, self::LOCATION), 'The Single template is not applied.');
        self::assertSame(0, substr_count($html, self::HEADER), 'No header on the post\'s Canvas page.');
        self::assertSame(0, substr_count($html, self::FOOTER), 'No footer on the post\'s Canvas page.');
        self::assertMatchesRegularExpression('/<body[^>]*class="[^"]*elementor-template-canvas/', $html);
    }

    public function testAPostSetToElementorFullWidthKeepsTheSiteChromeAndSkipsTheSingleTemplate(): void
    {
        update_post_meta($this->post_id, '_wp_page_template', self::FULL_WIDTH);
        $this->site_chrome();
        $this->template('single', self::LOCATION);

        $html = ThemePageRequest::render('theme-location-wrapper', $this->url('single'))['html'];

        self::assertSame(1, substr_count($html, self::POST_BODY), "The post's own content renders once: " . $html);
        self::assertSame(0, substr_count($html, self::LOCATION), 'The Single template is not applied.');
        self::assertSame(1, substr_count($html, self::HEADER), 'The Elemacy header still renders once.');
        self::assertSame(1, substr_count($html, self::FOOTER), 'The Elemacy footer still renders once.');
    }

    public function testAPostUsingAPageTemplateOfTheThemeIsServedByThatTemplate(): void
    {
        update_post_meta($this->post_id, '_wp_page_template', 'page-named.php');
        $this->template('single', self::LOCATION);

        $html = ThemePageRequest::render('elemacy-fixture', $this->url('single'))['html'];

        self::assertSame(1, substr_count($html, 'id="fixture-host"'), "The theme's named page template serves the post: " . $html);
        self::assertSame(1, substr_count($html, self::POST_BODY));
        self::assertSame(0, substr_count($html, self::LOCATION), 'The Single template is not applied.');
    }

    public function testAPostWithTheDefaultLayoutGetsTheSingleTemplate(): void
    {
        $this->site_chrome();
        $this->template('single', self::LOCATION);

        $html = ThemePageRequest::render('theme-location-wrapper', $this->url('single'))['html'];

        self::assertSame(1, substr_count($html, self::LOCATION), 'The Single template is applied: ' . $html);
    }

    public function testAnArchiveTemplateIsAppliedWhateverLayoutThePostsHave(): void
    {
        update_post_meta($this->post_id, '_wp_page_template', self::CANVAS);
        $this->site_chrome();
        $this->template('archive', self::LOCATION);

        $html = ThemePageRequest::render('theme-location-wrapper', $this->url('archive'))['html'];

        self::assertSame(1, substr_count($html, self::LOCATION), 'The Archive template is applied: ' . $html);
        self::assertSame(1, substr_count($html, self::HEADER), 'The header renders: a post\'s layout does not affect an archive.');
    }

    private function assert_single_document_shell(string $html): void
    {
        self::assertSame(1, preg_match_all('/<html[ >]/', $html), 'Exactly one <html> tag.');
        self::assertSame(1, preg_match_all('/<head[ >]/', $html), 'Exactly one <head> tag.');
        self::assertSame(1, preg_match_all('/<body[ >]/', $html), 'Exactly one <body> tag.');
        self::assertSame(1, substr_count($html, '</body>'), 'Exactly one </body> tag.');
    }
}
