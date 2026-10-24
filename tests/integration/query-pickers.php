<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Core\Hooks;
use Elemacy\Elementor\Controls\Query\QueryControlManager;
use Elemacy\Elementor\Controls\Query\QueryResolverInterface;
use Elemacy\Elementor\Controls\Query\QueryResolverRegistry;
use Elemacy\Elementor\Controls\Query\QueryService;
use Elemacy\Http\DTO\QueryResultDTO;
use Elemacy\Loop\Items\TermLoopItem;
use Elemacy\Loop\Items\UserLoopItem;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * The elemacy_query live-search pickers (openspec theme-builder/archive-preview,
 * "Existing post pickers behave as before"): what the post picker finds and how
 * it labels it, pinned against real posts so moving it behind the resolver
 * registry cannot change it.
 */
final class QueryPickersTest extends IntegrationTestCase
{
    private const SECRET_TYPE = 'elemacy_secret_fx';
    private const PUBLIC_TAX  = 'elemacy_pubtax_fx';
    private const PRIVATE_TAX = 'elemacy_privtax_fx';

    private QueryService $service;

    /** @var int[] */
    private array $posts = [];

    /** @var int[] */
    private static array $users = [];

    /** @var array<int, string> Term id => taxonomy. */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Container::instance()->make(QueryService::class);

        if (!post_type_exists(self::SECRET_TYPE)) {
            register_post_type(self::SECRET_TYPE, ['public' => false, 'label' => 'Secret']);
        }

        register_taxonomy(self::PUBLIC_TAX, 'post', ['public' => true, 'labels' => ['name' => 'Fx Publics', 'singular_name' => 'Fx Public']]);
        register_taxonomy(self::PRIVATE_TAX, 'post', ['public' => false, 'labels' => ['name' => 'Fx Privates', 'singular_name' => 'Fx Private']]);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->posts as $id) {
                wp_delete_post($id, true);
            }
            $this->posts = [];
            require_once ABSPATH . 'wp-admin/includes/user.php';

            foreach ($this->terms as $id => $taxonomy) {
                wp_delete_term($id, $taxonomy);
            }
            $this->terms = [];
            unregister_taxonomy(self::PUBLIC_TAX);
            unregister_taxonomy(self::PRIVATE_TAX);

            foreach (self::$users as $id) {
                wp_delete_user($id);
            }
            self::$users = [];
            unregister_post_type(self::SECRET_TYPE);
        } finally {
            parent::tearDown();
        }
    }

    public function testSearchFindsPublishedPostsOfPublicTypesNewestFirstWithTypedLabels(): void
    {
        $older = $this->post('Zulu espresso guide', 'post', 'publish', '2024-01-01 10:00:00');
        $newer = $this->post('Alpha espresso news', 'post', 'publish', '2024-03-01 10:00:00');
        $page  = $this->post('Middle espresso page', 'page', 'publish', '2024-02-01 10:00:00');
        $this->post('Draft espresso', 'post', 'draft', '2024-04-01 10:00:00');
        $this->post('Secret espresso', self::SECRET_TYPE, 'publish', '2024-05-01 10:00:00');

        $results = $this->service->search(['object' => 'post'], 'espresso');

        self::assertSame([$newer, $page, $older], array_map(static fn ($r) => $r->id, $results), 'Newest first; public types only; published only.');
        self::assertSame(
            ['Alpha espresso news (Post)', 'Middle espresso page (Page)', 'Zulu espresso guide (Post)'],
            array_map(static fn ($r) => $r->text, $results)
        );
    }

    public function testSearchNarrowsToTheRequestedPublicTypesAndFallsBackWhenNoneIsValid(): void
    {
        $post = $this->post('Narrow latte post', 'post');
        $page = $this->post('Narrow latte page', 'page');

        $pages_only = $this->service->search(['object' => 'post', 'query' => ['post_type' => ['page']]], 'latte');
        self::assertSame([$page], array_map(static fn ($r) => $r->id, $pages_only));

        $crafted = $this->service->search(['object' => 'post', 'query' => ['post_type' => [self::SECRET_TYPE]]], 'latte');
        $ids = array_map(static fn ($r) => $r->id, $crafted);
        sort($ids);
        $expected = [$post, $page];
        sort($expected);
        self::assertSame($expected, $ids, 'A non-public type is never reachable; an all-invalid request falls back to every public type.');
    }

    public function testSearchHonoursARequestedPostStatus(): void
    {
        $draft = $this->post('Status mocha draft', 'post', 'draft');
        $this->post('Status mocha live', 'post', 'publish');

        $results = $this->service->search(['object' => 'post', 'query' => ['post_status' => ['draft']]], 'mocha');

        self::assertSame([$draft], array_map(static fn ($r) => $r->id, $results));
    }

    public function testSearchIsCappedAndEmptyForBlankTermsAndUnknownObjects(): void
    {
        for ($i = 0; $i < QueryService::MAX_RESULTS + 3; $i++) {
            $this->post('Capped macchiato ' . $i, 'post');
        }

        self::assertCount(QueryService::MAX_RESULTS, $this->service->search(['object' => 'post'], 'macchiato'));
        self::assertSame([], $this->service->search(['object' => 'post'], '   '));
        self::assertSame([], $this->service->search(['object' => 'nonsense'], 'macchiato'));
        self::assertSame([], $this->service->search([], 'macchiato'));
    }

    public function testTitlesLabelStoredIdsOfAnyStatusButNeverOfNonPublicTypes(): void
    {
        $live   = $this->post('Titled cortado', 'post');
        $draft  = $this->post('Titled cortado draft', 'post', 'draft');
        $secret = $this->post('Titled secret', self::SECRET_TYPE);
        $untitled = $this->post('', 'post');

        $titles = $this->service->titles(['object' => 'post'], [$live, $draft, $secret, $untitled, 0, 999999]);

        self::assertSame('Titled cortado (Post)', $titles[$live]);
        self::assertSame('Titled cortado draft (Post)', $titles[$draft]);
        self::assertSame('(no title) (Post)', $titles[$untitled]);
        self::assertArrayNotHasKey($secret, $titles);
        self::assertSame([], $this->service->titles(['object' => 'post'], []));
        self::assertSame([], $this->service->titles(['object' => 'nonsense'], [$live]));
    }

    public function testTheContainerSharesOneRegistryThatKnowsThePostResolver(): void
    {
        $container = Container::instance();

        self::assertSame($container->make(QueryResolverRegistry::class), $container->make(QueryResolverRegistry::class));
        self::assertContains(QueryService::OBJECT_POST, $container->make(QueryResolverRegistry::class)->keys());
    }

    public function testAnAddOnRegistersItsOwnResolverOnTheRegisterAction(): void
    {
        $listener = static function (QueryResolverRegistry $registry): void {
            $registry->register(new class() implements QueryResolverInterface {
                public function key(): string
                {
                    return 'addon_fx';
                }

                public function capability(): string
                {
                    return 'edit_posts';
                }

                public function search(array $query, string $term): array
                {
                    $dto = new QueryResultDTO();
                    $dto->id = 1;
                    $dto->text = 'Found ' . $term;

                    return [$dto];
                }

                public function titles(array $query, array $ids): array
                {
                    return [];
                }
            });
        };
        add_action(Hooks::QUERY_RESOLVERS_REGISTER_ACTION, $listener);

        try {
            $service = new QueryService(QueryResolverRegistry::from_config());
            $results = $service->search(['object' => 'addon_fx'], 'x');
        } finally {
            remove_action(Hooks::QUERY_RESOLVERS_REGISTER_ACTION, $listener);
        }

        self::assertSame(['Found x'], array_map(static fn ($r) => $r->text, $results), 'An add-on resolver answers through the same service.');
    }

    public function testAjaxHandlersAnswerEditorsAndNoOneWithoutTheResolversCapability(): void
    {
        $manager = Container::instance()->make(QueryControlManager::class);
        $post    = $this->post('Handler ristretto', 'post');
        $config  = ['object' => 'post'];

        $as_editor = $manager->handle_autocomplete(['autocomplete' => $config, 'q' => 'ristretto']);
        self::assertSame([$post], array_column($as_editor['results'], 'id'));
        self::assertSame([$post => 'Handler ristretto (Post)'], $manager->handle_titles(['get_titles' => $config, 'id' => [$post]]));

        $subscriber = self::factory_user('subscriber');
        wp_set_current_user($subscriber);
        self::assertSame(['results' => []], $manager->handle_autocomplete(['autocomplete' => $config, 'q' => 'ristretto']));
        self::assertSame([], $manager->handle_titles(['get_titles' => $config, 'id' => [$post]]));

        wp_set_current_user(get_user_by('login', 'elemacy_test_admin')->ID);
        self::assertSame(['results' => []], $manager->handle_autocomplete(['autocomplete' => ['object' => 'nonsense'], 'q' => 'ristretto']));
        self::assertSame([], $manager->handle_titles(['get_titles' => ['object' => 'nonsense'], 'id' => [$post]]));
    }

    public function testTermSearchFindsTermsOfPublicTaxonomiesOnlyLabelledWithTheirTaxonomy(): void
    {
        $category = $this->term('Brew Cat', 'category');
        $custom   = $this->term('Brew Custom', self::PUBLIC_TAX);
        $tag      = $this->term('Brew Tag', 'post_tag');
        $this->term('Brew Secret', self::PRIVATE_TAX);

        $results = $this->service->search(['object' => QueryService::OBJECT_TERM], 'brew');

        self::assertSame([$category, $custom, $tag], array_map(static fn ($r) => $r->id, $results), 'Public taxonomies only, alphabetical, terms with no posts included.');
        self::assertSame(['Brew Cat (Category)', 'Brew Custom (Fx Public)', 'Brew Tag (Tag)'], array_map(static fn ($r) => $r->text, $results));
    }

    public function testTermSearchNarrowsToRequestedTaxonomiesButNeverReachesAPrivateOne(): void
    {
        $category = $this->term('Narrow Cat', 'category');
        $custom   = $this->term('Narrow Custom', self::PUBLIC_TAX);
        $secret   = $this->term('Narrow Secret', self::PRIVATE_TAX);

        $only_category = $this->service->search(['object' => 'term', 'query' => ['taxonomy' => ['category']]], 'narrow');
        self::assertSame([$category], array_map(static fn ($r) => $r->id, $only_category));

        $crafted = $this->service->search(['object' => 'term', 'query' => ['taxonomy' => [self::PRIVATE_TAX]]], 'narrow');
        $ids = array_map(static fn ($r) => $r->id, $crafted);
        self::assertNotContains($secret, $ids, 'A private taxonomy is never reachable, even when asked for by name.');
        self::assertContains($category, $ids, 'An all-invalid request falls back to every public taxonomy.');
        self::assertContains($custom, $ids);
    }

    public function testTermTitlesLabelStoredIdsAndSkipPrivateAndDeletedTerms(): void
    {
        $category = $this->term('Titled Cat', 'category');
        $secret   = $this->term('Titled Secret', self::PRIVATE_TAX);
        $gone     = $this->term('Titled Gone', 'post_tag');
        wp_delete_term($gone, 'post_tag');
        unset($this->terms[$gone]);

        $titles = $this->service->titles(['object' => 'term'], [$category, $secret, $gone, 999999]);

        self::assertSame([$category => 'Titled Cat (Category)'], $titles);
    }

    public function testTermSearchIsCapped(): void
    {
        for ($i = 0; $i < QueryService::MAX_RESULTS + 3; $i++) {
            $this->term('Capped term ' . $i, 'post_tag');
        }

        self::assertCount(QueryService::MAX_RESULTS, $this->service->search(['object' => 'term'], 'capped term'));
    }

    public function testUserSearchDefaultsToAuthorsAndShowsDisplayNamesOnly(): void
    {
        $author      = $this->user('author', 'Brew Author');
        $contributor = $this->user('contributor', 'Brew Contributor');
        $subscriber  = $this->user('subscriber', 'Brew Subscriber');

        $results = $this->service->search(['object' => QueryService::OBJECT_USER], 'brew');

        self::assertSame([$author, $contributor], array_map(static fn ($r) => $r->id, $results), 'Authors by default, alphabetical; a subscriber cannot be an author.');
        self::assertSame(['Brew Author', 'Brew Contributor'], array_map(static fn ($r) => $r->text, $results));
        self::assertNotContains($subscriber, array_map(static fn ($r) => $r->id, $results));
    }

    public function testUserSearchWithWhoAllFindsAnyUser(): void
    {
        $subscriber = $this->user('subscriber', 'Everyone Subscriber');

        self::assertSame([], $this->service->search(['object' => 'user'], 'everyone subscriber'));
        self::assertSame(
            [$subscriber],
            array_map(static fn ($r) => $r->id, $this->service->search(['object' => 'user', 'query' => ['who' => 'all']], 'everyone subscriber'))
        );
        self::assertSame([], $this->service->search(['object' => 'user', 'query' => ['who' => 'bogus']], 'everyone subscriber'), 'An unknown scope falls back to authors.');
    }

    public function testUserSearchMatchesTheDisplayNameOnlyNeverTheLoginOrEmail(): void
    {
        $id   = $this->user('author', 'Visible Name', 'hidden_login_fx', 'hidden.mail.fx@example.test');
        $text = array_map(static fn ($r) => $r->text, $this->service->search(['object' => 'user'], 'visible name'));

        self::assertSame(['Visible Name'], $text);
        self::assertSame([], $this->service->search(['object' => 'user'], 'hidden_login_fx'), 'A login is not searchable.');
        self::assertSame([], $this->service->search(['object' => 'user'], 'hidden.mail.fx'), 'An email is not searchable.');
        self::assertSame([$id => 'Visible Name'], $this->service->titles(['object' => 'user'], [$id]), 'Only the display name is ever returned.');
    }

    public function testUserTitlesFollowTheSameScopeAndSkipDeletedUsers(): void
    {
        $author     = $this->user('author', 'Scoped Author');
        $subscriber = $this->user('subscriber', 'Scoped Subscriber');

        self::assertSame([$author => 'Scoped Author'], $this->service->titles(['object' => 'user'], [$author, $subscriber, 999999]));
        self::assertSame(
            [$author => 'Scoped Author', $subscriber => 'Scoped Subscriber'],
            $this->service->titles(['object' => 'user', 'query' => ['who' => 'all']], [$author, $subscriber])
        );
    }

    public function testTheTermAndUserResolversAreRegisteredAndAnswerEditors(): void
    {
        $keys = Container::instance()->make(QueryResolverRegistry::class)->keys();

        self::assertContains(QueryService::OBJECT_TERM, $keys);
        self::assertContains(QueryService::OBJECT_USER, $keys);

        $manager = Container::instance()->make(QueryControlManager::class);
        $term    = $this->term('Ajax Cat', 'category');
        $out     = $manager->handle_autocomplete(['autocomplete' => ['object' => 'term'], 'q' => 'ajax cat']);

        self::assertSame([$term], array_column($out['results'], 'id'));

        wp_set_current_user(self::factory_user('subscriber'));
        self::assertSame(['results' => []], $manager->handle_autocomplete(['autocomplete' => ['object' => 'term'], 'q' => 'ajax cat']));
    }

    public function testTheTermsLoopPreviewKeepsResolvingAStoredTermIdAndFallsBackToTheFirstTerm(): void
    {
        $source = LoopDataSourceRegistry::instance()->get('terms');
        $term   = $this->term('Loop Preview Term', 'post_tag');

        $stored = $source->resolve_preview_item(['preview_taxonomy' => 'category', 'preview_term' => (string) $term]);
        self::assertInstanceOf(TermLoopItem::class, $stored);
        self::assertSame($term, $stored->get_term()->term_id, 'A template saved with a term id previews that term, whatever the taxonomy select says.');

        $empty = $source->resolve_preview_item(['preview_taxonomy' => 'post_tag', 'preview_term' => '']);
        self::assertInstanceOf(TermLoopItem::class, $empty, 'An empty choice still previews the first term.');
        self::assertSame('post_tag', $empty->get_term()->taxonomy);
    }

    public function testTheUsersLoopPreviewKeepsResolvingAStoredUserIdIncludingOneWithNoPosts(): void
    {
        $source     = LoopDataSourceRegistry::instance()->get('users');
        $subscriber = $this->user('subscriber', 'Loop Preview Subscriber');

        $stored = $source->resolve_preview_item(['preview_user' => (string) $subscriber]);
        self::assertInstanceOf(UserLoopItem::class, $stored);
        self::assertSame($subscriber, $stored->get_user()->ID);

        self::assertInstanceOf(UserLoopItem::class, $source->resolve_preview_item(['preview_user' => '']), 'An empty choice still previews the first user.');
    }

    public function testTheLoopPreviewTermAndUserPickersSearchLiveInsteadOfListingAFixedSet(): void
    {
        $controls = Plugin::instance()->documents->get($this->documents->create('Loop preview controls', 'loop'), false)->get_controls();

        self::assertSame('elemacy_query', $controls['preview_term']['type']);
        self::assertSame('term', $controls['preview_term']['autocomplete']['object']);
        self::assertEmpty($controls['preview_term']['options'] ?? [], 'No pre-loaded option list.');
        self::assertEmpty($controls['preview_user']['options'] ?? [], 'No pre-loaded option list.');
        self::assertSame(['preview_data_source' => 'terms'], $controls['preview_term']['condition']);

        self::assertSame('elemacy_query', $controls['preview_user']['type']);
        self::assertSame('user', $controls['preview_user']['autocomplete']['object']);
        self::assertSame('all', $controls['preview_user']['autocomplete']['query']['who'], 'The Users loop keeps listing any user, not only authors.');
        self::assertSame(['preview_data_source' => 'users'], $controls['preview_user']['condition']);
    }

    public function testALoopPreviewUserWithNoPostsCanBeFoundAndLabelledByNameOnly(): void
    {
        $subscriber = $this->user('subscriber', 'Loop Quiet Reader', 'quiet_login_fx', 'quiet.mail.fx@example.test');
        $config     = ['object' => 'user', 'query' => ['who' => 'all']];

        self::assertSame([$subscriber], array_map(static fn ($r) => $r->id, $this->service->search($config, 'quiet reader')));
        self::assertSame([$subscriber => 'Loop Quiet Reader'], $this->service->titles($config, [$subscriber]));
        self::assertSame([], $this->service->search($config, 'quiet_login_fx'), 'Never by login.');
    }

    private static function factory_user(string $role): int
    {
        $id = wp_insert_user(['user_login' => 'qp_' . $role . '_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'role' => $role]);
        self::assertIsInt($id);
        self::$users[] = $id;

        return $id;
    }

    private function term(string $name, string $taxonomy): int
    {
        $created = wp_insert_term($name, $taxonomy);
        self::assertIsArray($created);
        $id = (int) $created['term_id'];
        $this->terms[$id] = $taxonomy;

        return $id;
    }

    private function user(string $role, string $display_name, string $login = '', string $email = ''): int
    {
        $suffix = wp_generate_password(6, false);
        $id = wp_insert_user([
            'user_login'   => '' !== $login ? $login : 'qp_' . $suffix,
            'user_email'   => '' !== $email ? $email : 'qp_' . $suffix . '@example.test',
            'user_pass'    => wp_generate_password(),
            'display_name' => $display_name,
            'role'         => $role,
        ]);
        self::assertIsInt($id);
        self::$users[] = $id;

        return $id;
    }

    private function post(string $title, string $type, string $status = 'publish', string $date = ''): int
    {
        $args = ['post_title' => $title, 'post_content' => 'Body', 'post_type' => $type, 'post_status' => $status];

        if ('' !== $date) {
            $args['post_date'] = $date;
            $args['post_date_gmt'] = get_gmt_from_date($date);
        }

        $id = wp_insert_post($args, true);
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }
}
