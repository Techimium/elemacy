<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Elementor\Rendering\ClassicCssRenderer;
use Elemacy\Loop\Rendering\LoopItemStyles;
use Elementor\Core\Files\CSS\Post as Post_CSS;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Task 8.9 phase close-out: confirms two specific, previously-untested real
 * behaviors ClassicCssRenderer::render_base()/LoopItemStyles::print_base_css()
 * already implement — cached CSS file reuse, and falling back to inline CSS
 * when Elementor's own meta says a cached file exists but the file itself
 * is actually missing from disk (a stale cache after a filesystem/migration
 * issue, design.md D2) — against a REAL Elementor-generated CSS file, not a
 * mock. Neither scenario had a real-environment test before this task; the
 * fast-harness suite only covers Post_CSS being entirely unavailable, not
 * this specific stale-cache case.
 */
final class LoopBaseCssFileFallbackTest extends IntegrationTestCase
{
    public function testASecondRenderReusesTheAlreadyCachedFileInsteadOfRegeneratingIt(): void
    {
        $template_id = $this->documents->create('Loop base CSS cache reuse', 'loop', 'publish', self::styledHeading('#ff0000'));

        $renderer = new ClassicCssRenderer();
        $first = $renderer->render_base($template_id);
        self::assertSame(ClassicCssRenderer::DELIVERY_LINK, $first['type'], 'A real widget with a color control must produce a real cached file, not inline/none.');
        self::assertNotEmpty($first['content']);

        $css_file = Post_CSS::create($template_id);
        $path = $css_file->get_path();
        self::assertFileExists($path, 'Elementor must have actually written the cached file to disk.');
        $mtime_before = filemtime($path);

        // A second render, e.g. a later request in a real ajax page or a
        // sibling widget on the same template, must reuse the existing file
        // rather than regenerating it — same URL, file left untouched.
        $second = $renderer->render_base($template_id);
        self::assertSame(ClassicCssRenderer::DELIVERY_LINK, $second['type']);
        self::assertSame($first['content'], $second['content'], 'The same cached file URL must be reused, not a freshly regenerated one.');
        clearstatcache(true, $path);
        self::assertSame($mtime_before, filemtime($path), 'The cached file itself must not be rewritten on a second, redundant render.');
    }

    public function testMissingCachedFileFallsBackToRealInlineCssInsteadOfABrokenLink(): void
    {
        $template_id = $this->documents->create('Loop base CSS missing file fallback', 'loop', 'publish', self::styledHeading('#00ff00'));

        $renderer = new ClassicCssRenderer();
        $primed = $renderer->render_base($template_id);
        self::assertSame(ClassicCssRenderer::DELIVERY_LINK, $primed['type'], 'Priming render must produce a real cached file.');

        $css_file = Post_CSS::create($template_id);
        $path = $css_file->get_path();
        self::assertFileExists($path);
        // Simulate the exact scenario design.md D2 names: Elementor's own
        // meta still says CSS_STATUS_FILE (nothing here changes that), but
        // the file itself is gone — an uploads/ directory that didn't
        // survive a migration, a manual cleanup, a CDN purge gone wrong.
        unlink($path);
        clearstatcache(true, $path);

        $fallback = $renderer->render_base($template_id);
        self::assertSame(ClassicCssRenderer::DELIVERY_INLINE, $fallback['type'], 'A missing cached file must fall back to inline delivery, never a link to a file that no longer exists.');
        self::assertNotEmpty($fallback['content'], 'The inline fallback must carry the real generated CSS, not an empty string.');
        self::assertStringContainsString('color', $fallback['content'], 'The real widget\'s own color rule must be present in the inline fallback: ' . $fallback['content']);

        // LoopItemStyles::print_base_css() — what the real render loop
        // actually calls — must produce the same safe outcome: a real
        // <style> tag with the fallback content, never a <link> to the
        // missing file.
        $styles = new LoopItemStyles();
        ob_start();
        try {
            $styles->print_base_css($template_id);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertStringNotContainsString('<link', $output, 'print_base_css() must not emit a <link> to a file that no longer exists: ' . $output);
        self::assertStringContainsString('<style', $output, 'print_base_css() must fall back to an inline <style> tag: ' . $output);
    }

    private static function styledHeading(string $color): array
    {
        return [[
            'id' => 'eac3001', 'elType' => 'container', 'settings' => [],
            'elements' => [[
                'id' => 'eac3002', 'elType' => 'widget', 'widgetType' => 'heading',
                'settings' => ['title' => 'Styled heading', 'header_size' => 'h2', 'title_color' => $color],
                'elements' => [],
            ]],
        ]];
    }
}
