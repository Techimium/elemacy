<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

/**
 * Header-only, Footer-only and both-sides replacement, rendered against real
 * themes as a signed-out visitor, each page in its own PHP process.
 *
 * A theme with a dedicated adapter runs twice, once as shipped and once
 * forced onto the generic adapter, so the two paths are compared on the same
 * markup. A page passes when the Elemacy template appears once for each
 * configured side, the native chrome is gone from a replaced side and still
 * there on the other, the post content and the document shell are exactly as
 * on the unmodified theme, and the markup balances as it does there.
 *
 * Themes are installed with setup.py --theme (CI installs the pinned set);
 * one that is not installed skips with a message. Astra is not in the set: the
 * harness keeps the slug `astra` for its own fixture theme.
 */
final class RealThemeChromeTest extends IntegrationTestCase
{
    private const HEADER = 'Elemacy real-theme header';
    private const FOOTER = 'Elemacy real-theme footer';
    private const CONTENT = 'Real theme chrome content.';

    /**
     * 'dedicated' themes are also run on the generic adapter. 'block' themes
     * are served by the block adapter only. `empty_shell` marks a theme that
     * keeps an empty <header>/<footer> element after its content is removed;
     * `inside_wrapper` one that renders the Elemacy template inside its own
     * header element. Neither can be judged by the element tag alone.
     *
     * @return array<string, array{string, bool, array<string, bool>}>
     */
    public function pages(): array
    {
        $dedicated = [
            'hello-elementor' => [],
            'generatepress' => [],
            'kadence' => [],
            'neve' => ['inside_wrapper' => true],
            'oceanwp' => [],
            'blocksy' => [],
            'storefront' => ['empty_shell' => true],
        ];
        $generic_only = ['twentytwentyone' => [], 'twentytwenty' => [], 'hestia' => [], 'sydney' => [], 'zakra' => []];
        $block = ['twentytwentyfive' => []];

        $pages = [];
        foreach ($dedicated as $theme => $flags) {
            $pages["$theme (dedicated)"] = [$theme, false, $flags];
            $pages["$theme (generic)"] = [$theme, true, $flags];
        }
        foreach ($generic_only as $theme => $flags) {
            $pages["$theme (generic)"] = [$theme, true, $flags];
        }
        foreach ($block as $theme => $flags) {
            $pages["$theme (block)"] = [$theme, false, $flags];
        }

        return $pages;
    }

    /** @dataProvider pages */
    public function testEachCombinationOfSidesReplacesOnlyWhatIsConfigured(string $theme, bool $generic, array $flags): void
    {
        if (!wp_get_theme($theme)->exists()) {
            self::markTestSkipped("Real theme '$theme' is not installed; provision with setup.py --theme.");
        }

        $post_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Real theme chrome post', 'post_content' => self::CONTENT,
        ], true);
        self::assertIsInt($post_id);
        $url = get_permalink($post_id);

        try {
            $base = ThemePageRequest::render($theme, $url, 'guest', [], $generic);
            $native_header = $this->first_tag($base['html'], 'header');
            $native_footer = $this->last_tag($base['html'], 'footer');
            self::assertNotSame('', $native_header, "Sanity: $theme renders a <header> when nothing is configured.");
            self::assertNotSame('', $native_footer, "Sanity: $theme renders a <footer> when nothing is configured.");
            $expected = $this->facts($base['html']);
            self::assertGreaterThan(0, $expected['content'], 'Sanity: the post content renders on the unmodified theme.');

            foreach ([[true, false], [false, true], [true, true]] as [$header, $footer]) {
                $label = ($header ? 'header' : '') . ($header && $footer ? '+' : '') . ($footer ? 'footer' : '');
                $ids = [];
                if ($header) {
                    $ids[] = $this->documents->create(self::HEADER, 'header', 'publish', LibraryDocument::heading(self::HEADER));
                }
                if ($footer) {
                    $ids[] = $this->documents->create(self::FOOTER, 'footer', 'publish', LibraryDocument::heading(self::FOOTER));
                }

                try {
                    $page = ThemePageRequest::render($theme, $url, 'guest', [], $generic);
                } finally {
                    foreach ($ids as $id) {
                        wp_delete_post($id, true);
                    }
                }

                $html = $page['html'];
                $where = "$theme ($label, " . ($generic ? 'generic' : 'as shipped') . ')';
                self::assertSame($header ? 1 : 0, substr_count($html, self::HEADER), "$where: the Elemacy header renders once if configured, never otherwise.");
                self::assertSame($footer ? 1 : 0, substr_count($html, self::FOOTER), "$where: the Elemacy footer renders once if configured, never otherwise.");
                if (empty($flags['empty_shell']) && empty($flags['inside_wrapper'])) {
                    self::assertSame($header ? 0 : 1, substr_count($html, $native_header), "$where: the native header is replaced only when a header is configured.");
                    self::assertSame($footer ? 0 : 1, substr_count($html, $native_footer), "$where: the native footer is replaced only when a footer is configured.");
                }
                $facts = $this->facts($html);
                self::assertSame($expected['content'], $facts['content'], "$where: the content renders exactly as on the unmodified theme.");
                self::assertSame($expected['shell'], $facts['shell'], "$where: one <html>, one <head>, one <body>.");
                self::assertSame($expected['divs'], $facts['divs'], "$where: <div> tags balance as on the unmodified theme.");
                self::assertSame(['wp_head' => 1, 'wp_body_open' => 1, 'wp_footer' => 1], $page['calls'], "$where: each shell callback runs once.");
            }
        } finally {
            wp_delete_post($post_id, true);
        }
    }

    /** @return array<string, array{string, string, string}> */
    public function chromeHooks(): array
    {
        return [
            'neve header' => ['neve', 'neve_do_header', 'header'],
            'neve footer' => ['neve', 'neve_do_footer', 'footer'],
            'storefront header' => ['storefront', 'storefront_header', 'header'],
            'storefront footer' => ['storefront', 'storefront_footer', 'footer'],
        ];
    }

    /**
     * An adapter removes the theme's callbacks on its chrome hook, not every
     * callback: one a plugin attached to the same hook still runs.
     *
     * @dataProvider chromeHooks
     */
    public function testAPluginCallbackOnTheThemesChromeHookSurvivesItsReplacement(string $theme, string $hook, string $side): void
    {
        if (!wp_get_theme($theme)->exists()) {
            self::markTestSkipped("Real theme '$theme' is not installed; provision with setup.py --theme.");
        }
        $post_id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Plugin callback post', 'post_content' => self::CONTENT], true);
        $header = $this->documents->create(self::HEADER, 'header', 'publish', LibraryDocument::heading(self::HEADER));
        $footer = $this->documents->create(self::FOOTER, 'footer', 'publish', LibraryDocument::heading(self::FOOTER));

        try {
            $html = ThemePageRequest::render($theme, get_permalink($post_id), 'guest', [], false, [$hook => '<!--plugin-callback-ran-->'])['html'];
        } finally {
            wp_delete_post($header, true);
            wp_delete_post($footer, true);
            wp_delete_post($post_id, true);
        }

        self::assertSame(1, substr_count($html, '<!--plugin-callback-ran-->'), "A plugin callback on $hook still runs once.");
        self::assertSame(1, substr_count($html, 'header' === $side ? self::HEADER : self::FOOTER), 'The Elemacy template for that side renders once.');
    }

    /** @return array{content: int, shell: string, divs: int} */
    private function facts(string $html): array
    {
        return [
            'content' => substr_count($html, self::CONTENT),
            'shell' => preg_match_all('/<html[ >]/', $html) . preg_match_all('/<head[ >]/', $html) . preg_match_all('/<body[ >]/', $html),
            'divs' => substr_count($html, '<div') - substr_count($html, '</div>'),
        ];
    }

    private function first_tag(string $html, string $tag): string
    {
        return preg_match('#<' . $tag . '[^>]*>#', $html, $m) ? $m[0] : '';
    }

    private function last_tag(string $html, string $tag): string
    {
        return preg_match_all('#<' . $tag . '[^>]*>#', $html, $m) ? end($m[0]) : '';
    }
}
