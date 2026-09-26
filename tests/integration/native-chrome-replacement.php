<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

/**
 * The generic adapter replaces a classic theme's header and footer by
 * discarding their output, not hiding it. Each page is rendered in its own
 * process (ThemePageRequest), so every render loads the theme's header.php
 * and footer.php for the first time, as a real request does.
 */
final class NativeChromeReplacementTest extends IntegrationTestCase
{
    private const THEME = 'theme-generic-chrome';

    private int $post_id;
    private array $owned = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->post_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Native chrome post', 'post_content' => 'Native chrome fixture content.',
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
            parent::tearDown();
        }
    }

    private function create(string $type, string $marker): int
    {
        $id = $this->documents->create($marker, $type, 'publish', LibraryDocument::heading($marker));
        $this->owned[] = $id;

        return $id;
    }

    public function testTheThemesOwnChromeIsAbsentFromTheResponseAndEveryShellCallbackRunsOnce(): void
    {
        $this->create('header', 'Elemacy Discard header');
        $this->create('footer', 'Elemacy Discard footer');

        $page = ThemePageRequest::render(self::THEME, get_permalink($this->post_id));
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Discard header'), 'The Elemacy header renders exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Discard footer'), 'The Elemacy footer renders exactly once.');
        foreach (['Generic native header', 'generic-native-nav', 'id="masthead"', 'Generic native footer', 'id="colophon"'] as $native) {
            self::assertStringNotContainsString($native, $html, "The theme's own chrome is not in the response at all.");
        }

        self::assertSame(1, substr_count($html, 'Native chrome fixture content.'), "The theme's content template is delivered unchanged.");
        self::assertSame(1, substr_count($html, '<main id="generic-native-main">'), "The theme's content template is delivered unchanged.");
        self::assertSame(1, substr_count($html, '<aside id="generic-native-sidebar">Generic native sidebar</aside>'), "The theme's sidebar is delivered unchanged.");
        self::assertSame(1, substr_count($html, "id='generic-fixture-style-css'"), "The theme's enqueued stylesheet survives.");
        self::assertSame(1, substr_count($html, "id=\"generic-fixture-script-js\""), "The theme's enqueued footer script survives.");

        // The discarded header.php and footer.php call wp_head(), wp_body_open()
        // and wp_footer() again; none of those calls may re-run a callback.
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        self::assertSame(1, substr_count($html, '<meta name="elemacy-third-party-head">'), 'The third-party head output is present once.');
        self::assertSame(1, substr_count($html, '<!-- elemacy-third-party-body-open -->'), 'The third-party body-open output is present once.');
        self::assertSame(1, substr_count($html, '<!-- elemacy-third-party-footer -->'), 'The third-party footer output is present once.');

        self::assertStringNotContainsString('id="wpadminbar"', $html, 'A signed-out visitor gets no admin bar.');
        self::assertStringNotContainsString('elemacy-force-stretched-header', $html, 'No stretch wrapper or suppression stylesheet remains.');
        $this->assertSingleDocumentShell($html);
    }

    public function testASignedInAdministratorGetsExactlyOneAdminBar(): void
    {
        $this->create('header', 'Elemacy Admin bar header');
        $this->create('footer', 'Elemacy Admin bar footer');

        $html = ThemePageRequest::render(self::THEME, get_permalink($this->post_id), 'admin')['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Admin bar header'), 'The Elemacy header renders exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'id="wpadminbar"'), 'The admin bar renders exactly once.');
        self::assertSame(1, substr_count($html, "id='admin-bar-css'"), 'The admin bar keeps its stylesheet.');
        self::assertSame(1, substr_count($html, 'id="admin-bar-js"'), 'The admin bar keeps its script.');
        $this->assertSingleDocumentShell($html);
    }

    public function testReplacingOnlyTheHeaderLeavesTheThemeFooterUnchanged(): void
    {
        $this->create('header', 'Elemacy Header-only header');

        $page = ThemePageRequest::render(self::THEME, get_permalink($this->post_id));
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Header-only header'), 'The Elemacy header renders exactly once: ' . $html);
        self::assertStringNotContainsString('Generic native header', $html, "The theme's header is replaced.");
        self::assertSame(1, substr_count($html, '<footer id="colophon" class="site-footer">Generic native footer</footer>'), "The theme's own footer renders normally.");
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    public function testReplacingOnlyTheFooterLeavesTheThemeHeaderUnchanged(): void
    {
        $this->create('footer', 'Elemacy Footer-only footer');

        $page = ThemePageRequest::render(self::THEME, get_permalink($this->post_id));
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Footer-only footer'), 'The Elemacy footer renders exactly once: ' . $html);
        self::assertStringNotContainsString('Generic native footer', $html, "The theme's footer is replaced.");
        self::assertSame(1, substr_count($html, '<header id="masthead" class="site-header"><nav id="generic-native-nav">Generic native header</nav></header>'), "The theme's own header renders normally.");
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    public function testTheDeprecatedThemeCompatStubsNeverReachTheResponse(): void
    {
        $url = get_permalink($this->post_id);

        // Control: with nothing configured, this theme really is served by
        // WordPress's deprecated wp-includes/theme-compat header and footer.
        $native = ThemePageRequest::render('theme-compat-stub', $url)['html'];
        self::assertStringContainsString('<div id="header" role="banner">', $native, 'Sanity: the fixture theme must resolve the theme-compat header: ' . $native);
        self::assertStringContainsString('<div id="footer" role="contentinfo">', $native, 'Sanity: the fixture theme must resolve the theme-compat footer.');

        $this->create('header', 'Elemacy Compat header');
        $this->create('footer', 'Elemacy Compat footer');

        $page = ThemePageRequest::render('theme-compat-stub', $url);
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Compat header'), 'The Elemacy header renders exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Compat footer'), 'The Elemacy footer renders exactly once.');
        foreach (['id="header"', 'id="headerimg"', 'id="footer"', '<hr', 'proudly powered by'] as $stub) {
            self::assertStringNotContainsString($stub, $html, 'None of the theme-compat stub markup, divider included, reaches the response.');
        }
        self::assertSame(1, substr_count($html, 'Native chrome fixture content.'), 'The content still renders.');
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    public function testOptingOutOfHeaderSuppressionKeepsTheThemeHeaderAndStillReplacesTheFooter(): void
    {
        $this->create('header', 'Elemacy Opt-out header');
        $this->create('footer', 'Elemacy Opt-out footer');

        $page = ThemePageRequest::render(self::THEME, get_permalink($this->post_id), 'guest', ['header']);
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Generic native header'), "The theme's own header renders: " . $html);
        self::assertStringNotContainsString('Elemacy Opt-out header', $html, 'The Elemacy header does not render on the opted-out side.');
        self::assertSame(1, substr_count($html, 'Elemacy Opt-out footer'), 'The footer is still replaced.');
        self::assertStringNotContainsString('Generic native footer', $html, "The theme's footer is still replaced.");
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    public function testALocationTemplateOnAThemeWithARealHeaderFileRendersTheHeaderOnce(): void
    {
        $this->create('header', 'Elemacy Wrapper header');
        $this->create('single', 'Elemacy Wrapper single');

        $html = ThemePageRequest::render('theme-location-wrapper', get_permalink($this->post_id))['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Wrapper single'), 'The location template renders exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Wrapper header'), 'The header renders exactly once.');
        self::assertStringNotContainsString('Location wrapper native header', $html, "The theme's header.php is replaced.");
        self::assertSame(1, substr_count($html, 'Location wrapper native footer'), "The theme's footer still renders: no footer template is configured.");
        $this->assertSingleDocumentShell($html);
    }

    public function testALocationTemplateOnAThemeWithNoHeaderFileRendersTheHeaderOnce(): void
    {
        $this->create('header', 'Elemacy Shell header');
        $this->create('single', 'Elemacy Shell single');

        $html = ThemePageRequest::render('theme-compat-stub', get_permalink($this->post_id))['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Shell single'), 'The location template renders exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Shell header'), 'The header renders exactly once.');
        self::assertStringNotContainsString('id="header"', $html, 'The wrapper owns the shell: the theme-compat stub is never loaded.');
        $this->assertSingleDocumentShell($html);
    }

    /**
     * Real classic themes, installed with setup.py --theme, with the markers
     * of their own header, footer and content. Each has no dedicated adapter,
     * so it is served through the generic path.
     *
     * @return array<string, array{string, array{header: string, footer: string, style: string}}>
     */
    public function realClassicThemes(): array
    {
        return [
            'twentytwentyone' => ['twentytwentyone', [
                'header' => '<header id="masthead"',
                'footer' => '<footer id="colophon"',
                'style' => 'twenty-twenty-one-style-css',
            ]],
        ];
    }

    private function assertRealThemeInstalled(string $theme): void
    {
        if (!wp_get_theme($theme)->exists()) {
            self::markTestSkipped("Real theme '$theme' is not installed; provision with setup.py --theme.");
        }
    }

    /** @dataProvider realClassicThemes */
    public function testARealClassicThemeHasBothSidesOfItsChromeReplaced(string $theme, array $markers): void
    {
        $this->assertRealThemeInstalled($theme);
        $url = get_permalink($this->post_id);

        $native = ThemePageRequest::render($theme, $url)['html'];
        self::assertSame(1, substr_count($native, $markers['header']), "Sanity: the theme's own header renders when nothing is configured: " . $native);
        self::assertSame(1, substr_count($native, $markers['footer']), "Sanity: the theme's own footer renders when nothing is configured.");

        $this->create('header', 'Elemacy Real header');
        $this->create('footer', 'Elemacy Real footer');
        $page = ThemePageRequest::render($theme, $url);
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Real header'), 'The Elemacy header renders exactly once: ' . $html);
        self::assertSame(1, substr_count($html, 'Elemacy Real footer'), 'The Elemacy footer renders exactly once.');
        self::assertStringNotContainsString($markers['header'], $html, "The theme's own header is not in the response.");
        self::assertStringNotContainsString($markers['footer'], $html, "The theme's own footer is not in the response.");
        self::assertSame(1, substr_count($html, 'Native chrome fixture content.'), "The theme's content template still renders the post.");
        self::assertSame(1, substr_count($html, '<article id="post-' . $this->post_id . '"'), "The theme's content template still renders its own article markup.");
        self::assertSame(1, substr_count($html, $markers['style']), "The theme's own stylesheet is still enqueued.");
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    /** @dataProvider realClassicThemes */
    public function testARealClassicThemeKeepsItsOwnFooterWhenOnlyTheHeaderIsReplaced(string $theme, array $markers): void
    {
        $this->assertRealThemeInstalled($theme);
        $this->create('header', 'Elemacy Real header-only');

        $page = ThemePageRequest::render($theme, get_permalink($this->post_id));
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Real header-only'), 'The Elemacy header renders exactly once: ' . $html);
        self::assertStringNotContainsString($markers['header'], $html, "The theme's own header is replaced.");
        self::assertSame(1, substr_count($html, $markers['footer']), "The theme's own footer renders.");
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    /** @dataProvider realClassicThemes */
    public function testARealClassicThemeKeepsItsOwnHeaderWhenOnlyTheFooterIsReplaced(string $theme, array $markers): void
    {
        $this->assertRealThemeInstalled($theme);
        $this->create('footer', 'Elemacy Real footer-only');

        $page = ThemePageRequest::render($theme, get_permalink($this->post_id));
        $html = $page['html'];

        self::assertSame(1, substr_count($html, 'Elemacy Real footer-only'), 'The Elemacy footer renders exactly once: ' . $html);
        self::assertStringNotContainsString($markers['footer'], $html, "The theme's own footer is replaced.");
        self::assertSame(1, substr_count($html, $markers['header']), "The theme's own header renders.");
        self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], 'Each third-party shell callback runs exactly once.');
        $this->assertSingleDocumentShell($html);
    }

    /** @dataProvider realClassicThemes */
    public function testARealClassicThemeKeepsItsHeaderWhenHeaderSuppressionIsOptedOutOf(string $theme, array $markers): void
    {
        $this->assertRealThemeInstalled($theme);
        $this->create('header', 'Elemacy Real opt-out header');
        $this->create('footer', 'Elemacy Real opt-out footer');

        $html = ThemePageRequest::render($theme, get_permalink($this->post_id), 'guest', ['header'])['html'];

        self::assertSame(1, substr_count($html, $markers['header']), "The theme's own header renders: " . $html);
        self::assertStringNotContainsString('Elemacy Real opt-out header', $html, 'The Elemacy header does not render on the opted-out side.');
        self::assertSame(1, substr_count($html, 'Elemacy Real opt-out footer'), 'The footer is still replaced.');
        self::assertStringNotContainsString($markers['footer'], $html, "The theme's own footer is still replaced.");
        $this->assertSingleDocumentShell($html);
    }

    private function assertSingleDocumentShell(string $html): void
    {
        self::assertSame(1, preg_match_all('/<html[ >]/', $html), 'Exactly one <html> tag.');
        self::assertSame(1, substr_count($html, '<head>'), 'Exactly one <head> tag.');
        self::assertSame(1, substr_count($html, '</head>'), 'Exactly one </head> tag.');
        self::assertSame(1, preg_match_all('/<body[ >]/', $html), 'Exactly one <body> tag.');
        self::assertSame(1, substr_count($html, '</body>'), 'Exactly one </body> tag.');
        self::assertSame(1, substr_count($html, '</html>'), 'Exactly one </html> tag.');
    }
}
