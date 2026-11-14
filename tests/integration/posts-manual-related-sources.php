<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Placement\HostFacts;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * The Manual Selection and Related Posts sources, through the same public
 * settings a visitor's first and later pages use.
 */
final class PostsManualRelatedSourcesTest extends IntegrationTestCase
{
    private const TYPE = 'fx_event';
    private const TAX = 'fx_event_type';

    /** @var int[] */
    private array $posts = [];
    /** @var array<int, string> */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();
        register_post_type(self::TYPE, ['public' => true, 'label' => 'Fx Events']);
        register_taxonomy(self::TAX, self::TYPE, ['public' => true, 'label' => 'Fx Types']);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->posts as $post) {
                wp_delete_post($post, true);
            }
            foreach ($this->terms as $id => $taxonomy) {
                wp_delete_term($id, $taxonomy);
            }
            unregister_taxonomy(self::TAX);
            unregister_post_type(self::TYPE);
        } finally {
            parent::tearDown();
        }
    }

    public function testManualSelectionKeepsThePickedOrderAcrossPostTypes(): void
    {
        $event = $this->post('Event', self::TYPE);
        $page = $this->post('Page', 'page');
        $post = $this->post('Post', 'post');

        self::assertSame([$event, $page, $post], $this->manual(['manual_posts_ids' => [$event, $page, $post]]));
    }

    public function testAnUnpublishedPickDisappearsAndTheRestKeepTheirOrder(): void
    {
        $first = $this->post('First');
        $second = $this->post('Second');
        $third = $this->post('Third');
        wp_update_post(['ID' => $second, 'post_status' => 'draft']);

        self::assertSame([$first, $third], $this->manual(['manual_posts_ids' => [$first, $second, $third]]));
    }

    public function testAPasswordProtectedPickIsOmittedForVisitors(): void
    {
        $open = $this->post('Open');
        $locked = $this->post('Locked');
        wp_update_post(['ID' => $locked, 'post_password' => 'secret']);

        self::assertSame([$open], $this->manual(['manual_posts_ids' => [$locked, $open]]));
    }

    public function testManualSelectionPaginatesInThePickedOrder(): void
    {
        $picked = [$this->post('C'), $this->post('A'), $this->post('B')];
        $settings = ['manual_posts_ids' => $picked, 'posts_per_page' => 2];

        self::assertSame(array_slice($picked, 0, 2), $this->manual($settings, 1));
        self::assertSame(array_slice($picked, 2), $this->manual($settings, 2));
    }

    public function testManualSelectionDefaultsToTheSharedPageSizeOnEveryPage(): void
    {
        $picked = [];
        for ($i = 0; $i < 8; $i++) {
            $picked[] = $this->post('Pick ' . $i);
        }
        $picked = array_reverse($picked);
        $raw = ['manual_posts_ids' => $picked];
        $filled = $raw + ['posts_per_page' => 6];

        self::assertSame(array_slice($picked, 0, 6), $this->manual($raw, 1), 'A page size left at its default is not saved; six still show.');
        self::assertSame(array_slice($picked, 6), $this->manual($raw, 2));
        self::assertSame($this->manual($filled, 1), $this->manual($raw, 1));
        self::assertSame($this->manual($filled, 2), $this->manual($raw, 2));
    }

    public function testRelatedPostsDefaultToTheSharedPageSizeOnEveryPage(): void
    {
        $term = $this->term('Shared');
        $current = $this->post('Current', self::TYPE, [$term]);
        for ($i = 0; $i < 8; $i++) {
            $this->post('Related ' . $i, self::TYPE, [$term]);
        }
        $raw = ['related_posts_orderby' => 'title', 'related_posts_order' => 'ASC'];
        $filled = $raw + ['posts_per_page' => 6];

        self::assertCount(6, $this->related($raw, $current, 1));
        self::assertCount(2, $this->related($raw, $current, 2));
        self::assertSame($this->related($filled, $current, 1), $this->related($raw, $current, 1));
        self::assertSame($this->related($filled, $current, 2), $this->related($raw, $current, 2));
    }

    public function testSwitchingBetweenPostSourcesKeepsThePageSize(): void
    {
        $term = $this->term('Shared');
        $current = $this->post('Current', self::TYPE, [$term]);
        $others = [$this->post('One', self::TYPE, [$term]), $this->post('Two', self::TYPE, [$term]), $this->post('Three', self::TYPE, [$term])];
        $saved = ['posts_per_page' => 2, 'post_type' => self::TYPE, 'manual_posts_ids' => $others];
        $context = [HostFacts::POST_ID => $current];

        foreach (['posts', 'manual_posts', 'related_posts'] as $key) {
            $source = LoopDataSourceRegistry::instance()->get($key);
            $result = $source->get_items($source->get_public_settings($saved + ['data_source' => $key], $context, 1));

            self::assertCount(2, $result->items, "$key shows the one saved page size.");
            self::assertSame(2, $result->max_num_pages, "$key pages by the one saved page size.");
        }
    }

    public function testANothingPickedListIsEmptyAndExplainsItself(): void
    {
        $source = LoopDataSourceRegistry::instance()->get('manual_posts');
        $result = $source->get_items([]);

        self::assertSame([], $result->items);
        self::assertNotEmpty($result->notice);
    }

    public function testRelatedPostsShareATermAndNeverIncludeTheCurrentPost(): void
    {
        $term = $this->term('Shared');
        $current = $this->post('Current', self::TYPE, [$term]);
        $related = $this->post('Related', self::TYPE, [$term]);
        $this->post('Unrelated', self::TYPE);

        self::assertSame([$related], $this->related([], $current));
    }

    public function testRelatedPostsStayWithinTheCurrentPostType(): void
    {
        $term = $this->term('Shared');
        $current = $this->post('Current', self::TYPE, [$term]);
        register_taxonomy_for_object_type(self::TAX, 'post');
        $other_type = $this->post('Post', 'post', [$term]);

        self::assertNotContains($other_type, $this->related([], $current));
    }

    public function testRelatedByAChosenTaxonomyOnly(): void
    {
        $term = $this->term('Shared');
        $current = $this->post('Current', self::TYPE, [$term]);
        $this->post('Related', self::TYPE, [$term]);

        self::assertSame([], $this->related(['related_posts_taxonomies' => ['category']], $current));
    }

    public function testFallbackListsRecentPostsOfTheSameTypeExcludingTheCurrent(): void
    {
        $current = $this->post('Current', self::TYPE);
        $recent = $this->post('Recent', self::TYPE);

        self::assertSame([], $this->related([], $current));
        self::assertSame([$recent], $this->related(['related_posts_fallback' => 'yes'], $current));
    }

    public function testNoCurrentPostIsAnEmptyList(): void
    {
        self::assertSame([], $this->related(['related_posts_fallback' => 'yes'], 0));
    }

    public function testRelatedPostsPaginateFromTheSignedCurrentPost(): void
    {
        $term = $this->term('Shared');
        $current = $this->post('Current', self::TYPE, [$term]);
        $related = [$this->post('One', self::TYPE, [$term]), $this->post('Two', self::TYPE, [$term]), $this->post('Three', self::TYPE, [$term])];
        $settings = ['posts_per_page' => 2, 'related_posts_orderby' => 'date', 'related_posts_order' => 'ASC'];

        $first = $this->related($settings, $current, 1);
        $second = $this->related($settings, $current, 2);

        self::assertCount(2, $first);
        self::assertCount(1, $second);
        self::assertEqualsCanonicalizing($related, array_merge($first, $second));
    }

    public function testAnUnknownOrderFallsBackToDate(): void
    {
        $settings = LoopDataSourceRegistry::instance()->get('related_posts')->get_public_settings(['related_posts_orderby' => 'post_password'], [], 1);

        self::assertSame('date', $settings['related_posts_orderby']);
    }

    /** @return int[] */
    private function manual(array $settings, int $page = 1): array
    {
        $source = LoopDataSourceRegistry::instance()->get('manual_posts');

        return $this->ids($source->get_items($source->get_public_settings($settings, [], $page)));
    }

    /** @return int[] */
    private function related(array $settings, int $current, int $page = 1): array
    {
        $source = LoopDataSourceRegistry::instance()->get('related_posts');
        $context = $current ? [HostFacts::POST_ID => $current] : [];

        return $this->ids($source->get_items($source->get_public_settings($settings, $context, $page)));
    }

    /** @return int[] */
    private function ids($result): array
    {
        return array_map(static fn ($item): int => $item->get_post()->ID, $result->items);
    }

    /** @param int[] $terms */
    private function post(string $title, string $type = 'post', array $terms = []): int
    {
        $id = wp_insert_post(['post_type' => $type, 'post_status' => 'publish', 'post_title' => $title], true);
        self::assertIsInt($id);
        $this->posts[] = $id;
        if ($terms) {
            wp_set_object_terms($id, $terms, self::TAX);
        }

        return $id;
    }

    private function term(string $name): int
    {
        $term = wp_insert_term($name . ' ' . bin2hex(random_bytes(3)), self::TAX);
        self::assertIsArray($term);
        $this->terms[(int) $term['term_id']] = self::TAX;

        return (int) $term['term_id'];
    }
}
