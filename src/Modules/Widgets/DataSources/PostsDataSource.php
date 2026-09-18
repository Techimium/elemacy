<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Core\Documents\SinglePostPreviewDocument;
use Elemacy\Core\Controls\Query\QueryControl;
use Elemacy\Core\Controls\Query\QueryService;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DataSources\PostsQuery;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopItems\PostLoopItem;
use Elemacy\Loop\Pagination\Policies\PostsPolicy;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elemacy\Support\PostTypes;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * The built-in Posts data source: reproduces exactly what LoopGrid's and
 * LoopCarousel's Query section did before this abstraction existed (post
 * type select including "Current Query", posts-per-page, offset, order,
 * excluding the current post), so every existing site's saved widget
 * settings keep working unchanged (design.md D5).
 *
 * The runtime query execution and public-context policy live in
 * Loop\DataSources\PostsQuery / Loop\Pagination\Policies\PostsPolicy
 * (design.md D7) — this class keeps only what needs an Elementor
 * widget/document instance (register_controls()/register_preview_controls())
 * plus this source's own identity, delegating everything else to those two
 * collaborators.
 */
class PostsDataSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface
{
    protected PostsQuery $query;
    protected PostsPolicy $policy;

    public function __construct(?PostsQuery $query = null, ?PostsPolicy $policy = null)
    {
        $this->query = $query ?? new PostsQuery();
        $this->policy = $policy ?? new PostsPolicy($this->query);
    }

    public function get_key(): string
    {
        return 'posts';
    }

    public function get_label(): string
    {
        return esc_html__('Posts', 'elemacy');
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        return $this->policy->get_public_settings($settings, $context, $page);
    }

    protected function get_public_post_types(): array
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $options = [
            'current_query' => esc_html__('Current Query', 'elemacy'),
        ];

        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }

        return $options;
    }

    public function register_controls(Widget_Base $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            'post_type',
            [
                'label' => esc_html__('Source', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_public_post_types(),
                'default' => 'post',
                'condition' => $condition,
            ]
        );

        // Loop Carousel has always called this "Posts Count"; Loop Grid
        // "Posts Per Page" — both share the same `posts_per_page` setting.
        $posts_per_page_label = 'elemacy-loop-carousel' === $widget->get_name()
            ? esc_html__('Posts Count', 'elemacy')
            : esc_html__('Posts Per Page', 'elemacy');

        $widget->add_control(
            'posts_per_page',
            [
                'label' => $posts_per_page_label,
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'offset',
            [
                'label' => esc_html__('Offset', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'description' => esc_html__('Number of posts to skip. Note: Using an offset can break pagination.', 'elemacy'),
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'orderby',
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => esc_html__('Date', 'elemacy'),
                    'title' => esc_html__('Title', 'elemacy'),
                    'menu_order' => esc_html__('Menu Order', 'elemacy'),
                    'rand' => esc_html__('Random', 'elemacy'),
                ],
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'order',
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'ASC' => esc_html__('ASC', 'elemacy'),
                    'DESC' => esc_html__('DESC', 'elemacy'),
                ],
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );

        $widget->add_control(
            'exclude_current_post',
            [
                'label' => esc_html__('Exclude Current Post', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => $condition + ['post_type!' => 'current_query'],
            ]
        );
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return $this->query->get_items($settings);
    }

    public function get_ajax_payload(array $settings): array
    {
        $payload = [
            'post_type' => $settings['post_type'],
            'posts_per_page' => $settings['posts_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
            'offset' => $settings['offset'],
            'exclude_current_post' => $settings['exclude_current_post'],
            'current_post_id' => get_the_ID(),
        ];

        if ('current_query' === $settings['post_type']) {
            global $wp_query;
            $payload['current_query_vars'] = $wp_query->query_vars;
        }

        return $payload;
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        return $this->query->sanitize_ajax_settings($raw_settings, $paged);
    }

    /**
     * Same control SinglePostPreviewDocument registers for SingleDocument's
     * own (non-loop) preview — same key, label, placeholder, and default, so
     * a Loop Item template saved before this preview support existed keeps
     * previewing against the same post with no migration (design.md D5).
     */
    public function register_preview_controls(PreviewablePageBase $document): void
    {
        $condition = ['preview_data_source' => $this->get_key()];

        $document->add_control(
            SinglePostPreviewDocument::PREVIEW_POST,
            [
                'label' => esc_html__('Preview Content', 'elemacy'),
                'type' => QueryControl::TYPE,
                'label_block' => true,
                'placeholder' => esc_html__('Auto (most recent post)', 'elemacy'),
                'autocomplete' => [
                    'object' => QueryService::OBJECT_POST,
                    'query' => ['post_type' => PostTypes::public_names()],
                ],
                'default' => '',
                'description' => esc_html__('Search for a real post to preview this template with. Defaults to the most recent post.', 'elemacy'),
                'condition' => $condition,
            ]
        );
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        $selected = (int) ($settings[SinglePostPreviewDocument::PREVIEW_POST] ?? 0);
        $post = null;

        if ($selected > 0 && PostStatus::PUBLISH === get_post_status($selected)) {
            $post = get_post($selected);
        }

        if (!$post) {
            $post_id = $this->most_recent_post_id();
            $post = $post_id ? get_post($post_id) : null;
        }

        return $post ? new PostLoopItem($post) : null;
    }

    protected function most_recent_post_id(): int
    {
        $post_types = PostTypes::public_names();

        if (empty($post_types)) {
            return 0;
        }

        $ids = get_posts([
            'post_type' => $post_types,
            'post_status' => PostStatus::PUBLISH,
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);

        return $ids ? (int) $ids[0] : 0;
    }
}
