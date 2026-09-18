<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Rendering\AtomicStylesRenderer;
use Elemacy\Core\Rendering\ClassicCssRenderer;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopItems\PostLoopItem;
use Elemacy\Loop\LoopItemStyles;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\Loop\Rendering\TemplateRecursionGuard;
use RuntimeException;
use WP_Post;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * design.md D8: LoopRenderer owns rendering exactly one loop item's
 * fragment — the per-item body Grid/Carousel/AJAX each used to duplicate
 * independently (task 8.5, wired into the live widgets/controller in task
 * 8.6). These exercise it directly against real Elementor templates.
 */
final class LoopRendererTest extends IntegrationTestCase
{
    public function testStyleIsPrintedBeforeMarkupForEachItem(): void
    {
        $template_id = $this->documents->create('Loop renderer ordering', 'loop', 'publish');
        $post_id = self::insertPost();

        ob_start();
        try {
            $ok = (new LoopRenderer())->render_item($template_id, new PostLoopItem(get_post($post_id)), 'placement', 'posts');
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertTrue($ok);
        $style_position = strpos($output, 'elemacy-loop-base-' . $template_id);
        $markup_position = strpos($output, 'elemacy-loop-item elemacy-loop-item-');
        self::assertNotFalse($style_position, 'Base CSS (style or link) must be present: ' . $output);
        self::assertNotFalse($markup_position, 'The item wrapper div must be present: ' . $output);
        self::assertLessThan($markup_position, $style_position, 'Base CSS must be printed before the item\'s own markup: ' . $output);

        wp_delete_post($post_id, true);
    }

    public function testRenderItemProducesACompleteFragmentWithBalancedMarkup(): void
    {
        $template_id = $this->documents->create('Loop renderer fragment', 'loop', 'publish');
        $post_id = self::insertPost();

        ob_start();
        try {
            $ok = (new LoopRenderer())->render_item($template_id, new PostLoopItem(get_post($post_id)), 'placement', 'posts');
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertTrue($ok);
        self::assertSame(1, substr_count($output, '<div class="elemacy-loop-item elemacy-loop-item-'), 'The item wrapper must open exactly once, with no stray duplicate: ' . $output);
        self::assertStringEndsWith('</div>', rtrim($output), 'The fragment must end with the item wrapper\'s own closing tag, not truncate mid-content: ' . $output);
        self::assertStringContainsString('Loop renderer fragment', $output, 'The template\'s own real Elementor content must be present inside the fragment: ' . $output);

        wp_delete_post($post_id, true);
    }

    public function testBaseCssIsEmittedExactlyOnceAcrossMultipleItemsForOnePlacement(): void
    {
        $template_id = $this->documents->create('Loop renderer base css once', 'loop', 'publish');
        $post_ids = [self::insertPost(), self::insertPost(), self::insertPost()];

        $renderer = new LoopRenderer();
        ob_start();
        try {
            foreach ($post_ids as $occurrence => $post_id) {
                self::assertTrue($renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'placement', 'posts', $occurrence));
            }
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(
            1,
            substr_count($output, 'elemacy-loop-base-' . $template_id),
            'Base CSS for one template must be emitted exactly once across three separately-rendered items for the same placement, not once per item: ' . $output
        );
        self::assertSame(3, substr_count($output, 'elemacy-loop-item elemacy-loop-item-'), 'All three items must still each render their own markup: ' . $output);

        foreach ($post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
    }

    public function testMissingTemplateIsSkippedWithoutThrowingOrOutput(): void
    {
        $post_id = self::insertPost();

        ob_start();
        try {
            $ok = (new LoopRenderer())->render_item(999999999, new PostLoopItem(get_post($post_id)), 'placement', 'posts');
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertFalse($ok, 'A template ID that resolves to no real document must be skipped, not fatal.');
        self::assertSame('', $output, 'A skipped item must produce no output at all.');
        self::assertNull(LoopContext::current(), 'Nothing should be left on the item stack.');

        wp_delete_post($post_id, true);
    }

    public function testActiveRecursiveTemplateCycleIsRefusedWithoutThrowing(): void
    {
        $template_id = $this->documents->create('Loop renderer recursion', 'loop', 'publish');
        $post_id = self::insertPost();

        // Simulates this exact template already being mid-render one level
        // up (e.g. a loop item template that itself contains another Loop
        // Grid on the same template) without needing genuinely nested
        // Elementor rendering to prove it.
        TemplateRecursionGuard::enter($template_id);
        ob_start();
        try {
            $ok = (new LoopRenderer())->render_item($template_id, new PostLoopItem(get_post($post_id)), 'placement', 'posts');
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
            TemplateRecursionGuard::exit();
        }

        self::assertFalse($ok, 'An active recursive cycle must be refused, not throw out to the caller.');
        self::assertSame('', $output, 'A refused render must produce no output — not even base CSS.');
        self::assertSame(0, TemplateRecursionGuard::depth(), 'The guard must not be left in a corrupted state by a refused entry.');

        wp_delete_post($post_id, true);
    }

    public function testSequentialReuseOfTheSameTemplateAcrossNonNestedItemsSucceeds(): void
    {
        $template_id = $this->documents->create('Loop renderer sequential reuse', 'loop', 'publish');
        $post_ids = [self::insertPost(), self::insertPost()];
        $renderer = new LoopRenderer();

        ob_start();
        try {
            $first = $renderer->render_item($template_id, new PostLoopItem(get_post($post_ids[0])), 'placement', 'posts', 0);
            $second = $renderer->render_item($template_id, new PostLoopItem(get_post($post_ids[1])), 'placement', 'posts', 1);
        } finally {
            ob_end_clean();
        }

        self::assertTrue($first);
        self::assertTrue($second, 'Rendering the same template again for a second, non-nested item must succeed — only an ACTIVE cycle is refused.');
        self::assertSame(0, TemplateRecursionGuard::depth());

        foreach ($post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
    }

    public function testRenderFailureAfterASuccessfulEnterDiscardsPartialOutputAndStillExits(): void
    {
        $template_id = $this->documents->create('Loop renderer render failure', 'loop', 'publish');
        $post_id = self::insertPost();
        $item = new IdentityFailsOnSecondCallLoopItem($post_id);

        ob_start();
        try {
            $ok = (new LoopRenderer())->render_item($template_id, $item, 'placement', 'posts');
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertFalse($ok, 'A failure after a successful enter() must make render_item() report failure, not throw.');
        self::assertStringNotContainsString('elemacy-loop-item elemacy-loop-item-', $output, 'No partial item markup must leak into the output when rendering that item fails: ' . $output);
        self::assertTrue($item->exited, 'exit() must still run for an item whose enter() succeeded, even though rendering it failed afterward.');
        self::assertNull(LoopContext::current(), 'Nothing should be left on the item stack after a render failure.');

        wp_delete_post($post_id, true);
    }

    public function testSamePostRenderedByTwoWidgetsGetsIndependentDynamicCssScopes(): void
    {
        $template_id = $this->documents->create('Loop renderer render identity', 'loop', 'publish');
        $post_id = self::insertPost();
        $recorder = new RecordingClassicCssRenderer();
        $renderer = new LoopRenderer(new LoopItemStyles($recorder, new RecordingAtomicStylesRenderer()));

        ob_start();
        try {
            $renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'widget-A', 'posts');
            $renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'widget-B', 'posts');
        } finally {
            ob_end_clean();
        }

        self::assertCount(2, $recorder->dynamic_calls);
        [$first_template, $first_item_identity, $first_selector] = $recorder->dynamic_calls[0];
        [$second_template, $second_item_identity, $second_selector] = $recorder->dynamic_calls[1];

        self::assertSame((string) $post_id, $first_item_identity, 'The item\'s own domain identity must remain unchanged and unaffected by render scoping.');
        self::assertSame($first_item_identity, $second_item_identity, 'Both widgets render the exact same domain item.');
        self::assertNotSame($first_selector, $second_selector, 'The same post rendered by two different widget placements must never share a dynamic-CSS scope.');

        wp_delete_post($post_id, true);
    }

    public function testSameTemplateAndPlacementInTwoDifferentAjaxBatchesGetsIndependentDynamicCssScopes(): void
    {
        $template_id = $this->documents->create('Loop renderer render identity batches', 'loop', 'publish');
        $post_id = self::insertPost();
        $recorder = new RecordingClassicCssRenderer();
        $renderer = new LoopRenderer(new LoopItemStyles($recorder, new RecordingAtomicStylesRenderer()));

        ob_start();
        try {
            $renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'widget-A', 'posts', 0, 'page-1');
            $renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'widget-A', 'posts', 0, 'page-2');
        } finally {
            ob_end_clean();
        }

        [, , $page_1_selector] = $recorder->dynamic_calls[0];
        [, , $page_2_selector] = $recorder->dynamic_calls[1];
        self::assertNotSame($page_1_selector, $page_2_selector, 'The same template/placement/item across two different AJAX pagination batches must not share a dynamic-CSS scope.');

        wp_delete_post($post_id, true);
    }

    public function testEqualNumericIdsFromDifferentSourcesGetIndependentDynamicCssScopes(): void
    {
        $template_id = $this->documents->create('Loop renderer render identity sources', 'loop', 'publish');
        $post_id = self::insertPost();
        $recorder = new RecordingClassicCssRenderer();
        $renderer = new LoopRenderer(new LoopItemStyles($recorder, new RecordingAtomicStylesRenderer()));
        // Not a real term/user item — a plain fixture item whose identity
        // happens to numerically equal $post_id, standing in for a term or
        // user that coincidentally shares that post's numeric ID.
        $coincidental_item = new FixtureIdentityLoopItem((string) $post_id);

        ob_start();
        try {
            $renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'widget-A', 'posts');
            $renderer->render_item($template_id, $coincidental_item, 'widget-A', 'terms');
        } finally {
            ob_end_clean();
        }

        [, $posts_item_identity, $posts_selector] = $recorder->dynamic_calls[0];
        [, $terms_item_identity, $terms_selector] = $recorder->dynamic_calls[1];
        self::assertSame($posts_item_identity, $terms_item_identity, 'Both items share the exact same raw numeric domain identity.');
        self::assertNotSame($posts_selector, $terms_selector, 'A post and a different source\'s item that happen to share a numeric ID must never share a dynamic-CSS scope.');

        wp_delete_post($post_id, true);
    }

    public function testRepeatedIdenticalAjaxInsertionOfTheSameItemProducesTheSameStableScope(): void
    {
        $template_id = $this->documents->create('Loop renderer render identity repeated', 'loop', 'publish');
        $post_id = self::insertPost();
        $recorder = new RecordingClassicCssRenderer();
        $renderer = new LoopRenderer(new LoopItemStyles($recorder, new RecordingAtomicStylesRenderer()));

        ob_start();
        try {
            // The exact same placement/template/source/occurrence/batch
            // requested twice (e.g. a page re-fetched after navigating away
            // and back) — must resolve to the identical scope each time,
            // not merely to two DIFFERENT-but-non-colliding ones.
            $renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'widget-A', 'posts', 0, 'page-1');
            $renderer->render_item($template_id, new PostLoopItem(get_post($post_id)), 'widget-A', 'posts', 0, 'page-1');
        } finally {
            ob_end_clean();
        }

        [, , $first_selector] = $recorder->dynamic_calls[0];
        [, , $second_selector] = $recorder->dynamic_calls[1];
        self::assertSame($first_selector, $second_selector, 'Identical render-identity inputs must deterministically resolve to the identical scope, not a fresh one each time.');

        wp_delete_post($post_id, true);
    }

    private static function insertPost(): int
    {
        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Fixture post', 'post_content' => 'Fixture content.'], true);
        self::assertNotInstanceOf(\WP_Error::class, $id);

        return (int) $id;
    }
}

/** A minimal, always-succeeding LoopItemInterface with a caller-chosen identity — stands in for a non-post source (a term/user) whose numeric ID happens to coincide with a real post's. */
final class FixtureIdentityLoopItem implements LoopItemInterface
{
    private string $identity;

    public function __construct(string $identity)
    {
        $this->identity = $identity;
    }

    public function get_identity(): string
    {
        return $this->identity;
    }

    /** @param string $key @return mixed */
    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
    }

    public function exit(): void
    {
    }
}

/** Records every render_dynamic() call's arguments instead of touching real Elementor dynamic-tag CSS generation. */
final class RecordingClassicCssRenderer extends ClassicCssRenderer
{
    /** @var array<int, array{0:int,1:string,2:string}> */
    public array $dynamic_calls = [];

    public function suppress_automatic_dynamic_css(int $post_id): void
    {
    }

    public function render_base(int $post_id): array
    {
        return ['type' => self::DELIVERY_NONE, 'content' => ''];
    }

    public function render_dynamic(int $template_id, string $item_identity, string $selector_prefix): string
    {
        $this->dynamic_calls[] = [$template_id, $item_identity, $selector_prefix];

        return '';
    }

    public function has_dynamic_settings(int $post_id): bool
    {
        return true;
    }
}

/** A real AtomicStylesRenderer subclass that never reports dynamic content, so only RecordingClassicCssRenderer's own calls need asserting on. */
final class RecordingAtomicStylesRenderer extends AtomicStylesRenderer
{
    public function render_base(int $post_id): string
    {
        return '';
    }

    public function render_dynamic(int $post_id, string $selector_prefix): string
    {
        return '';
    }

    public function has_dynamic_styles(int $post_id): bool
    {
        return false;
    }
}

/** enter() succeeds; get_identity() throws on its second call, forcing a failure inside render_item_markup() after a successful enter(). */
final class IdentityFailsOnSecondCallLoopItem implements LoopItemInterface
{
    private int $post_id;
    private int $calls = 0;
    public bool $exited = false;

    public function __construct(int $post_id)
    {
        $this->post_id = $post_id;
    }

    public function get_identity(): string
    {
        $this->calls++;

        if ($this->calls > 1) {
            throw new RuntimeException('Simulated failure resolving this item\'s identity mid-render.');
        }

        return (string) $this->post_id;
    }

    /** @param string $key @return mixed */
    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
    }

    public function exit(): void
    {
        $this->exited = true;
    }
}
