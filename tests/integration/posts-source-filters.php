<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Hooks;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\Sources\Posts\ShownPosts;
use Elemacy\Modules\LoopBuilder\DataSources\PostsDataSource;
use ReflectionMethod;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * The Posts source's optional filters, sort orders and developer hook, through
 * the same public settings a visitor's first and later pages use, on a custom
 * post type.
 */
final class PostsSourceFiltersTest extends IntegrationTestCase
{
    private const TYPE = 'fx_event';
    private const TAX = 'fx_event_type';
    private const SECOND_TAX = 'fx_event_city';

    /** @var int[] */
    private array $posts = [];
    /** @var array<int, string> Term id => taxonomy. */
    private array $terms = [];
    /** @var int[] */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        register_post_type(self::TYPE, ['public' => true, 'label' => 'Fx Events', 'supports' => ['title', 'editor', 'author']]);
        register_taxonomy(self::TAX, self::TYPE, ['public' => true, 'label' => 'Fx Types']);
        register_taxonomy(self::SECOND_TAX, self::TYPE, ['public' => true, 'label' => 'Fx Cities']);
        ShownPosts::reset();
    }

    protected function tearDown(): void
    {
        try {
            remove_all_filters(Hooks::LOOP_POSTS_QUERY_ARGS_FILTER);
            foreach ($this->posts as $post) {
                wp_delete_post($post, true);
            }
            foreach ($this->terms as $id => $taxonomy) {
                wp_delete_term($id, $taxonomy);
            }
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ($this->users as $user) {
                wp_delete_user($user);
            }
            unregister_taxonomy(self::TAX);
            unregister_taxonomy(self::SECOND_TAX);
            unregister_post_type(self::TYPE);
            ShownPosts::reset();
        } finally {
            parent::tearDown();
        }
    }

    public function testPostTypeChoiceListsCustomTypesAndHidesStorageTypes(): void
    {
        $options = $this->post_type_options();

        self::assertArrayHasKey(self::TYPE, $options);
        self::assertArrayHasKey('post', $options);
        self::assertArrayNotHasKey('attachment', $options);
        self::assertArrayNotHasKey('elementor_library', $options);
    }

    public function testASavedTypeNoLongerListedStillQueries(): void
    {
        $settings = LoopDataSourceRegistry::instance()->get('posts')->get_public_settings(['post_type' => 'attachment', 'exclude_current_post' => ''], [], 1);

        self::assertSame('attachment', $settings['post_type']);
    }

    public function testIncludeByTermOnACustomPostTypeHoldsOnEveryPage(): void
    {
        $term = $this->term('Conference', self::TAX);
        $matching = [$this->event('A', [self::TAX => [$term]]), $this->event('B', [self::TAX => [$term]]), $this->event('C', [self::TAX => [$term]])];
        $this->event('Other');

        self::assertEqualsCanonicalizing($matching, $this->all_pages(['posts_include_terms' => [$term]], 2));
    }

    public function testTermsFromTwoTaxonomiesMustBothMatch(): void
    {
        $type = $this->term('Talk', self::TAX);
        $city = $this->term('Dhaka', self::SECOND_TAX);
        $both = $this->event('Both', [self::TAX => [$type], self::SECOND_TAX => [$city]]);
        $this->event('Type only', [self::TAX => [$type]]);
        $this->event('City only', [self::SECOND_TAX => [$city]]);

        self::assertSame([$both], $this->query(['posts_include_terms' => [$type, $city]]));
    }

    public function testATermFromAnotherPostTypesTaxonomyIsIgnored(): void
    {
        $category = $this->term('Foreign', 'category');
        $event = $this->event('Event');

        self::assertContains($event, $this->query(['posts_include_terms' => [$category]]));
    }

    public function testExcludingPostsTermsAndAuthors(): void
    {
        $term = $this->term('Hidden', self::TAX);
        $author = $this->author();
        $kept = $this->event('Kept');
        $by_post = $this->event('By post');
        $by_term = $this->event('By term', [self::TAX => [$term]]);
        $by_author = $this->event('By author', [], ['post_author' => $author]);

        $ids = $this->query([
            'posts_exclude_posts' => [$by_post],
            'posts_exclude_terms' => [$term],
            'posts_exclude_authors' => [$author],
        ]);

        self::assertContains($kept, $ids);
        self::assertNotContains($by_post, $ids);
        self::assertNotContains($by_term, $ids);
        self::assertNotContains($by_author, $ids);
    }

    public function testIncludeByAuthor(): void
    {
        $author = $this->author();
        $mine = $this->event('Mine', [], ['post_author' => $author]);
        $this->event('Theirs');

        self::assertSame([$mine], $this->query(['posts_include_authors' => [$author]]));
    }

    public function testAMalformedIdListIsRefused(): void
    {
        $this->expectException(ValidationException::class);

        $this->query(['posts_include_terms' => 'not-a-list']);
    }

    /** @dataProvider dateRanges */
    public function testDateRanges(string $range, string $inside, string $outside): void
    {
        $recent = $this->event('Recent', [], ['post_date' => gmdate('Y-m-d H:i:s', strtotime($inside))]);
        $old = $this->event('Old', [], ['post_date' => gmdate('Y-m-d H:i:s', strtotime($outside))]);

        $ids = $this->query(['posts_date_range' => $range]);

        self::assertContains($recent, $ids);
        self::assertNotContains($old, $ids);
    }

    public static function dateRanges(): array
    {
        return [
            'day' => ['day', '-6 hours', '-3 days'],
            'week' => ['week', '-3 days', '-10 days'],
            'month' => ['month', '-20 days', '-2 months'],
            'quarter' => ['quarter', '-2 months', '-5 months'],
            'year' => ['year', '-10 months', '-2 years'],
        ];
    }

    public function testACustomDateRangeHoldsOnLaterPages(): void
    {
        $inside = [
            $this->event('In 1', [], ['post_date' => '2024-03-05 10:00:00']),
            $this->event('In 2', [], ['post_date' => '2024-03-10 10:00:00']),
            $this->event('In 3', [], ['post_date' => '2024-03-15 10:00:00']),
        ];
        $this->event('Before', [], ['post_date' => '2024-01-01 10:00:00']);
        $this->event('After', [], ['post_date' => '2024-06-01 10:00:00']);

        self::assertEqualsCanonicalizing($inside, $this->all_pages([
            'posts_date_range' => 'custom',
            'posts_date_after' => '2024-03-01 00:00',
            'posts_date_before' => '2024-03-31 23:59',
        ], 2));
    }

    public function testCustomFieldConditionsHoldOnLaterPages(): void
    {
        $matching = [
            $this->event('One', [], [], ['is_featured' => '1']),
            $this->event('Two', [], [], ['is_featured' => '1']),
            $this->event('Three', [], [], ['is_featured' => '1']),
        ];
        $this->event('Not featured', [], [], ['is_featured' => '0']);
        $this->event('No field');

        self::assertEqualsCanonicalizing($matching, $this->all_pages([
            'posts_meta_conditions' => [['key' => 'is_featured', 'compare' => '=', 'value' => '1']],
        ], 2));
    }

    public function testExistsAndNumericComparisons(): void
    {
        $cheap = $this->event('Cheap', [], [], ['price' => '9']);
        $dear = $this->event('Dear', [], [], ['price' => '100']);
        $none = $this->event('No price');

        $existing = $this->query(['posts_meta_conditions' => [['key' => 'price', 'compare' => 'EXISTS', 'value' => '']]]);
        self::assertEqualsCanonicalizing([$cheap, $dear], $existing);
        self::assertNotContains($none, $existing);

        // Numerically 9 < 50; as text "9" would sort after "50".
        self::assertSame([$cheap], $this->query(['posts_meta_conditions' => [['key' => 'price', 'compare' => '<', 'value' => '50']]]));
    }

    public function testAConditionOnAProtectedKeyHasNoEffect(): void
    {
        $first = $this->event('One', [], [], ['_secret' => 'yes']);
        $second = $this->event('Two');

        self::assertEqualsCanonicalizing([$first, $second], $this->query([
            'posts_meta_conditions' => [['key' => '_secret', 'compare' => '=', 'value' => 'yes']],
        ]));
    }

    public function testSortingByANumericCustomFieldHoldsAcrossPages(): void
    {
        $third = $this->event('Third', [], [], ['price' => '100']);
        $first = $this->event('First', [], [], ['price' => '9']);
        $second = $this->event('Second', [], [], ['price' => '50']);

        self::assertSame([$first, $second, $third], $this->all_pages([
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_order_meta_key' => 'price',
        ], 2));
    }

    public function testNewSortOrdersAreHonoured(): void
    {
        $first = $this->event('Bravo');
        $second = $this->event('Alpha');

        self::assertSame([$first, $second], $this->query(['orderby' => 'id', 'order' => 'ASC']));
    }

    public function testACustomFieldSortWithNoFieldNameFallsBackToDate(): void
    {
        $older = $this->event('Older', [], ['post_date' => '2024-01-01 10:00:00']);
        $newer = $this->event('Newer', [], ['post_date' => '2024-02-01 10:00:00']);

        self::assertSame([$newer, $older], $this->query(['orderby' => 'meta_value', 'order' => 'DESC']));
    }

    public function testExistingOrdersAreUnchanged(): void
    {
        $older = $this->event('Zulu', [], ['post_date' => '2024-01-01 10:00:00']);
        $newer = $this->event('Yankee', [], ['post_date' => '2024-02-01 10:00:00']);

        self::assertSame([$newer, $older], $this->query(['orderby' => 'date', 'order' => 'DESC']));
        self::assertSame([$newer, $older], $this->query(['orderby' => 'title', 'order' => 'ASC']));
    }

    public function testStickyPostsAreOnlyIgnoredWhenAsked(): void
    {
        $author = $this->author();
        $sticky = $this->event('Sticky', [], ['post_type' => 'post', 'post_author' => $author, 'post_date' => '2023-01-01 10:00:00']);
        $newer = $this->event('Newer', [], ['post_type' => 'post', 'post_author' => $author, 'post_date' => '2024-01-01 10:00:00']);
        stick_post($sticky);

        try {
            $settings = ['post_type' => 'post', 'posts_include_authors' => [$author]];

            self::assertSame([$sticky, $newer], $this->query($settings));
            self::assertSame([$newer, $sticky], $this->query($settings + ['posts_ignore_sticky' => 'yes']));
        } finally {
            unstick_post($sticky);
        }
    }

    public function testAvoidDuplicatesSkipsPostsAnEarlierListShowed(): void
    {
        $older = $this->event('Older', [], ['post_date' => '2024-01-01 10:00:00']);
        $newest = $this->event('Newest', [], ['post_date' => '2024-02-01 10:00:00']);

        self::assertSame([$newest], $this->query(['posts_per_page' => 1]));
        self::assertSame([$older], $this->query(['posts_avoid_duplicates' => 'yes']));
    }

    public function testAvoidDuplicatesDoesNothingOnAPaginatedList(): void
    {
        $older = $this->event('Older', [], ['post_date' => '2024-01-01 10:00:00']);
        $newest = $this->event('Newest', [], ['post_date' => '2024-02-01 10:00:00']);

        $this->query(['posts_per_page' => 1]);

        self::assertSame([$newest, $older], $this->query(['posts_avoid_duplicates' => 'yes', 'pagination_type' => 'numbers']));
    }

    public function testQueryIdFilterNarrowsEveryPage(): void
    {
        $matching = [
            $this->event('One', [], [], ['upcoming' => '1']),
            $this->event('Two', [], [], ['upcoming' => '1']),
            $this->event('Three', [], [], ['upcoming' => '1']),
        ];
        $this->event('Past');

        add_filter(Hooks::LOOP_POSTS_QUERY_ARGS_FILTER, static function (array $args, string $query_id): array {
            if ('upcoming' === $query_id) {
                $args['meta_key'] = 'upcoming';
            }
            return $args;
        }, 10, 2);

        self::assertEqualsCanonicalizing($matching, $this->all_pages(['posts_query_id' => 'upcoming'], 2));
    }

    public function testAFilterCannotWidenAPublicQuery(): void
    {
        $published = $this->event('Published');
        $draft = $this->event('Draft', [], ['post_status' => 'draft']);
        $locked = $this->event('Locked', [], ['post_password' => 'secret']);

        add_filter(Hooks::LOOP_POSTS_QUERY_ARGS_FILTER, static function (array $args): array {
            $args['post_status'] = ['publish', 'draft'];
            $args['has_password'] = null;
            $args['posts_per_page'] = 1000;
            return $args;
        });

        $ids = $this->query(['posts_per_page' => 5]);

        self::assertContains($published, $ids);
        self::assertNotContains($draft, $ids);
        self::assertNotContains($locked, $ids);
    }

    public function testNoFilterSetLeavesTheQueryUnchanged(): void
    {
        $seen = null;
        add_filter(Hooks::LOOP_POSTS_QUERY_ARGS_FILTER, static function (array $args) use (&$seen): array {
            $seen = $args;
            return $args;
        });

        $this->query([]);

        foreach (['tax_query', 'meta_query', 'date_query', 'author__in', 'author__not_in', 'ignore_sticky_posts', 'meta_key'] as $key) {
            self::assertArrayNotHasKey($key, $seen, "$key must only appear when its filter is set.");
        }
    }

    /** @return array<string, string> */
    private function post_type_options(): array
    {
        $method = new ReflectionMethod(PostsDataSource::class, 'get_public_post_types');
        $method->setAccessible(true);

        return $method->invoke(LoopDataSourceRegistry::instance()->get('posts'));
    }

    /** @return int[] */
    private function query(array $settings, int $page = 1): array
    {
        $source = LoopDataSourceRegistry::instance()->get('posts');
        $public = $source->get_public_settings($settings + ['post_type' => self::TYPE, 'posts_per_page' => 50, 'exclude_current_post' => ''], [], $page);

        return array_map(static fn ($item): int => $item->get_post()->ID, $source->get_items($public)->items);
    }

    /** @return int[] Every page's posts, in order. */
    private function all_pages(array $settings, int $per_page): array
    {
        $ids = [];
        for ($page = 1; $page <= 10; $page++) {
            $found = $this->query($settings + ['posts_per_page' => $per_page, 'pagination_type' => 'numbers'], $page);
            if (!$found) {
                break;
            }
            $ids = array_merge($ids, $found);
        }

        return $ids;
    }

    /**
     * @param array<string, int[]>  $terms
     * @param array<string, mixed>  $post
     * @param array<string, string> $meta
     */
    private function event(string $title, array $terms = [], array $post = [], array $meta = []): int
    {
        $id = wp_insert_post($post + ['post_type' => self::TYPE, 'post_status' => 'publish', 'post_title' => $title], true);
        self::assertIsInt($id);
        $this->posts[] = $id;

        foreach ($terms as $taxonomy => $ids) {
            wp_set_object_terms($id, $ids, $taxonomy);
        }
        foreach ($meta as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        return $id;
    }

    private function term(string $name, string $taxonomy): int
    {
        $term = wp_insert_term($name . ' ' . bin2hex(random_bytes(3)), $taxonomy);
        self::assertIsArray($term);
        $this->terms[(int) $term['term_id']] = $taxonomy;

        return (int) $term['term_id'];
    }

    private function author(): int
    {
        $id = wp_insert_user(['user_login' => 'fx_author_' . bin2hex(random_bytes(3)), 'user_pass' => wp_generate_password(), 'role' => 'author']);
        self::assertIsInt($id);
        $this->users[] = $id;

        return $id;
    }
}
