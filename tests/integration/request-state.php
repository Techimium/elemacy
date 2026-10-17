<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Rendering\LoopItemStyles;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elemacy\Tests\Integration\Fixtures\ThemeLifecycleHarness;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemeLifecycleHarness.php';

/**
 * Request-scoped render caches end with their page and never cross sites.
 * This PHPUnit process is itself a long-running process that renders many
 * pages, so every scenario here runs through the production boundary
 * (Core\RequestState, flushed on `wp`) with no reflection reset in between.
 * The fragment marker comes from fixtures/mu-plugins/template-rendering-preparation.php:
 * a document carrying `_elemacy_fixture_item_marker` prints the current post id.
 */
final class RequestStateTest extends IntegrationTestCase
{
    private array $owned = [];

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function post(string $title): int
    {
        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $title . ' body.'], true);
        self::assertIsInt($id);
        $this->owned[] = $id;

        return $id;
    }

    private static function marker(int $post_id): string
    {
        return '<span data-elemacy-fixture-current-post>' . $post_id . '</span>';
    }

    /** Output of rendering $post_ids through one loop template, as one placement. */
    private static function render_loop(int $template, array $post_ids): string
    {
        $renderer = new LoopRenderer();
        ob_start();
        try {
            foreach ($post_ids as $occurrence => $post_id) {
                self::assertTrue($renderer->render_item($template, new PostLoopItem(get_post($post_id)), 'request-state', 'posts', $occurrence));
            }

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function testASecondPageInTheSameProcessPreparesItsOwnForeignDocuments(): void
    {
        $header = $this->documents->create('Request state header', 'header', 'publish', LibraryDocument::heading('Request state header'));
        update_post_meta($header, '_elemacy_fixture_item_marker', '1');
        $first = $this->post('Request state page one');
        $second = $this->post('Request state page two');

        $harness = new ThemeLifecycleHarness();
        try {
            $harness->switch_theme('twentytwentyfive');
            $page_one = $harness->render(get_permalink($first));
            $page_two = $harness->render(get_permalink($second));
        } finally {
            $harness->restore();
        }

        self::assertSame(1, substr_count($page_one, 'Request state header'), 'Page one renders the header: ' . $page_one);
        self::assertStringContainsString(self::marker($first), $page_one, 'Page one\'s header was prepared for page one.');
        self::assertSame(1, substr_count($page_two, 'Request state header'), 'Page two renders the header too, not skipped as already rendered: ' . $page_two);
        self::assertStringContainsString(self::marker($second), $page_two, 'Page two\'s header was prepared for page two.');
        self::assertStringNotContainsString(self::marker($first), $page_two, 'Page two is never served page one\'s prepared header.');
    }

    public function testASecondPageInTheSameProcessReEmitsALoopTemplatesBaseStyles(): void
    {
        $template = $this->documents->create('Request state loop item', 'loop', 'publish');
        $item = $this->post('Request state loop post');
        $request = new RequestSimulator();

        try {
            $request->visit(get_permalink($this->post('Request state loop page one')));
            $page_one = self::render_loop($template, [$item, $item]);

            $request->visit(get_permalink($this->post('Request state loop page two')));
            $page_two = self::render_loop($template, [$item]);
        } finally {
            $request->restore();
        }

        self::assertSame(1, substr_count($page_one, 'id="elemacy-loop-base-' . $template . '"'), 'Within one page the base styles are emitted once: ' . $page_one);
        self::assertSame(1, substr_count($page_two, 'id="elemacy-loop-base-' . $template . '"'), 'The next page emits them again: ' . $page_two);
    }

    public function testWithinOneRequestAForeignDocumentIsPreparedOnceAndReused(): void
    {
        $document = $this->documents->create('Request state reuse', 'header', 'publish', LibraryDocument::heading('Request state reuse'));
        update_post_meta($document, '_elemacy_fixture_item_marker', '1');
        $host = $this->post('Request state reuse host');
        $other = $this->post('Request state reuse other');
        $request = new RequestSimulator();

        try {
            $request->visit(get_permalink($host));
            $renderer = new TemplateRenderer();
            $prepared = $renderer->prepare($document, 'theme-builder', 'header');

            // Same request, different ambient post: still the one fragment.
            $GLOBALS['post'] = get_post($other);
            setup_postdata($GLOBALS['post']);
            $again = $renderer->prepare($document, 'theme-builder', 'header');
            wp_reset_postdata();
        } finally {
            $request->restore();
        }

        self::assertStringContainsString(self::marker($host), $prepared, 'The fragment was prepared for the request\'s own post.');
        self::assertSame($prepared, $again, 'A second preparation in the same request reuses the fragment rather than rendering again.');
    }

    /**
     * Simulates the acting-site change by setting $blog_id, which is what
     * get_current_blog_id() reads and what switch_to_blog() sets, without a
     * second site's tables and without firing switch_blog: the keys alone must
     * keep another site's entries out.
     */
    public function testNoFragmentStyleMarkerOrResolutionIsReusedOnAnotherSite(): void
    {
        $document = $this->documents->create('Request state site fragment', 'header', 'publish', LibraryDocument::heading('Request state site fragment'));
        $template = $this->documents->create('Request state site loop', 'loop', 'publish');
        $item = $this->post('Request state site post');
        $request = new RequestSimulator();
        $original_blog = $GLOBALS['blog_id'];

        try {
            $request->visit(get_permalink($item));
            $renderer = new TemplateRenderer();
            $resolver = TemplateResolver::instance();
            $memo = new \ReflectionProperty(TemplateResolver::class, 'resolved');
            $memo->setAccessible(true);

            $fragment = $renderer->prepare($document, 'theme-builder', 'header');
            self::render_loop($template, [$item]);
            self::assertSame($document, $resolver->resolve('header'), 'Sanity: the header resolves on this site.');
            // Poison this site's memo so a cross-site read would be visible.
            $memo->setValue(null, [get_current_blog_id() . ':header' => -1] + $memo->getValue());
            self::assertSame(-1, $resolver->resolve('header'), 'Sanity: the poisoned memo is what this site reads.');

            $GLOBALS['blog_id'] = 999999;
            self::assertSame(999999, get_current_blog_id(), 'Sanity: the acting site changed.');

            self::assertNull($renderer->get_prepared($document, 'theme-builder', 'header'), 'Another site is never served this site\'s prepared fragment.');
            self::assertSame(1, substr_count(self::render_loop($template, [$item]), 'id="elemacy-loop-base-' . $template . '"'), 'Another site does not see this site\'s base-style marker.');
            self::assertNotSame(-1, $resolver->resolve('header'), 'Another site does not read this site\'s resolution memo.');

            $GLOBALS['blog_id'] = $original_blog;

            self::assertSame($fragment, $renderer->get_prepared($document, 'theme-builder', 'header'), 'Back on the first site, its own fragment is still there.');
            self::assertSame(0, substr_count(self::render_loop($template, [$item]), 'id="elemacy-loop-base-' . $template . '"'), 'Back on the first site, its base styles are still marked as emitted.');
        } finally {
            $GLOBALS['blog_id'] = $original_blog;
            TemplateResolver::reset();
            $request->restore();
        }
    }
}
