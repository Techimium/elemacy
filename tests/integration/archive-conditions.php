<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionManager;
use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * "Post Type Archive" decides from the archive WordPress actually queried,
 * never from whichever post it lists first.
 */
final class ArchiveConditionsTest extends IntegrationTestCase
{
    private const BOOK = 'elemacy_test_book';
    private const SHARED = 'elemacy_test_topic';
    private const GENRE = 'elemacy_test_genre';

    private RequestSimulator $request;
    /** @var int[] */
    private array $posts = [];
    /** @var array<int, string> term id => taxonomy */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();
        register_post_type(self::BOOK, ['public' => true, 'has_archive' => true, 'label' => 'Books']);
        register_taxonomy(self::GENRE, [self::BOOK], ['public' => true, 'query_var' => true]);
        register_taxonomy(self::SHARED, ['post', self::BOOK], ['public' => true, 'query_var' => true]);
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
            foreach ($this->posts as $id) {
                wp_delete_post($id, true);
            }
            foreach ($this->terms as $id => $taxonomy) {
                wp_delete_term($id, $taxonomy);
            }
            unregister_taxonomy(self::SHARED);
            unregister_taxonomy(self::GENRE);
            unregister_post_type(self::BOOK);
        } finally {
            parent::tearDown();
        }
    }

    private function term(string $taxonomy, string $name): int
    {
        $term = wp_insert_term($name . ' ' . wp_generate_password(6, false), $taxonomy);
        self::assertIsArray($term);
        $this->terms[(int) $term['term_id']] = $taxonomy;

        return (int) $term['term_id'];
    }

    private function post(string $type, ?int $term = null, string $taxonomy = '', string $date = ''): int
    {
        $id = wp_insert_post(array_filter([
            'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Archive condition ' . $type, 'post_date' => $date,
        ]), true);
        self::assertIsInt($id);
        $this->posts[] = $id;
        if (null !== $term) {
            wp_set_object_terms($id, [$term], $taxonomy);
        }

        return $id;
    }

    private function archive_matches(string $value): bool
    {
        return ConditionManager::instance()->get('archive/post_type')->check(
            ConditionRuleDTO::from_array(['type' => 'archive/post_type', 'value' => $value])
        );
    }

    public function testASharedTaxonomyArchiveBelongsToPostsWhateverItsNewestItemIs(): void
    {
        $topic = $this->term(self::SHARED, 'Shared topic');
        $this->post('post', $topic, self::SHARED, '2020-01-01 00:00:00');
        $this->post(self::BOOK, $topic, self::SHARED);

        $this->request->visit((string) get_term_link($topic, self::SHARED));

        self::assertSame(self::BOOK, get_post_type(), 'Sanity: the newest item listed is a book.');
        self::assertTrue($this->archive_matches('post'), '"Posts" still matches a taxonomy registered for posts.');
        self::assertTrue($this->archive_matches(self::BOOK), 'The book post type matches it too.');
    }

    public function testACustomPostTypesOwnTaxonomyArchiveBelongsToIt(): void
    {
        $genre = $this->term(self::GENRE, 'Genre');
        $this->post(self::BOOK, $genre, self::GENRE);

        $this->request->visit((string) get_term_link($genre, self::GENRE));

        self::assertTrue(is_tax(self::GENRE), 'Sanity: a genre archive.');
        self::assertTrue($this->archive_matches(self::BOOK));
        self::assertFalse($this->archive_matches('post'), 'A taxonomy registered only for books is not a Posts archive.');
    }

    public function testAnAuthorArchiveBelongsToPostsOnly(): void
    {
        $this->post('post');

        $this->request->visit((string) get_author_posts_url(get_current_user_id()));

        self::assertTrue(is_author(), 'Sanity: an author archive.');
        self::assertTrue($this->archive_matches('post'));
        self::assertFalse($this->archive_matches(self::BOOK));
    }
}
