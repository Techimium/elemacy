<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Container;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Access\PublishedLoopDefinitionResolver;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\Loop\Rendering\LoopTemplateRenderStack;
use Elemacy\Tests\Integration\Fixtures\ArchitectureCatalog;
use Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest;
use Elemacy\Tests\Integration\Fixtures\RequestSimulator;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ArchitectureCatalog.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * A public loop resolves "current post" and "current term" the way the editor
 * does in every placement it can live in (page, Theme Builder template, popup,
 * nested loop item), on its first render and on its signed later pages.
 *
 * Constructs ArchitectureCatalog, which requires ACF Pro's repeater field type.
 *
 * @group requires-acf-pro
 */
final class LoopHostContextTest extends IntegrationTestCase
{
    private ArchitectureCatalog $fixtures;
    private array $catalog;
    private RequestSimulator $request;
    private array $posts = [];
    private array $terms = [];
    private array $field_groups = [];
    private $previous_cache_ttl;

    protected function setUp(): void
    {
        parent::setUp();
        // Elementor's Element Cache prints a dynamic widget as an
        // [elementor-element] placeholder; it is an orthogonal Elementor
        // concern, disabled for determinism as in foreign-document-assets.php.
        $this->previous_cache_ttl = get_option('elementor_element_cache_ttl');
        update_option('elementor_element_cache_ttl', 'disable');
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
            foreach ($this->posts as $id) {
                wp_delete_post($id, true);
            }
            foreach ($this->terms as $id) {
                wp_delete_term($id, 'category');
            }
            foreach ($this->field_groups as $id) {
                acf_delete_field_group($id);
            }
        } finally {
            if (false === $this->previous_cache_ttl) {
                delete_option('elementor_element_cache_ttl');
            } else {
                update_option('elementor_element_cache_ttl', $this->previous_cache_ttl);
            }
            $this->fixtures->cleanup();
            parent::tearDown();
        }
    }

    public function testAcfCurrentPostRendersTheViewedPostsRowsInASingleTemplate(): void
    {
        $single = $this->documents->create_everywhere('Host context single', 'single');
        $published = $this->catalog['posts']['published'];
        $this->request->visit(get_permalink($published));
        wp_set_current_user(0);

        $result = $this->policy()->eligible_items($this->acf_current_post(2, ''), $single, 1);

        self::assertSame([$published . '-0', $published . '-1'], $this->identities($result));
    }

    public function testAcfCurrentPostInsideAPostItemReadsThatPostOnTheFirstAndTheSignedNextPage(): void
    {
        $published = $this->catalog['posts']['published'];
        // Page 2 runs in a fresh process, which cannot see the catalog's
        // in-memory field group; this one is stored, like an author's.
        $field = $this->stored_repeater($published);
        $settings = ['repeater_field' => $field] + $this->acf_current_post(1, 'load_more');
        $outer = $this->documents->create('Host context outer', 'loop', 'publish', ArchitectureCatalog::grid($settings));
        $this->request->visit(get_permalink($this->catalog['elementor_host']));
        wp_set_current_user(0);

        ob_start();
        try {
            (new LoopRenderer())->render_item($outer, new PostLoopItem(get_post($published)), 'host-context', 'posts');
        } finally {
            $html = ob_get_clean();
        }

        self::assertStringContainsString('data-elemacy-item="' . $published . '-0"', $html, 'Page 1 of the inner loop must list its outer post\'s first row: ' . $html);
        self::assertSame(1, preg_match('/data-elemacy-loop-request="([^"]+)"/', $html, $match), 'The inner loop must offer its signed next page: ' . $html);

        $request = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $request['page'] = 2;
        $response = json_decode(NativeAjaxRequest::loop((string) wp_json_encode($request)), true);

        self::assertTrue($response['success'] ?? false, 'Page 2 must succeed: ' . wp_json_encode($response));
        self::assertStringContainsString('data-elemacy-item="' . $published . '-1"', $response['data']['items'], 'Page 2 must still read the outer post\'s rows.');
    }

    public function testExcludeCurrentPostAppliesInASingleTemplateAPopupAndANestedItem(): void
    {
        $published = $this->catalog['posts']['published'];
        $other = $this->published_post('Host context other');
        $single = $this->documents->create_everywhere('Host context single', 'single');
        // A saved current_post_id is never authoritative: it names $other, which must stay listed.
        $settings = $this->posts_excluding_current() + ['current_post_id' => $other];

        $this->request->visit(get_permalink($published));
        wp_set_current_user(0);
        foreach (['single template' => $single, 'popup' => $this->catalog['popups']['center']] as $placement => $owner) {
            $ids = $this->identities($this->policy()->eligible_items($settings, $owner, 1));
            self::assertNotContains((string) $published, $ids, "The viewed post must be excluded in a $placement.");
            self::assertContains((string) $other, $ids, "Only the server-determined current post is excluded in a $placement.");
        }

        $this->request->visit(get_permalink($this->catalog['elementor_host']));
        $ids = $this->within_post_item($published, function () use ($settings): array {
            return $this->identities($this->policy()->eligible_items($settings, $this->catalog['nested_loop'], 1));
        });
        self::assertNotContains((string) $published, $ids, 'The enclosing post item must be excluded in a nested loop.');
        self::assertContains((string) $other, $ids);
    }

    public function testExcludeCurrentTermAppliesOnATermArchiveOnTheFirstAndTheSignedPage(): void
    {
        $viewed = $this->catalog['term'];
        $other = $this->term('Host context other');
        $settings = [
            'data_source' => 'terms', 'taxonomy' => 'category', 'terms_number' => 100,
            'terms_orderby' => 'id', 'terms_order' => 'ASC', 'hide_empty' => '',
            'exclude_current_term' => 'yes', 'current_term_id' => $other,
            'template_id' => $this->catalog['library']['published'], 'pagination_type' => 'load_more',
        ];
        $archive = $this->documents->create_everywhere('Host context archive', 'archive', 'publish', ArchitectureCatalog::grid($settings));
        $this->request->visit(get_term_link($viewed));
        wp_set_current_user(0);

        $ids = $this->identities($this->policy()->eligible_items($settings, $archive, 1));
        self::assertNotContains((string) $viewed, $ids, 'The viewed term must be excluded on page 1.');
        self::assertContains((string) $other, $ids, 'A saved current_term_id is never authoritative.');

        $placement = PlacementResolverRegistry::instance()->resolve_current($archive);
        self::assertNotNull($placement, 'The archive template must be the resolved placement.');
        $definition = (new PublishedLoopDefinitionResolver($this->policy()))->find($archive, $this->catalog['widget_id']);
        $context = (new PaginationContextSigner())->sign($archive, $this->catalog['widget_id'], $definition->fingerprint(), $placement['type'], $placement['facts']);
        $response = json_decode(NativeAjaxRequest::loop((string) wp_json_encode([
            'version' => 1, 'owner_id' => $archive, 'widget_id' => $this->catalog['widget_id'],
            'page' => 1, 'fingerprint' => $definition->fingerprint(), 'context' => $context,
        ])), true);

        self::assertTrue($response['success'] ?? false, 'The signed request must succeed: ' . wp_json_encode($response));
        self::assertStringNotContainsString('data-elemacy-item="' . $viewed . '"', $response['data']['items'], 'The viewed term must be excluded on signed pages too.');
        self::assertStringContainsString('data-elemacy-item="' . $other . '"', $response['data']['items']);
    }

    /** @dataProvider nonPublicPosts */
    public function testANonPublicEnclosingPostIsNeverSuppliedAsTheCurrentPost(string $status): void
    {
        $post = $this->catalog['posts'][$status];
        update_field($this->catalog['acf']['key'], $this->catalog['acf']['rows'], $post);
        $this->request->visit(get_permalink($this->catalog['elementor_host']));
        wp_set_current_user(0);

        $this->expectException(ValidationException::class);
        $this->within_post_item($post, function (): void {
            $this->policy()->eligible_items($this->acf_current_post(2, ''), $this->catalog['nested_loop'], 1);
        });
    }

    public static function nonPublicPosts(): array
    {
        return ['draft' => ['draft'], 'private' => ['private'], 'password protected' => ['protected']];
    }

    private function policy(): PublicRenderPolicy
    {
        return Container::instance()->singleton(PublicRenderPolicy::class);
    }

    private function acf_current_post(int $rows, string $pagination): array
    {
        return [
            'data_source' => 'acf_repeater', 'repeater_field' => $this->catalog['acf']['name'],
            'source_type' => 'current_post', 'rows_number' => $rows, 'rows_offset' => 0,
            'template_id' => $this->catalog['library']['published'], 'pagination_type' => $pagination,
        ];
    }

    private function posts_excluding_current(): array
    {
        return [
            'data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => 100,
            'orderby' => 'title', 'order' => 'ASC', 'exclude_current_post' => 'yes', 'offset' => 0,
            'template_id' => $this->catalog['library']['published'], 'pagination_type' => '',
        ];
    }

    /** Mirrors LoopRenderer: an item is active and its loop template is rendering. */
    private function within_post_item(int $post_id, callable $callback)
    {
        LoopContext::push(new PostLoopItem(get_post($post_id)));
        LoopTemplateRenderStack::push($this->catalog['nested_loop']);
        try {
            return $callback();
        } finally {
            LoopTemplateRenderStack::pop();
            LoopContext::pop();
        }
    }

    /** @return string[] */
    private function identities(LoopResultDTO $result): array
    {
        return array_map(static function ($item): string {
            return $item->get_identity();
        }, $result->items);
    }

    /** A repeater stored as ACF field group posts, with two rows on $post. */
    private function stored_repeater(int $post): string
    {
        $suffix = bin2hex(random_bytes(6));
        $group = acf_import_field_group([
            'key' => 'group_elemacy_host_' . $suffix, 'title' => 'Elemacy host context rows',
            'fields' => [[
                'key' => 'field_elemacy_host_rows_' . $suffix, 'name' => 'elemacy_host_rows_' . $suffix, 'label' => 'Rows', 'type' => 'repeater',
                'sub_fields' => [['key' => 'field_elemacy_host_label_' . $suffix, 'name' => 'label', 'label' => 'Label', 'type' => 'text']],
            ]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);
        self::assertNotEmpty($group['ID'] ?? null, 'The field group must be stored.');
        $this->field_groups[] = (int) $group['ID'];
        self::assertTrue((bool) update_field('field_elemacy_host_rows_' . $suffix, [['label' => 'First'], ['label' => 'Second']], $post));

        return 'elemacy_host_rows_' . $suffix;
    }

    private function published_post(string $title): int
    {
        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title], true);
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }

    private function term(string $name): int
    {
        $term = wp_insert_term($name . ' ' . bin2hex(random_bytes(4)), 'category');
        self::assertIsArray($term);
        $this->terms[] = (int) $term['term_id'];

        return (int) $term['term_id'];
    }
}
