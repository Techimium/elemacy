<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopItems\PostLoopItem;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeaterRowLoopItem;
use RuntimeException;
use WP_Post;
use WP_User;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * design.md D8: the shared render loop's enter()/exit() pair must reverse
 * exactly what it set up — restoring the previously active item's state —
 * not merely reset native globals to the site's main-query post. A loop
 * item template that itself contains another loop (a post nested inside a
 * post, or a nested/repeated ACF repeater row) must not corrupt the outer
 * item's context once the inner one exits. These reproduce the currently
 * real gaps that design.md D8 exists to close; task 8.2/8.3 make the red
 * assertions below pass. Deliberately checking the exact native global
 * state after exit() — not just LoopContext::current() — since the stack
 * itself already unwinds correctly today.
 */
/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class LoopContextTest extends IntegrationTestCase
{
    public function testNestedPostLoopItemsRestoreTheExactOuterStateNotTheMainQueryPost(): void
    {
        $main_page = self::insertPost(['post_type' => 'page', 'post_title' => 'Main query host']);
        $outer_post = self::insertPost(['post_title' => 'Outer loop post']);
        $inner_post = self::insertPost(['post_title' => 'Inner loop post']);

        $request = new RequestSimulator();
        try {
            $request->visit(get_permalink($main_page));
            self::assertSame($main_page, $GLOBALS['wp_query']->post->ID, 'Fixture must visit a real singular main query.');

            $outer_item = new PostLoopItem(get_post($outer_post));
            LoopContext::push($outer_item);
            try {
                $outer_item->enter();
                self::assertSame($outer_post, get_the_ID(), 'Outer enter() must establish its own post.');
                $outer_snapshot = self::snapshotPostGlobals();

                $inner_item = new PostLoopItem(get_post($inner_post));
                LoopContext::push($inner_item);
                try {
                    $inner_item->enter();
                    self::assertSame($inner_post, get_the_ID(), 'Inner enter() must establish its own post.');
                } finally {
                    $inner_item->exit();
                    LoopContext::pop();
                }

                self::assertSame($outer_item, LoopContext::current(), 'The stack must unwind back to the outer item — checking only this would miss the native-global corruption below.');
                $after_snapshot = self::snapshotPostGlobals();
                self::assertNotSame($main_page, $after_snapshot['post_id'], 'The outer item\'s post must not be replaced by the site\'s main-query post after the inner item exits.');
                self::assertSame($outer_snapshot, $after_snapshot, 'Exiting a nested inner loop item must restore the exact outer state, not just reset to the main query.');
            } finally {
                $outer_item->exit();
                LoopContext::pop();
            }
        } finally {
            $request->restore();
            wp_delete_post($main_page, true);
            wp_delete_post($outer_post, true);
            wp_delete_post($inner_post, true);
        }
    }

    public function testRenderFailureInsideANestedItemStillRestoresTheOuterStateNotTheMainQueryPost(): void
    {
        $main_page = self::insertPost(['post_type' => 'page', 'post_title' => 'Main query host']);
        $outer_post = self::insertPost(['post_title' => 'Outer loop post']);
        $inner_post = self::insertPost(['post_title' => 'Inner loop post']);

        $request = new RequestSimulator();
        try {
            $request->visit(get_permalink($main_page));

            $outer_item = new PostLoopItem(get_post($outer_post));
            LoopContext::push($outer_item);
            try {
                $outer_item->enter();
                $outer_snapshot = self::snapshotPostGlobals();

                $inner_item = new PostLoopItem(get_post($inner_post));
                LoopContext::push($inner_item);
                $caught = null;
                try {
                    $inner_item->enter();
                    try {
                        throw new RuntimeException('Simulated failure inside the nested item\'s render body.');
                    } finally {
                        // Mirrors LoopGrid.php's own inner try/finally: exit()
                        // still runs when the render body throws.
                        $inner_item->exit();
                    }
                } catch (RuntimeException $error) {
                    $caught = $error;
                } finally {
                    LoopContext::pop();
                }
                self::assertInstanceOf(RuntimeException::class, $caught, 'The render failure must propagate, not be swallowed.');

                self::assertSame($outer_item, LoopContext::current(), 'The stack must unwind back to the outer item even after a render failure.');
                $after_snapshot = self::snapshotPostGlobals();
                self::assertNotSame($main_page, $after_snapshot['post_id'], 'A render failure inside a nested item must not leave the outer item\'s post replaced by the site\'s main-query post.');
                self::assertSame($outer_snapshot, $after_snapshot, 'A render failure inside a nested item must restore the exact outer state once its own finally-based exit() runs.');
            } finally {
                $outer_item->exit();
                LoopContext::pop();
            }
        } finally {
            $request->restore();
            wp_delete_post($main_page, true);
            wp_delete_post($outer_post, true);
            wp_delete_post($inner_post, true);
        }
    }

    public function testPartialEnterFailureReEntersTheEnclosingItemToRecoverItsState(): void
    {
        $template_id = $this->documents->create('Loop item template for partial-enter recovery', 'loop', 'publish');
        $outer_post = self::insertPost(['post_title' => 'Outer loop post']);
        $failing_post = self::insertPost(['post_title' => 'Post the failing item wraps']);

        try {
            $outer_item = new PostLoopItem(get_post($outer_post));
            LoopContext::push($outer_item);
            try {
                $outer_item->enter();
                $outer_snapshot = self::snapshotPostGlobals();

                // A failing item that mutates global $post directly (bypassing
                // WordPressPostScope entirely) and throws before completing
                // its own setup — reproducing a partial enter() from a
                // legacy/hand-rolled LoopItemInterface implementation, not
                // just a well-behaved one. LoopRenderer::render_item() is the
                // fix design.md D8 assigns to this: on a failed enter(), it
                // re-enters whatever item was already active (design.md D8's
                // "cover partial failure during enter"), not merely relying
                // on the failing item's own (here, absent) reversibility.
                $failing_item = new PartiallyFailingLoopItem(get_post($failing_post));
                ob_start();
                try {
                    $ok = (new LoopRenderer())->render_item($template_id, $failing_item, 'placement', 'fixture');
                } finally {
                    ob_end_clean();
                }

                self::assertFalse($ok, 'A failing enter() must make render_item() report failure, not throw out to the caller.');
                self::assertSame($outer_item, LoopContext::current(), 'The stack must unwind back to the outer item after a partial enter() failure.');
                self::assertSame($outer_snapshot, self::snapshotPostGlobals(), 'A partial enter() failure must not leave global $post (or any other native global) mutated toward the failing item\'s post — LoopRenderer must re-enter the enclosing item to recover it.');
            } finally {
                $outer_item->exit();
                LoopContext::pop();
            }
        } finally {
            wp_delete_post($outer_post, true);
            wp_delete_post($failing_post, true);
        }
    }

    public function testNestedAndRepeatedAcfRepeaterRowsRestoreTheExactOuterRowCursor(): void
    {
        self::assertTrue(class_exists(AcfRepeaterRowLoopItem::class), 'This test requires --with-pro.');

        $fixtures = new ArchitectureCatalog();
        try {
            $catalog = $fixtures->create();
            $field = $catalog['acf']['key'];
            $post_id = $catalog['acf']['post_id'];

            $outer = new AcfRepeaterRowLoopItem($field, $post_id, 0);
            LoopContext::push($outer);
            try {
                $outer->enter();
                self::assertSame('First native row', get_sub_field('label'), 'Entering row 0 must position ACF\'s own cursor on row 0.');

                // A single level of ACF's own nested repeater still resolves
                // correctly while row 0 is active — this is the currently
                // well-supported half of "nested rows".
                self::assertTrue(have_rows('children'), 'Row 0 seeds one child row.');
                the_row();
                self::assertSame('Nested native row', get_sub_field('label'));
                self::assertFalse(have_rows('children'), 'Row 0 seeds exactly one child row.');
                self::assertSame('First native row', get_sub_field('label'), 'A single level of native nested repeater must not disturb the parent row once exhausted.');

                // A nested/repeated widget rendering a DIFFERENT row of the
                // SAME top-level field+post while row 0 is still active (e.g.
                // a loop item template that itself contains another Loop
                // Grid on this field) shares ACF's one native cursor for
                // this field+post — mirrors LoopGrid.php's own
                // LoopContext::push()/enter()/exit()/pop() pattern exactly.
                $inner = new AcfRepeaterRowLoopItem($field, $post_id, 2);
                LoopContext::push($inner);
                try {
                    $inner->enter();
                    self::assertSame('Third native row', get_sub_field('label'), 'The inner item must resolve its own row correctly.');
                } finally {
                    $inner->exit();
                    LoopContext::pop();
                }

                self::assertSame($outer, LoopContext::current(), 'The stack must unwind back to the outer row — checking only this would miss the native cursor corruption below.');
                self::assertSame(
                    'First native row',
                    get_sub_field('label'),
                    'Exiting a nested/repeated inner row item must restore the exact outer row, not leave the shared native cursor wherever the inner item left it.'
                );
            } finally {
                $outer->exit();
                LoopContext::pop();
            }

            // The outer iteration must still be able to reach its own next
            // row correctly afterward — not merely that its own exit()
            // restored it once.
            $next = new AcfRepeaterRowLoopItem($field, $post_id, 1);
            LoopContext::push($next);
            try {
                $next->enter();
                self::assertSame('Second native row', get_sub_field('label'), 'The outer iteration must still reach row 1 in order after a nested/repeated row item for the same field+post.');
            } finally {
                $next->exit();
                LoopContext::pop();
            }
        } finally {
            $fixtures->cleanup();
        }
    }

    public function testAcfRepeaterRowRenderFailureStillRestoresTheOuterRowCursor(): void
    {
        self::assertTrue(class_exists(AcfRepeaterRowLoopItem::class), 'This test requires --with-pro.');

        $fixtures = new ArchitectureCatalog();
        try {
            $catalog = $fixtures->create();
            $field = $catalog['acf']['key'];
            $post_id = $catalog['acf']['post_id'];

            $outer = new AcfRepeaterRowLoopItem($field, $post_id, 0);
            LoopContext::push($outer);
            try {
                $outer->enter();

                $inner = new AcfRepeaterRowLoopItem($field, $post_id, 2);
                LoopContext::push($inner);
                $caught = null;
                try {
                    $inner->enter();
                    try {
                        throw new RuntimeException('Simulated failure inside the nested row item\'s render body.');
                    } finally {
                        $inner->exit();
                    }
                } catch (RuntimeException $error) {
                    $caught = $error;
                } finally {
                    LoopContext::pop();
                }
                self::assertInstanceOf(RuntimeException::class, $caught, 'The render failure must propagate, not be swallowed.');

                self::assertSame($outer, LoopContext::current(), 'The stack must unwind back to the outer row even after a render failure.');
                self::assertSame(
                    'First native row',
                    get_sub_field('label'),
                    'A render failure inside a nested/repeated row item must not leave the outer row\'s native cursor corrupted once its own finally-based exit() runs.'
                );
            } finally {
                $outer->exit();
                LoopContext::pop();
            }
        } finally {
            $fixtures->cleanup();
        }
    }

    private static function insertPost(array $overrides): int
    {
        $id = wp_insert_post($overrides + [
            'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'Fixture content.',
        ], true);
        self::assertNotInstanceOf(\WP_Error::class, $id);

        return (int) $id;
    }

    private static function snapshotPostGlobals(): array
    {
        global $post, $id, $authordata, $currentday, $currentmonth, $page, $pages, $multipage, $more, $numpages;

        return [
            'post_id' => $post instanceof WP_Post ? $post->ID : null,
            'id' => $id,
            'author_id' => $authordata instanceof WP_User ? $authordata->ID : null,
            'currentday' => $currentday,
            'currentmonth' => $currentmonth,
            'page' => $page,
            'pages' => $pages,
            'multipage' => $multipage,
            'more' => $more,
            'numpages' => $numpages,
            'get_the_ID' => get_the_ID(),
        ];
    }
}

/**
 * A loop item whose enter() mutates global $post, then fails before
 * finishing its own setup — reproducing a real partial-enter failure
 * without needing PostLoopItem itself to have a multi-step enter().
 */
final class PartiallyFailingLoopItem implements LoopItemInterface
{
    private WP_Post $post;

    public function __construct(WP_Post $post)
    {
        $this->post = $post;
    }

    public function get_identity(): string
    {
        return (string) $this->post->ID;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
        global $post;

        $post = $this->post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- mirrors PostLoopItem::enter(), deliberately failing before setup_postdata() to simulate a partial enter().
        throw new RuntimeException('Simulated partial enter() failure.');
    }

    public function exit(): void
    {
    }
}
