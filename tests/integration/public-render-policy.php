<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\DynamicTags\Tags\User\UserEmail;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elemacy\Modules\Widgets\DataSources\PostsDataSource;
use Elemacy\Modules\Widgets\DataSources\TaxonomyDataSource;
use Elemacy\Modules\Widgets\DataSources\UsersDataSource;
use Elemacy\Modules\Widgets\Services\LoopContext;
use Elemacy\Modules\Widgets\Services\LoopDataSourceRegistry;
use Elemacy\Modules\Widgets\Services\PublicRenderPolicy;
use Elemacy\Modules\Widgets\Support\PublicLoopLimits;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\ObservedLoopSource;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/ObservedLoopSource.php';

/**
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater
 * field type (task 9.6: excluded from the Free-only CI job that runs
 * without a licensed ACF Pro installation; run directly with
 * --acf-plugin for full local coverage).
 *
 * @group requires-acf-pro
 */
final class PublicRenderPolicyTest extends IntegrationTestCase
{
    private ArchitectureCatalog $fixtures;
    private array $catalog;
    private LoopDataSourceRegistry $registry;
    private PublicRenderPolicy $policy;

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
        $this->registry = new LoopDataSourceRegistry();
        foreach ([new PostsDataSource(), new TaxonomyDataSource(), new UsersDataSource()] as $source) {
            $this->registry->register(new ObservedLoopSource($source));
        }
        $this->policy = new PublicRenderPolicy(TypeRegistry::instance(), $this->registry);
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        try {
            $this->fixtures->cleanup();
        } finally {
            parent::tearDown();
        }
    }

    public function testUserRoleAndEmailRemainAuthoredWhileVisitorIdentityComesFromSession(): void
    {
        $source = $this->policy->source('users');
        $saved = $this->catalog['sources']['users'];
        $saved['users_number'] = 100;
        $saved['current_user_id'] = $this->catalog['user'];
        $saved['exclude_current_user'] = 'yes';
        $settings = $this->policy->settings($source, $saved, [
            'current_user_id' => $this->catalog['user'], 'users_roles' => ['administrator'],
        ], 1);
        self::assertSame(['author'], $settings['users_roles']);
        self::assertSame(0, $settings['current_user_id']);
        self::assertSame(0, $source->query_calls);

        $expected_email = get_userdata($this->catalog['user'])->user_email;
        $rendered_email = null;
        foreach ($source->get_items($settings)->items as $item) {
            if ($item->get_field('user_email') !== $expected_email) {
                continue;
            }
            LoopContext::push($item);
            ob_start();
            try {
                (new UserEmail())->render();
                $rendered_email = ob_get_contents();
            } finally {
                ob_end_clean();
                LoopContext::pop();
            }
        }
        self::assertSame($expected_email, $rendered_email, 'The existing User Email tag remains author-controlled.');
        self::assertSame(0, get_current_user_id(), 'A rendered user must not become the visitor identity.');
        self::assertSame(1, $source->query_calls);
    }

    public function testPostsUseSavedTypeVerifiedHostAndPublicPasswordFiltering(): void
    {
        $source = $this->policy->source('posts');
        $saved = $this->catalog['sources']['posts'];
        $saved['posts_per_page'] = 100;
        $saved['current_post_id'] = $this->catalog['posts']['private'];
        $settings = $this->policy->settings($source, $saved, [
            'post_id' => $this->catalog['native_host'], 'post_type' => 'page',
        ], 1);
        self::assertSame('post', $settings['post_type']);
        self::assertSame($this->catalog['native_host'], $settings['current_post_id']);
        self::assertSame(0, $source->query_calls);
        $ids = [];
        foreach ($source->get_items($settings)->items as $item) {
            $id = $item->get_field('ID');
            $ids[] = $id;
            self::assertSame('publish', get_post_status($id));
            self::assertSame('', get_post($id)->post_password);
        }
        self::assertContains($this->catalog['posts']['published'], $ids);
        foreach (['draft', 'private', 'protected'] as $name) {
            self::assertNotContains($this->catalog['posts'][$name], $ids);
        }
    }

    public function testTermsUseSavedTaxonomyAndVerifiedCurrentTerm(): void
    {
        $source = $this->policy->source('terms');
        $saved = $this->catalog['sources']['terms'];
        $saved['exclude_current_term'] = 'yes';
        $saved['current_term_id'] = 999999;
        $settings = $this->policy->settings($source, $saved, [
            'term_id' => $this->catalog['term'], 'taxonomy' => 'post_tag',
        ], 1);
        self::assertSame('category', $settings['taxonomy']);
        self::assertSame($this->catalog['term'], $settings['current_term_id']);
        self::assertSame(0, $source->query_calls);
        foreach ($source->get_items($settings)->items as $item) {
            self::assertNotSame($this->catalog['term'], $item->get_field('term_id'));
        }
    }

    public function testCurrentQueryUsesOnlyTheVerifiedHostQuery(): void
    {
        $source = $this->policy->source('posts');
        $settings = $this->policy->settings($source, [
            'post_type' => 'current_query',
            'current_query_vars' => ['post_type' => 'page', 'p' => $this->catalog['posts']['private']],
        ], ['query' => ['post_type' => 'post', 's' => 'Source', 'posts_per_page' => 2]], 2);
        self::assertSame('post', $settings['current_query_args']['post_type']);
        self::assertSame('Source', $settings['current_query_args']['s']);
        self::assertSame(2, $settings['current_query_args']['paged']);
        self::assertSame(2, $settings['current_query_args']['posts_per_page']);
        self::assertSame('publish', $settings['current_query_args']['post_status']);
        self::assertFalse($settings['current_query_args']['has_password']);
        self::assertArrayNotHasKey('p', $settings['current_query_args']);
        self::assertSame(0, $source->query_calls);
    }

    /** @dataProvider invalidSavedSources */
    public function testInvalidOrUnavailableSourceIsRejectedBeforeQuery(string $key, array $saved, array $context): void
    {
        $exception = null;
        try {
            $source = $this->policy->source($key);
            $this->policy->settings($source, $saved, $context, 1);
        } catch (ValidationException $error) {
            $exception = $error;
        }
        self::assertInstanceOf(ValidationException::class, $exception);
        foreach ($this->registry->all() as $source) {
            self::assertSame(0, $source->query_calls);
        }
    }

    public static function invalidSavedSources(): array
    {
        return [
            'absent source' => ['not_installed', [], []],
            'private post type' => ['posts', ['post_type' => 'revision'], []],
            'private taxonomy' => ['terms', ['taxonomy' => 'nav_menu'], []],
            'missing selected role' => ['users', ['users_roles' => ['role_removed_by_extension']], []],
            'role is not an array' => ['users', ['users_roles' => 'author'], []],
            'malformed count' => ['posts', ['posts_per_page' => []], []],
            'current query missing verified host' => ['posts', ['post_type' => 'current_query'], []],
            'current query cannot fall back from private type' => ['posts', ['post_type' => 'current_query'], ['query' => ['post_type' => 'revision']]],
        ];
    }

    public function testLegacySourceMustExplicitlyOptIntoPublicPolicy(): void
    {
        $legacy = $this->createMock(LoopDataSourceInterface::class);
        $legacy->method('get_key')->willReturn('legacy');
        $legacy->expects(self::never())->method('get_items');
        $this->registry->register($legacy);
        $this->expectException(ValidationException::class);
        $this->policy->source('legacy');
    }

    public function testPublicCountsAreBoundedWithoutCoercingArrays(): void
    {
        foreach ([0, -1, '', '0', 101, 1000000] as $count) {
            self::assertSame(100, PublicLoopLimits::page_size($count));
        }
        self::assertSame(6, PublicLoopLimits::page_size(null));
        self::assertSame(1, PublicLoopLimits::page_size(1));
        self::assertSame(100, PublicLoopLimits::page_size(null, 500));
        $this->expectException(ValidationException::class);
        PublicLoopLimits::page_size([]);
    }

    public function testAjaxPaginatedWidgetsBoundTheirClassicFirstPageSize(): void
    {
        foreach ([0, -5, '', 500, 100] as $raw) {
            $bounded = PublicLoopLimits::bound_ajax_page_size(['pagination_ajax' => 'yes', 'posts_per_page' => $raw], 'posts_per_page');
            self::assertSame(100, $bounded['posts_per_page'], 'Raw value ' . var_export($raw, true) . ' must bound to 100 when AJAX-paginated.');
        }
        self::assertSame(3, PublicLoopLimits::bound_ajax_page_size(['pagination_ajax' => 'yes', 'posts_per_page' => 3], 'posts_per_page')['posts_per_page']);

        // Not AJAX-paginated, or a malformed value: left exactly as authored
        // for the source's own existing validation/defaulting to handle.
        foreach ([0, 500, 'not-a-number'] as $raw) {
            $settings = ['pagination_ajax' => 'no', 'posts_per_page' => $raw];
            self::assertSame($raw, PublicLoopLimits::bound_ajax_page_size($settings, 'posts_per_page')['posts_per_page']);
        }
        self::assertSame(
            500,
            PublicLoopLimits::bound_ajax_page_size(['posts_per_page' => 500], 'posts_per_page')['posts_per_page'],
            'Absent pagination_ajax leaves the value untouched.'
        );
        self::assertSame(
            'not-a-number',
            PublicLoopLimits::bound_ajax_page_size(['pagination_ajax' => 'yes', 'posts_per_page' => 'not-a-number'], 'posts_per_page')['posts_per_page'],
            'A malformed authored value is left for the source itself to reject/default.'
        );
    }

    public function testClassicFirstPageOfAnAjaxPaginatedPostsWidgetIsBounded(): void
    {
        $source = new PostsDataSource();
        $captured = null;
        $observe = static function (\WP_Query $query) use (&$captured) {
            $captured = $query->get('posts_per_page');
        };
        $base_settings = [
            'post_type' => 'post', 'orderby' => 'date', 'order' => 'DESC', 'exclude_current_post' => 'no',
        ];

        add_action('pre_get_posts', $observe);
        try {
            $source->get_items($base_settings + ['pagination_ajax' => 'yes', 'posts_per_page' => 0]);
            self::assertSame(100, $captured, 'A zero/unlimited saved size on an AJAX-paginated widget must be bounded on its classic first page.');

            $captured = null;
            $source->get_items($base_settings + ['pagination_ajax' => 'yes', 'posts_per_page' => 5000]);
            self::assertSame(100, $captured, 'An oversized saved size on an AJAX-paginated widget must be bounded.');

            $captured = null;
            $source->get_items($base_settings + ['pagination_ajax' => 'no', 'posts_per_page' => 0]);
            self::assertSame(0, $captured, 'A non-AJAX-paginated widget keeps its exact authored value unchanged.');
        } finally {
            remove_action('pre_get_posts', $observe);
        }
    }
}
