<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\Popups\Services\Placement\PopupPlacementResolver;
use Elemacy\Modules\ThemeBuilder\Services\Placement\ThemeBuilderPlacementResolver;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Rendering\LoopTemplateRenderStack;
use Elemacy\Loop\Placement\NestedLoopPlacementResolver;
use Elemacy\Loop\Placement\PagePlacementResolver;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class PlacementResolversTest extends IntegrationTestCase
{
    private ArchitectureCatalog $fixtures;
    private array $catalog;
    private RequestSimulator $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = new ArchitectureCatalog();
        try {
            $this->catalog = $this->fixtures->create();
            // A theme template with no inclusion rule displays nowhere, so give the
            // catalog's header, single and footer the conditions a new template of
            // their type starts with. Only here: other catalog users render pages
            // that a site-wide header would change.
            foreach ($this->catalog['locations'] as $type => $id) {
                (new \Elemacy\Conditions\ConditionRepository())->save($id, \Elemacy\TemplateLibrary\TypeRegistry::instance()->get($type)->default_conditions);
            }
        } catch (\Throwable $error) {
            $this->fixtures->cleanup();
            throw $error;
        }
        $this->request = new RequestSimulator();
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
        } finally {
            $this->fixtures->cleanup();
            parent::tearDown();
        }
    }

    public function testPageResolvesOnlyOnItsOwnQueriedSingularObject(): void
    {
        $resolver = new PagePlacementResolver();
        $this->request->visit(get_permalink($this->catalog['posts']['published']));

        self::assertSame(
            ['post_id' => $this->catalog['posts']['published']],
            $resolver->resolve($this->catalog['posts']['published'])
        );
        self::assertNull($resolver->resolve($this->catalog['posts']['draft']), 'A different owner must not borrow this request.');
    }

    public function testPageDoesNotResolveOnAnArchive(): void
    {
        $resolver = new PagePlacementResolver();
        $this->request->visit(get_term_link($this->catalog['term']));

        self::assertNull($resolver->resolve($this->catalog['posts']['published']));
    }

    public function testThemeBuilderHeaderFooterAndSingleResolveOnAPublishedSingle(): void
    {
        $resolver = new ThemeBuilderPlacementResolver();
        $this->request->visit(get_permalink($this->catalog['posts']['published']));

        self::assertSame(['location' => 'header'], $resolver->resolve($this->catalog['locations']['header']));
        self::assertSame(['location' => 'footer'], $resolver->resolve($this->catalog['locations']['footer']));
        self::assertSame(['location' => 'single'], $resolver->resolve($this->catalog['locations']['single']));
    }

    public function testThemeBuilderArchiveResolvesToItsLocationOnly(): void
    {
        $archive = $this->documents->create_everywhere('Archive fixture', 'archive');
        $resolver = new ThemeBuilderPlacementResolver();
        $this->request->visit(get_term_link($this->catalog['term']));

        self::assertSame(['location' => 'archive'], $resolver->resolve($archive));
    }

    public function testThemeBuilderDoesNotResolveAnUnrelatedId(): void
    {
        $resolver = new ThemeBuilderPlacementResolver();
        $this->request->visit(get_permalink($this->catalog['posts']['published']));

        self::assertNull($resolver->resolve($this->catalog['posts']['published']));
        self::assertNull($resolver->resolve(PHP_INT_MAX - 1));
    }

    public function testPopupResolvesForItsOwnMatchedDocument(): void
    {
        $resolver = new PopupPlacementResolver();
        $this->request->visit(get_permalink($this->catalog['posts']['published']));

        self::assertSame(
            ['popup_id' => $this->catalog['popups']['center']],
            $resolver->resolve($this->catalog['popups']['center'])
        );
    }

    public function testPopupDoesNotResolveForAnUnmatchedId(): void
    {
        $resolver = new PopupPlacementResolver();
        $this->request->visit(get_permalink($this->catalog['posts']['published']));

        self::assertNull($resolver->resolve($this->catalog['posts']['published']));
    }

    public function testNestedLoopResolvesOnlyForTheInnermostActivelyRenderingTemplate(): void
    {
        $resolver = new NestedLoopPlacementResolver();
        self::assertNull($resolver->resolve($this->catalog['nested_loop']), 'No template is rendering yet.');

        LoopTemplateRenderStack::push($this->catalog['library']['published']);
        LoopTemplateRenderStack::push($this->catalog['nested_loop']);
        try {
            self::assertSame(
                ['lineage' => [$this->catalog['library']['published'], $this->catalog['nested_loop']]],
                $resolver->resolve($this->catalog['nested_loop'])
            );
            self::assertNull(
                $resolver->resolve($this->catalog['library']['published']),
                'Only the innermost, actively rendering template resolves — not an outer ancestor.'
            );
        } finally {
            LoopTemplateRenderStack::pop();
            LoopTemplateRenderStack::pop();
        }
    }

    public function testNestedLoopRejectsExcessiveNestingDepth(): void
    {
        $resolver = new NestedLoopPlacementResolver();
        for ($depth = 0; $depth < 11; ++$depth) {
            LoopTemplateRenderStack::push(1000 + $depth);
        }
        try {
            self::assertNull($resolver->resolve(1010), 'Depth 11 exceeds the permitted nesting bound.');
        } finally {
            for ($depth = 0; $depth < 11; ++$depth) {
                LoopTemplateRenderStack::pop();
            }
        }
    }

    public function testRegistryHasEveryModuleContributedPlacement(): void
    {
        $registered = array_keys(PlacementResolverRegistry::instance()->all());

        foreach (['page', 'nested_loop', 'theme_builder', 'popup'] as $key) {
            self::assertContains($key, $registered, "Expected '$key' to be contributed through its owning module's composition root.");
        }
    }

    public function testRegistryResolvesCurrentThroughTheFirstMatchingContributor(): void
    {
        $registry = PlacementResolverRegistry::instance();
        $this->request->visit(get_permalink($this->catalog['posts']['published']));

        $resolved = $registry->resolve_current($this->catalog['locations']['header']);
        self::assertSame('theme_builder', $resolved['type']);
        self::assertSame(['location' => 'header', 'post_id' => $this->catalog['posts']['published']], $resolved['facts']);

        self::assertNull($registry->resolve_current(PHP_INT_MAX - 1));
    }

    public function testRegistrySuppliesTheViewedSingularAsTheCurrentPostInEveryHostPlacement(): void
    {
        $registry = PlacementResolverRegistry::instance();
        $published = $this->catalog['posts']['published'];
        $this->request->visit(get_permalink($published));

        self::assertSame(['location' => 'single', 'post_id' => $published], $registry->resolve_current($this->catalog['locations']['single'])['facts']);
        self::assertSame(['popup_id' => $this->catalog['popups']['center'], 'post_id' => $published], $registry->resolve_current($this->catalog['popups']['center'])['facts']);
        self::assertSame(['post_id' => $published], $registry->resolve_current($published)['facts']);
    }

    public function testRegistrySuppliesTheViewedTermButNoPostOnATermArchive(): void
    {
        $archive = $this->documents->create_everywhere('Archive fixture', 'archive');
        $this->request->visit(get_term_link($this->catalog['term']));

        $resolved = PlacementResolverRegistry::instance()->resolve_current($archive);
        self::assertSame(['location' => 'archive', 'term_id' => $this->catalog['term']], $resolved['facts']);
    }

    public function testNestedCurrentPostIsTheNearestEnclosingPostItemEvenInsideANonPostItem(): void
    {
        $published = $this->catalog['posts']['published'];
        $this->request->visit(get_permalink($this->catalog['elementor_host']));

        $facts = $this->nested_facts([new PostLoopItem(get_post($published)), $this->non_post_item()]);

        self::assertSame(['lineage' => [$this->catalog['nested_loop']], 'post_id' => $published], $facts);
    }

    public function testNestedNonPostItemOnAnArchiveHasNoCurrentPost(): void
    {
        $this->request->visit(get_term_link($this->catalog['term']));

        $facts = $this->nested_facts([$this->non_post_item()]);

        self::assertSame(['lineage' => [$this->catalog['nested_loop']], 'term_id' => $this->catalog['term']], $facts);
    }

    /** @dataProvider nonPublicPosts */
    public function testANonPublicPostItemIsNeverTheCurrentPost(string $status): void
    {
        $this->request->visit(get_permalink($this->catalog['elementor_host']));
        wp_set_current_user(0);

        $facts = $this->nested_facts([new PostLoopItem(get_post($this->catalog['posts'][$status]))]);

        self::assertSame(['lineage' => [$this->catalog['nested_loop']]], $facts);
    }

    public static function nonPublicPosts(): array
    {
        return ['draft' => ['draft'], 'private' => ['private'], 'password protected' => ['protected']];
    }

    public function testAResolverCannotRedefineTheRegistryOwnedFacts(): void
    {
        $registry = PlacementResolverRegistry::instance();
        $original = $registry->all();
        $registry->register(new class () implements \Elemacy\Loop\Contracts\PlacementResolverInterface {
            public function get_key(): string
            {
                return 'aaa_spoofing';
            }

            public function resolve(int $owner_id): ?array
            {
                return ['post_id' => 1, 'term_id' => 1, 'own' => true];
            }
        });
        $property = new \ReflectionProperty($registry, 'resolvers');
        $property->setAccessible(true);
        // Ask the spoofing resolver first, as if a contributor registered early.
        $property->setValue($registry, ['aaa_spoofing' => $registry->all()['aaa_spoofing']] + $original);

        try {
            $this->request->visit(get_term_link($this->catalog['term']));
            self::assertSame(['own' => true, 'term_id' => $this->catalog['term']], $registry->resolve_current(123)['facts']);
        } finally {
            $property->setValue($registry, $original);
        }
    }

    /** @param LoopItemInterface[] $items Outermost first, as LoopRenderer pushes them. */
    private function nested_facts(array $items): array
    {
        foreach ($items as $item) {
            LoopContext::push($item);
        }
        LoopTemplateRenderStack::push($this->catalog['nested_loop']);
        try {
            return PlacementResolverRegistry::instance()->resolve_current($this->catalog['nested_loop'])['facts'];
        } finally {
            LoopTemplateRenderStack::pop();
            foreach ($items as $item) {
                LoopContext::pop();
            }
        }
    }

    private function non_post_item(): LoopItemInterface
    {
        return new class () implements LoopItemInterface {
            public function get_identity(): string
            {
                return 'row';
            }

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
        };
    }
}
