<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\Popups\Services\Placement\PopupPlacementResolver;
use Elemacy\Modules\ThemeBuilder\Services\Placement\ThemeBuilderPlacementResolver;
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
        self::assertSame(['location' => 'single', 'query' => []], $resolver->resolve($this->catalog['locations']['single']));
    }

    public function testThemeBuilderArchiveExposesVerifiedQueryFacts(): void
    {
        $archive = $this->documents->create('Archive fixture', 'archive');
        $resolver = new ThemeBuilderPlacementResolver();
        $this->request->visit(get_term_link($this->catalog['term']));

        $resolved = $resolver->resolve($archive);
        self::assertNotNull($resolved);
        self::assertSame('archive', $resolved['location']);
        self::assertSame('category', $resolved['query']['taxonomy']);
        self::assertSame($this->catalog['term'], $resolved['query']['term_id']);
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
        self::assertSame(['location' => 'header'], $resolved['facts']);

        self::assertNull($registry->resolve_current(PHP_INT_MAX - 1));
    }
}
