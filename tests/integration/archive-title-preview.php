<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Core\Hooks;
use Elemacy\Elementor\Preview\PreviewManager;
use Elemacy\Elementor\Controls\Query\QueryResolverRegistry;
use Elemacy\Modules\DynamicTags\Tags\Archive\ArchiveTitle;
use Elemacy\Modules\ThemeBuilder\Documents\ArchiveDocument;
use Elemacy\Modules\ThemeBuilder\Services\ArchivePreviewQuery;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;
use WP_Query;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * The Archive Title tag and the Archive template preview (openspec
 * dynamic-tags/archive-title and theme-builder/archive-preview), on real
 * WordPress requests and the real editor query switch.
 */
final class ArchiveTitlePreviewTest extends IntegrationTestCase
{
    private const PUBLIC_TAX  = 'elemacy_apt_pub_fx';
    private const PRIVATE_TAX = 'elemacy_apt_priv_fx';

    private RequestSimulator $request;

    /** @var array<int, string> Term id => taxonomy. */
    private array $terms = [];

    /** @var int[] */
    private array $posts = [];

    /** @var int[] */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RequestSimulator();
        register_taxonomy(self::PUBLIC_TAX, 'post', ['public' => true, 'labels' => ['name' => 'Apt Publics', 'singular_name' => 'Apt Public']]);
        register_taxonomy(self::PRIVATE_TAX, 'post', ['public' => false]);
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
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ($this->users as $id) {
                wp_delete_user($id);
            }
            unregister_taxonomy(self::PUBLIC_TAX);
            unregister_taxonomy(self::PRIVATE_TAX);
            $this->posts = $this->terms = $this->users = [];
        } finally {
            parent::tearDown();
        }
    }

    /* ── The tag on real requests ─────────────────────────────────────── */

    public function testTheTagShowsTheSearchHeadingOnARealSearchRequest(): void
    {
        $this->request->visit('/?s=coffee');

        self::assertTrue(is_search());
        self::assertSame('Search Results for: coffee', $this->title(true));
        self::assertSame('Search Results for: coffee', $this->title(false), 'Remove Prefix does not apply to the search heading.');
    }

    public function testTheTagOutputsASearchTermWithMarkupAsInertText(): void
    {
        $this->request->visit('/?s=' . rawurlencode('<script>alert(1)</script> & "x"'));

        $output = $this->title(true);

        self::assertStringNotContainsString('<script', $output);
        self::assertStringContainsString('Search Results for: ', $output);
        self::assertStringContainsString('alert(1)', $output, 'The term is still shown, only inert.');
    }

    public function testARealCategoryArchiveStillShowsItsTitleWithAndWithoutThePrefix(): void
    {
        $category = $this->term('Visitor News', 'category');
        $this->post('Visitor news post', $category);

        $this->request->visit(get_term_link($category, 'category'));

        self::assertTrue(is_category());
        self::assertSame('Visitor News', $this->plain(true));
        self::assertSame('Category: Visitor News', $this->plain(false));
    }

    /* ── The preview query for each Preview Type ─────────────────────── */

    public function testNoStoredTypeAndPostTypeGiveExactlyTheArgsTheArchivePreviewAlwaysHad(): void
    {
        $legacy = [
            'post_type'           => 'post',
            'posts_per_page'      => (int) get_option('posts_per_page'),
            'ignore_sticky_posts' => true,
        ];

        self::assertSame($legacy, (new ArchivePreviewQuery())->build([]));
        self::assertSame($legacy, (new ArchivePreviewQuery())->build(['type' => 'post_type', 'post_type' => 'post']));
        self::assertSame(['post_type' => 'page'] + $legacy, (new ArchivePreviewQuery())->build(['post_type' => 'page']));
        self::assertSame($legacy, (new ArchivePreviewQuery())->build(['post_type' => 'not_a_type']));
    }

    public function testTermTypeMapsCategoryTagAndCustomTaxonomyToTheirOwnQueries(): void
    {
        $query    = new ArchivePreviewQuery();
        $category = $this->term('Map Cat', 'category');
        $tag      = $this->term('Map Tag', 'post_tag');
        $custom   = $this->term('Map Custom', self::PUBLIC_TAX);

        self::assertSame($this->legacy() + ['cat' => $category], $query->build(['type' => 'term', 'term' => (string) $category]));

        self::assertSame($this->legacy() + ['tag_id' => $tag], $query->build(['type' => 'term', 'term' => $tag]));

        $custom_args = $query->build(['type' => 'term', 'term' => $custom]);
        self::assertSame(
            [['taxonomy' => self::PUBLIC_TAX, 'field' => 'term_id', 'terms' => [$custom]]],
            $custom_args['tax_query']
        );
        self::assertSame(['post'], $custom_args['post_type'], 'A taxonomy archive lists the post types the taxonomy belongs to.');
    }

    public function testTermTypeFallsBackToThePostTypeArgsWhenTheTermIsUnusable(): void
    {
        $query   = new ArchivePreviewQuery();
        $secret  = $this->term('Map Secret', self::PRIVATE_TAX);
        $deleted = $this->term('Map Deleted', 'post_tag');
        wp_delete_term($deleted, 'post_tag');
        unset($this->terms[$deleted]);

        foreach ([$secret, $deleted, 0, '', 'abc', 999999] as $bad) {
            self::assertSame($this->legacy(), $query->build(['type' => 'term', 'term' => $bad]), 'Unusable term: ' . var_export($bad, true));
        }
    }

    public function testAuthorTypeUsesTheAuthorIdAndFallsBackWhenTheUserIsGone(): void
    {
        $query  = new ArchivePreviewQuery();
        $author = $this->user('author');

        self::assertSame($author, $query->build(['type' => 'author', 'author' => (string) $author])['author']);

        foreach ([0, '', 999999] as $bad) {
            self::assertSame($this->legacy(), $query->build(['type' => 'author', 'author' => $bad]), 'Unusable author: ' . var_export($bad, true));
        }
    }

    public function testMonthTypeReadsYearAndMonthAndFallsBackOnGarbage(): void
    {
        $query = new ArchivePreviewQuery();

        foreach (['2024-03-15', '2024-03', '2024-03-15 10:30'] as $value) {
            $args = $query->build(['type' => 'month', 'month' => $value]);
            self::assertSame(2024, $args['year'], $value);
            self::assertSame(3, $args['monthnum'], $value);
        }

        foreach (['', 'garbage', '2024-13-01', '2024-00-10', '24-03-01'] as $bad) {
            self::assertSame($this->legacy(), $query->build(['type' => 'month', 'month' => $bad]), 'Unusable month: ' . $bad);
        }
    }

    public function testAnUnknownTypeFallsBackToThePostTypeArgs(): void
    {
        self::assertSame($this->legacy(), (new ArchivePreviewQuery())->build(['type' => 'nonsense']));
    }

    /* ── The preview shows real titles through the editor query switch ── */

    public function testEachPreviewTypeMakesWordPressSeeARealArchiveWithItsRealTitle(): void
    {
        $category = $this->term('Prev News', 'category');
        $tag      = $this->term('Prev Tag', 'post_tag');
        $custom   = $this->term('Prev Custom', self::PUBLIC_TAX);
        $author   = $this->user('author', 'Prev Writer');
        $this->post('March post', $category, '2024-03-15 10:00:00');

        $cases = [
            'category' => [[ArchiveDocument::PREVIEW_TERM => (string) $category], 'term', 'is_category', 'Prev News', 'Category: Prev News'],
            'tag'      => [[ArchiveDocument::PREVIEW_TERM => (string) $tag], 'term', 'is_tag', 'Prev Tag', 'Tag: Prev Tag'],
            'custom'   => [[ArchiveDocument::PREVIEW_TERM => (string) $custom], 'term', 'is_tax', 'Prev Custom', 'Apt Public: Prev Custom'],
            'author'   => [[ArchiveDocument::PREVIEW_AUTHOR => (string) $author], 'author', 'is_author', 'Prev Writer', 'Author: Prev Writer'],
            'month'    => [[ArchiveDocument::PREVIEW_MONTH => '2024-03-15'], 'month', 'is_month', 'March 2024', 'Month: March 2024'],
        ];

        foreach ($cases as $name => [$settings, $type, $flag, $stripped, $full]) {
            $result = $this->inEditorPreview($settings + [ArchiveDocument::PREVIEW_TYPE => $type], function () use ($flag) {
                return [$flag(), $this->plain(true), $this->plain(false)];
            });

            self::assertTrue($result[0], $name . ': WordPress must treat the preview as ' . $flag . '.');
            self::assertSame($stripped, $result[1], $name . ' with Remove Prefix on');
            self::assertSame($full, $result[2], $name . ' with Remove Prefix off');
        }
    }

    public function testThePostTypePreviewStillShowsTheGenericTitleItAlwaysDid(): void
    {
        $title = $this->inEditorPreview([], fn () => $this->plain(true));

        self::assertSame('Archives', $title);
    }

    /* ── The document wires the settings into the preview ─────────────── */

    public function testTheArchiveDocumentPreviewsTheSavedTypeAndOldDocumentsStillPreviewThePostType(): void
    {
        $category = $this->term('Doc News', 'category');

        $legacy_id = $this->documents->create('Legacy archive', 'archive');
        $legacy    = Plugin::instance()->documents->get($legacy_id);
        self::assertSame($this->legacy(), $legacy->get_preview_query_args(), 'A document without the new settings previews exactly as before.');

        $typed_id = $this->documents->create('Typed archive', 'archive');
        $typed    = Plugin::instance()->documents->get($typed_id);
        $typed->save(['settings' => [
            ArchiveDocument::PREVIEW_TYPE => 'term',
            ArchiveDocument::PREVIEW_TERM => (string) $category,
        ]]);
        $typed = Plugin::instance()->documents->get($typed_id, false);

        self::assertSame($category, $typed->get_preview_query_args()['cat']);
    }

    public function testThePreviewSettingsRegisterTheTypePickerAndShowEachControlOnlyForItsType(): void
    {
        $id       = $this->documents->create('Controls archive', 'archive');
        $controls = Plugin::instance()->documents->get($id, false)->get_controls();

        self::assertSame('select', $controls[ArchiveDocument::PREVIEW_TYPE]['type']);
        self::assertSame('post_type', $controls[ArchiveDocument::PREVIEW_TYPE]['default'], 'No stored type means the post type archive.');
        self::assertSame('post', $controls[ArchiveDocument::PREVIEW_POST_TYPE]['default'], 'The old post type control keeps its key and default.');

        self::assertSame('elemacy_query', $controls[ArchiveDocument::PREVIEW_TERM]['type']);
        self::assertSame('term', $controls[ArchiveDocument::PREVIEW_TERM]['autocomplete']['object']);
        self::assertSame('elemacy_query', $controls[ArchiveDocument::PREVIEW_AUTHOR]['type']);
        self::assertSame('user', $controls[ArchiveDocument::PREVIEW_AUTHOR]['autocomplete']['object']);
        self::assertSame('date_time', $controls[ArchiveDocument::PREVIEW_MONTH]['type']);

        foreach ([
            ArchiveDocument::PREVIEW_POST_TYPE => 'post_type',
            ArchiveDocument::PREVIEW_TERM      => 'term',
            ArchiveDocument::PREVIEW_AUTHOR    => 'author',
            ArchiveDocument::PREVIEW_MONTH     => 'month',
        ] as $control => $type) {
            self::assertSame([ArchiveDocument::PREVIEW_TYPE => $type], $controls[$control]['condition'], $control . ' shows only for its own type.');
        }
    }

    /* ── Visitors are unaffected ──────────────────────────────────────── */

    public function testAStoredPreviewTypeNeverChangesWhatAVisitorSeesOnARealArchive(): void
    {
        $category = $this->term('Real Archive', 'category');
        $other    = $this->term('Other Archive', 'category');
        $this->post('Real archive post', $category);

        $id = $this->documents->create('Stored archive', 'archive');
        Plugin::instance()->documents->get($id)->save(['settings' => [
            ArchiveDocument::PREVIEW_TYPE => 'term',
            ArchiveDocument::PREVIEW_TERM => (string) $other,
        ]]);

        $this->request->visit(get_term_link($category, 'category'));

        self::assertSame('Real Archive', $this->plain(true), 'The visitor sees the page\'s own archive, not the stored preview.');
    }

    public function testThePickerRegistryLoadsNothingUntilAPickerSearchRuns(): void
    {
        $before   = did_action(Hooks::QUERY_RESOLVERS_REGISTER_ACTION);
        $registry = QueryResolverRegistry::from_config();
        (new ArchivePreviewQuery())->build(['type' => 'term', 'term' => 1]);

        self::assertSame($before, did_action(Hooks::QUERY_RESOLVERS_REGISTER_ACTION), 'Building the registry and the preview args loads no resolver.');

        $registry->keys();
        self::assertSame($before + 1, did_action(Hooks::QUERY_RESOLVERS_REGISTER_ACTION), 'The first lookup loads them, once.');
    }

    /* ── Helpers ─────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function legacy(): array
    {
        return [
            'post_type'           => 'post',
            'posts_per_page'      => (int) get_option('posts_per_page'),
            'ignore_sticky_posts' => true,
        ];
    }

    /** The tag's text without markup: WordPress wraps an archive's name in a span. */
    private function plain(bool $remove_prefix): string
    {
        return trim(wp_strip_all_tags($this->title($remove_prefix)));
    }

    private function title(bool $remove_prefix): string
    {
        $tag = new ArchiveTitle(['id' => 'apt1', 'settings' => ['remove_prefix' => $remove_prefix ? 'yes' : '']]);

        ob_start();
        $tag->render();

        return trim((string) ob_get_clean());
    }

    /**
     * Runs the callback while the editor previews a real Archive document saved
     * with these settings: the same hooks the editor fires around a dynamic tag's
     * render, through the real PreviewManager.
     *
     * @param array<string, mixed> $settings
     *
     * @return mixed
     */
    private function inEditorPreview(array $settings, callable $callback)
    {
        $id = $this->documents->create('Archive preview ' . wp_generate_password(4, false), 'archive');
        Plugin::instance()->documents->get($id)->save(['settings' => $settings]);
        Plugin::instance()->documents->get($id, false); // The editor loads the saved document afresh.

        $manager       = Container::instance()->make(PreviewManager::class);
        $previous_post = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = get_post($id);

        $manager->before_tags_render();

        try {
            return $callback();
        } finally {
            $manager->after_tags_render();
            $GLOBALS['post'] = $previous_post;
        }
    }

    private function term(string $name, string $taxonomy): int
    {
        $created = wp_insert_term($name, $taxonomy);
        self::assertIsArray($created);
        $id = (int) $created['term_id'];
        $this->terms[$id] = $taxonomy;

        return $id;
    }

    private function post(string $title, int $category, string $date = ''): int
    {
        $args = ['post_title' => $title, 'post_content' => 'Body', 'post_status' => 'publish', 'post_category' => [$category]];

        if ('' !== $date) {
            $args['post_date'] = $date;
            $args['post_date_gmt'] = get_gmt_from_date($date);
        }

        $id = wp_insert_post($args);
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }

    private function user(string $role, string $display_name = ''): int
    {
        $suffix = wp_generate_password(6, false);
        $id = wp_insert_user([
            'user_login'   => 'apt_' . $suffix,
            'user_email'   => 'apt_' . $suffix . '@example.test',
            'user_pass'    => wp_generate_password(),
            'display_name' => '' !== $display_name ? $display_name : 'Apt ' . $suffix,
            'role'         => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;

        return $id;
    }
}
