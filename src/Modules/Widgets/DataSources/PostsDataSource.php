<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Elementor\Documents\SinglePostPreviewDocument;
use Elemacy\Elementor\Controls\Query\QueryControl;
use Elemacy\Elementor\Controls\Query\QueryService;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Sources\Posts\PostsQuery;
use Elemacy\Loop\Sources\Posts\PostsSettings;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Sources\Posts\PostsPolicy;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Support\PostTypes;
use Elementor\Controls_Manager;
use Elemacy\Elementor\Widgets\BaseWidget;

/**
 * The built-in Posts data source: the post type, posts-per-page, offset,
 * order and current-post exclusion Loop Grid's and Loop Carousel's Query
 * section offers. Listing "whatever the page queries" is the separate Current
 * Query source.
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
        $options = [];

        foreach ($post_types as $post_type) {
            $options[$post_type->name] = $post_type->label;
        }

        return $options;
    }

    public function register_controls(BaseWidget $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            PostsSettings::POST_TYPE,
            [
                'label' => esc_html__('Post Type', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_public_post_types(),
                'default' => PostsSettings::DEFAULT_POST_TYPE,
                'condition' => $condition,
            ]
        );

        // Loop Carousel has always called this "Posts Count"; Loop Grid
        // "Posts Per Page" — both share the same PostsSettings::PER_PAGE setting.
        $posts_per_page_label = 'elemacy-loop-carousel' === $widget->get_name()
            ? esc_html__('Posts Count', 'elemacy')
            : esc_html__('Posts Per Page', 'elemacy');

        $widget->add_control(
            PostsSettings::PER_PAGE,
            [
                'label' => $posts_per_page_label,
                'type' => Controls_Manager::NUMBER,
                'default' => PostsSettings::DEFAULT_PER_PAGE,
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            PostsSettings::OFFSET,
            [
                'label' => esc_html__('Offset', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => PostsSettings::DEFAULT_OFFSET,
                'description' => esc_html__('Number of posts to skip. Note: Using an offset can break pagination.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            PostsSettings::ORDER_BY,
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => PostsSettings::DEFAULT_ORDER_BY,
                'options' => [
                    'date' => esc_html__('Date', 'elemacy'),
                    'title' => esc_html__('Title', 'elemacy'),
                    'menu_order' => esc_html__('Menu Order', 'elemacy'),
                    'rand' => esc_html__('Random', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            PostsSettings::ORDER,
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => PostsSettings::DEFAULT_ORDER,
                'options' => [
                    'ASC' => esc_html__('ASC', 'elemacy'),
                    'DESC' => esc_html__('DESC', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            PostsSettings::EXCLUDE_CURRENT,
            [
                'label' => esc_html__('Exclude Current Post', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => PostsSettings::DEFAULT_EXCLUDE_CURRENT,
                'condition' => $condition,
            ]
        );
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return $this->query->get_items($settings);
    }

    /**
     * Same control SinglePostPreviewDocument registers for SingleDocument's
     * own (non-loop) preview — same key, label, placeholder, and default, so
     * a Loop Item template saved before this preview support existed keeps
     * previewing against the same post with no migration.
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
