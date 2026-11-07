<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Access\SignedParentChain;
use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Address\ItemAddress;
use Elemacy\Loop\Address\ItemAddressResolver;
use Elemacy\Loop\Address\Resolvers\PostStepResolver;
use Elemacy\Loop\Address\Resolvers\TermStepResolver;
use Elemacy\Loop\Address\Resolvers\UserStepResolver;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Placement\HostFacts;
use Elemacy\Loop\Placement\NestedLoopPlacementResolver;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\Loop\Rendering\LoopTemplateRenderStack;
use Elemacy\Loop\Rendering\TemplateRecursionGuard;
use Elemacy\Loop\Support\LoopLimits;

require_once __DIR__ . '/IntegrationTestCase.php';

/** A loop item whose enter() fails after touching nothing. */
final class UnenterableLoopItem implements LoopItemInterface
{
    public int $exits = 0;

    public function get_identity(): string
    {
        return 'unenterable';
    }

    public function get_field(string $key)
    {
        return null;
    }

    public function enter(): void
    {
        throw new \RuntimeException('This item is no longer stored here.');
    }

    public function exit(): void
    {
        ++$this->exits;
    }
}

/**
 * Item addresses without ACF: the built-in post/term/user step resolvers, the
 * signed parent facts, the later-page chain and the renderer's failure path.
 */
final class LoopAddressesTest extends IntegrationTestCase
{
    /** @var int[] */
    private array $posts = [];

    protected function tearDown(): void
    {
        try {
            while (null !== LoopContext::current_frame()) {
                LoopContext::pop();
            }
            foreach (array_reverse($this->posts) as $post) {
                wp_delete_post($post, true);
            }
            $this->posts = [];
        } finally {
            parent::tearDown();
        }
    }

    public function testThePostResolverRebuildsOnlyPubliclyViewablePostsAndFingerprintsTheirLastChange(): void
    {
        $resolver = new PostStepResolver();
        $published = $this->post(['post_status' => 'publish']);

        foreach (['draft' => $this->post(['post_status' => 'draft']), 'private' => $this->post(['post_status' => 'private']), 'protected' => $this->post(['post_status' => 'publish', 'post_password' => 'secret'])] as $kind => $id) {
            self::assertNull($resolver->resolve(new AddressStep('post', (string) $id), null), "A $kind post must not be rebuilt.");
        }
        self::assertNull($resolver->resolve(new AddressStep('post', 'abc'), null));
        self::assertNull($resolver->resolve(new AddressStep('post', '999999999'), null));

        $item = $resolver->resolve(new AddressStep('post', (string) $published), null);
        self::assertInstanceOf(PostLoopItem::class, $item);
        self::assertSame('post:' . $published, $item->get_address_step()->canonical());

        $before = $resolver->fingerprint($item);
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['post_modified_gmt' => '2001-01-01 00:00:00'], ['ID' => $published]);
        clean_post_cache($published);
        $after = $resolver->fingerprint($resolver->resolve(new AddressStep('post', (string) $published), null));
        self::assertNotSame($before, $after, 'Editing a parent post must change its fingerprint.');
    }

    public function testTheTermAndUserResolversRefuseWhatNoLongerExistsOrIsNotPublic(): void
    {
        register_taxonomy('elemacy_hidden_tax', 'post', ['public' => false, 'publicly_queryable' => false]);
        $hidden = wp_insert_term('Hidden ' . uniqid(), 'elemacy_hidden_tax');
        $category = wp_insert_term('Visible ' . uniqid(), 'category');

        try {
            $terms = new TermStepResolver();
            self::assertNull($terms->resolve(new AddressStep('term', (string) $hidden['term_id']), null), 'A term of a non-viewable taxonomy must not be rebuilt.');
            self::assertNotNull($terms->resolve(new AddressStep('term', (string) $category['term_id']), null));

            $users = new UserStepResolver();
            self::assertNull($users->resolve(new AddressStep('user', '999999999'), null));
            self::assertNotNull($users->resolve(new AddressStep('user', (string) get_current_user_id()), null));
        } finally {
            wp_delete_term($hidden['term_id'], 'elemacy_hidden_tax');
            wp_delete_term($category['term_id'], 'category');
            unregister_taxonomy('elemacy_hidden_tax');
        }
    }

    public function testAParentFactIsAcceptedOnlyOnANestedLoopTokenWhoseDepthMatchesItsLineage(): void
    {
        $signer = new PaginationContextSigner();
        $outer = $this->post(['post_status' => 'publish']);
        $template = $this->documents->create('Loop addresses template', 'loop');
        $parent = ['parent' => 'post:' . $outer, 'parent_fp' => 'fp', 'parent_render' => 'rk'];

        $valid = $signer->sign($template, 'w1', 'f', 'nested_loop', ['lineage' => [$template]] + $parent);
        self::assertSame('post:' . $outer, $signer->verify($valid, $template, 'w1', 'f')['facts']['parent']);

        $refused = [
            'a page token' => $signer->sign($template, 'w1', 'f', 'page', $parent),
            'a depth mismatch' => $signer->sign($template, 'w1', 'f', 'nested_loop', ['lineage' => [$template]] + ['parent' => 'post:' . $outer . '/post:' . $outer] + $parent),
            'a malformed address' => $signer->sign($template, 'w1', 'f', 'nested_loop', ['lineage' => [$template]] + ['parent' => 'post'] + $parent),
            'an opaque step' => $signer->sign($template, 'w1', 'f', 'nested_loop', ['lineage' => [$template]] + ['parent' => 'opaque:x'] + $parent),
            'a missing fingerprint' => $signer->sign($template, 'w1', 'f', 'nested_loop', ['lineage' => [$template], 'parent' => 'post:' . $outer, 'parent_render' => 'rk']),
        ];

        foreach ($refused as $case => $token) {
            try {
                $signer->verify($token, $template, 'w1', 'f');
                self::fail("A token with $case must be refused.");
            } catch (ValidationException $error) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testALaterPageRebuildsItsSignedChainRefusesAChangedOneAndRestoresEverything(): void
    {
        $outer = $this->post(['post_status' => 'publish']);
        $inner = $this->post(['post_status' => 'publish']);
        $address = new ItemAddress([new AddressStep('post', (string) $outer), new AddressStep('post', (string) $inner)]);
        $resolver = new ItemAddressResolver();
        $facts = [
            'lineage' => [101, 102],
            HostFacts::POST_ID => $outer,
            HostFacts::PARENT => $address->canonical(),
            HostFacts::PARENT_FP => $resolver->fingerprint($address, $resolver->resolve($address)),
            HostFacts::PARENT_RENDER => 'render-key',
        ];
        $global_post = $GLOBALS['post'] ?? null;

        $chain = SignedParentChain::enter($facts, new ItemAddressResolver());
        try {
            self::assertSame($inner, get_the_ID(), 'The innermost rebuilt post is the native current post.');
            self::assertSame($address->canonical(), LoopContext::current_frame()->address()->canonical());
            self::assertSame('render-key', LoopContext::current_frame()->render_key());
            self::assertSame([101, 102], LoopTemplateRenderStack::stack());
            self::assertSame(['lineage' => [101, 102]], (new NestedLoopPlacementResolver())->resolve(102), 'A token minted inside the page continues the signed lineage.');
        } finally {
            $chain->leave();
        }

        self::assertSame([], LoopContext::frames());
        self::assertSame([], LoopTemplateRenderStack::stack());
        self::assertSame(0, TemplateRecursionGuard::depth());
        self::assertSame($global_post, $GLOBALS['post'] ?? null);

        // Edited since the page was served (set directly: modified times have one-second resolution).
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['post_modified_gmt' => '2001-01-01 00:00:00'], ['ID' => $inner]);
        clean_post_cache($inner);
        $refused = false;
        try {
            SignedParentChain::enter($facts, new ItemAddressResolver())->leave();
        } catch (ValidationException $error) {
            $refused = true;
        }
        self::assertTrue($refused, 'A chain whose content changed is refused.');
        self::assertSame([], LoopContext::frames(), 'A refused chain leaves no frame behind.');
        self::assertSame([], LoopTemplateRenderStack::stack());
        self::assertSame(0, TemplateRecursionGuard::depth());
    }

    public function testATokenMintedAtTheNestingLimitInsideALaterPageCannotNestFurther(): void
    {
        $steps = [];
        $lineage = [];
        for ($i = 0; $i < LoopLimits::MAX_DEPTH; $i++) {
            $steps[] = new AddressStep('post', (string) $this->post(['post_status' => 'publish']));
            $lineage[] = 500 + $i;
        }
        $address = new ItemAddress($steps);
        $resolver = new ItemAddressResolver();
        $facts = [
            'lineage' => $lineage,
            HostFacts::PARENT => $address->canonical(),
            HostFacts::PARENT_FP => $resolver->fingerprint($address, $resolver->resolve($address)),
            HostFacts::PARENT_RENDER => '',
        ];
        $template = $this->documents->create('Eleventh level', 'loop');

        $chain = SignedParentChain::enter($facts, new ItemAddressResolver());
        try {
            ob_start();
            $rendered = (new LoopRenderer())->render_item($template, new PostLoopItem(get_post((int) $steps[0]->locator())), 'p', 'posts');
            $html = ob_get_clean();
        } finally {
            $chain->leave();
        }

        self::assertFalse($rendered, 'An eleventh level must not render.');
        self::assertSame('', $html);
        self::assertSame(0, TemplateRecursionGuard::depth());
    }

    public function testAnEleventhLevelLoopRendersNothingAndTellsOnlyAnEditorWhy(): void
    {
        $template = $this->documents->create('Too deep template', 'loop');
        for ($i = 0; $i < LoopLimits::MAX_DEPTH; $i++) {
            LoopContext::push(new UnenterableLoopItem());
        }
        $render = static function () use ($template): string {
            $widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance([
                'id' => 'toodeep', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid',
                'settings' => ['data_source' => 'posts', 'template_id' => $template], 'elements' => [],
            ]);
            \Elementor\Plugin::$instance->documents->switch_to_document(\Elementor\Plugin::$instance->documents->get($template, false));
            ob_start();
            try {
                $widget->render_content();
            } finally {
                $html = (string) ob_get_clean();
                \Elementor\Plugin::$instance->documents->restore_document();
            }

            return $html;
        };

        $as_editor = $render();
        $user = get_current_user_id();
        wp_set_current_user(0);
        try {
            $as_visitor = $render();
        } finally {
            wp_set_current_user($user);
        }

        self::assertStringContainsString('nested more than ' . LoopLimits::MAX_DEPTH . ' levels deep', $as_editor);
        self::assertStringNotContainsString('elemacy-loop-item', $as_editor);
        self::assertStringNotContainsString('nested more than', $as_visitor);
        self::assertStringNotContainsString('elemacy-loop-item', $as_visitor);
    }

    public function testAnItemThatFailsToEnterIsSkippedWithoutReEnteringOrDisturbingItsParent(): void
    {
        $outer_id = $this->post(['post_status' => 'publish']);
        $template = $this->documents->create('Failing child template', 'loop');
        $outer = new PostLoopItem(get_post($outer_id));
        $global_post = $GLOBALS['post'] ?? null;

        $frame = LoopContext::push($outer, 'posts', $template, 0, 'outer-key');
        $outer->enter();
        try {
            $failing = new UnenterableLoopItem();
            ob_start();
            $rendered = (new LoopRenderer())->render_item($template, $failing, 'p', 'ext');
            $html = ob_get_clean();

            self::assertFalse($rendered);
            self::assertSame('', trim(strip_tags($html)), 'A skipped item leaves no markup.');
            self::assertSame(0, $failing->exits, 'An item that never entered is never exited.');
            self::assertSame($outer_id, get_the_ID(), 'The enclosing post stays the native current post.');
            self::assertSame($frame, LoopContext::current_frame());
        } finally {
            $outer->exit();
            LoopContext::pop();
        }

        self::assertSame($global_post, $GLOBALS['post'] ?? null);
    }

    private function post(array $values): int
    {
        $id = wp_insert_post($values + ['post_type' => 'post', 'post_title' => 'Address fixture ' . uniqid()], true);
        self::assertNotInstanceOf(\WP_Error::class, $id);
        $this->posts[] = (int) $id;

        return (int) $id;
    }
}
