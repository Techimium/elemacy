<?php

namespace Elemacy\Modules\LoopBuilder\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Elementor\Documents\SinglePostPreviewDocument;
use Elemacy\Elementor\Controls\Query\QueryControl;
use Elemacy\Elementor\Controls\Query\QueryService;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Contracts\SharedPageSizeSourceInterface;
use Elemacy\Loop\Sources\Posts\PostsQuery;
use Elemacy\Loop\Sources\Posts\PostsSettings;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Sources\Posts\PostsPolicy;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Support\PostTypes;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elemacy\Loop\Sources\Posts\PostsFilters;
use Elemacy\Widgets\BaseWidget;

/**
 * The built-in Posts data source: the post type, offset, order, current-post
 * exclusion and filters the Query section offers. Its page size is the
 * widget's shared one. Listing "whatever the page queries" is the separate
 * Current Query source.
 *
 * @internal
 */
class PostsDataSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface, SharedPageSizeSourceInterface
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

        foreach (PostTypes::public_names() as $name) {
            if (isset($post_types[$name])) {
                $options[$name] = $post_types[$name]->label;
            }
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

        $widget->add_control(
            PostsSettings::OFFSET,
            [
                'label' => esc_html__('Offset', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => PostsSettings::DEFAULT_OFFSET,
                'description' => esc_html__('Number of posts to skip.', 'elemacy'),
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
                    'modified' => esc_html__('Date Modified', 'elemacy'),
                    'title' => esc_html__('Title', 'elemacy'),
                    'author' => esc_html__('Author', 'elemacy'),
                    'comment_count' => esc_html__('Comment Count', 'elemacy'),
                    'menu_order' => esc_html__('Menu Order', 'elemacy'),
                    'rand' => esc_html__('Random', 'elemacy'),
                    'id' => esc_html__('Post ID', 'elemacy'),
                    'meta_value' => esc_html__('Custom Field (Text)', 'elemacy'),
                    'meta_value_num' => esc_html__('Custom Field (Number)', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            PostsSettings::ORDER_META_KEY,
            [
                'label' => esc_html__('Custom Field Name', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'condition' => $condition + [PostsSettings::ORDER_BY => PostsSettings::META_ORDERS],
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

        $this->register_filter_controls($widget, $condition);
    }

    /**
     * The optional filters. Each is off by default, leaving the query as it
     * was.
     */
    private function register_filter_controls(BaseWidget $widget, array $condition): void
    {
        $this->add_heading($widget, 'posts_include_heading', esc_html__('Include', 'elemacy'), $condition);
        $this->add_picker($widget, PostsSettings::INCLUDE_TERMS, esc_html__('Terms', 'elemacy'), QueryService::OBJECT_TERM, [], $condition);
        $this->add_picker($widget, PostsSettings::INCLUDE_AUTHORS, esc_html__('Authors', 'elemacy'), QueryService::OBJECT_USER, [], $condition);

        $this->add_heading($widget, 'posts_exclude_heading', esc_html__('Exclude', 'elemacy'), $condition);
        $this->add_picker($widget, PostsSettings::EXCLUDE_POSTS, esc_html__('Posts', 'elemacy'), QueryService::OBJECT_POST, ['post_type' => PostTypes::public_names()], $condition);
        $this->add_picker($widget, PostsSettings::EXCLUDE_TERMS, esc_html__('Terms', 'elemacy'), QueryService::OBJECT_TERM, [], $condition);
        $this->add_picker($widget, PostsSettings::EXCLUDE_AUTHORS, esc_html__('Authors', 'elemacy'), QueryService::OBJECT_USER, [], $condition);

        $this->add_heading($widget, 'posts_date_heading', esc_html__('Date', 'elemacy'), $condition);
        $widget->add_control(
            PostsSettings::DATE_RANGE,
            [
                'label' => esc_html__('Published', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => PostsFilters::DATE_RANGE_ANY,
                'options' => [
                    PostsFilters::DATE_RANGE_ANY => esc_html__('Any Time', 'elemacy'),
                    'day' => esc_html__('Past Day', 'elemacy'),
                    'week' => esc_html__('Past Week', 'elemacy'),
                    'month' => esc_html__('Past Month', 'elemacy'),
                    'quarter' => esc_html__('Past Quarter', 'elemacy'),
                    'year' => esc_html__('Past Year', 'elemacy'),
                    PostsFilters::DATE_RANGE_CUSTOM => esc_html__('Custom', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $custom_date = $condition + [PostsSettings::DATE_RANGE => PostsFilters::DATE_RANGE_CUSTOM];

        $widget->add_control(
            PostsSettings::DATE_AFTER,
            [
                'label' => esc_html__('After', 'elemacy'),
                'type' => Controls_Manager::DATE_TIME,
                'default' => '',
                'condition' => $custom_date,
            ]
        );

        $widget->add_control(
            PostsSettings::DATE_BEFORE,
            [
                'label' => esc_html__('Before', 'elemacy'),
                'type' => Controls_Manager::DATE_TIME,
                'default' => '',
                'condition' => $custom_date,
            ]
        );

        $this->register_meta_condition_control($widget, $condition);

        $this->add_heading($widget, 'posts_advanced_heading', esc_html__('Advanced', 'elemacy'), $condition);
        $this->add_switch($widget, PostsSettings::IGNORE_STICKY, esc_html__('Ignore Sticky Posts', 'elemacy'), '', $condition);
        $this->add_switch($widget, PostsSettings::AVOID_DUPLICATES, esc_html__('Avoid Duplicates', 'elemacy'), esc_html__('Skips posts earlier lists on this page already showed. Has no effect on a paginated list.', 'elemacy'), $condition);

        $widget->add_control(
            PostsSettings::QUERY_ID,
            [
                'label' => esc_html__('Query ID', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'description' => esc_html__('For developers: lets code adjust this query through a filter.', 'elemacy'),
                'condition' => $condition,
            ]
        );
    }

    private function register_meta_condition_control(BaseWidget $widget, array $condition): void
    {
        $rows = new Repeater();

        $rows->add_control(
            'key',
            [
                'label' => esc_html__('Field Name', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $rows->add_control(
            'compare',
            [
                'label' => esc_html__('Comparison', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '=',
                'options' => [
                    '=' => esc_html__('Equals', 'elemacy'),
                    '!=' => esc_html__('Does Not Equal', 'elemacy'),
                    '>' => esc_html__('Greater Than', 'elemacy'),
                    '>=' => esc_html__('Greater Than or Equal', 'elemacy'),
                    '<' => esc_html__('Less Than', 'elemacy'),
                    '<=' => esc_html__('Less Than or Equal', 'elemacy'),
                    'LIKE' => esc_html__('Contains', 'elemacy'),
                    'NOT LIKE' => esc_html__('Does Not Contain', 'elemacy'),
                    'EXISTS' => esc_html__('Exists', 'elemacy'),
                    'NOT EXISTS' => esc_html__('Does Not Exist', 'elemacy'),
                ],
            ]
        );

        $rows->add_control(
            'value',
            [
                'label' => esc_html__('Value', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'condition' => ['compare!' => ['EXISTS', 'NOT EXISTS']],
            ]
        );

        $this->add_heading($widget, 'posts_meta_heading', esc_html__('Custom Fields', 'elemacy'), $condition);
        $widget->add_control(
            PostsSettings::META_CONDITIONS,
            [
                'label' => esc_html__('Conditions', 'elemacy'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $rows->get_controls(),
                'default' => [],
                'title_field' => '{{{ key }}}',
                'prevent_empty' => false,
                'condition' => $condition,
            ]
        );
    }

    private function add_heading(BaseWidget $widget, string $id, string $label, array $condition): void
    {
        $widget->add_control(
            $id,
            [
                'label' => $label,
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => $condition,
            ]
        );
    }

    private function add_picker(BaseWidget $widget, string $id, string $label, string $picks, array $query, array $condition): void
    {
        $widget->add_control(
            $id,
            [
                'label' => $label,
                'type' => QueryControl::TYPE,
                'label_block' => true,
                'multiple' => true,
                'autocomplete' => [
                    'object' => $picks,
                    'query' => $query,
                ],
                'default' => [],
                'condition' => $condition,
            ]
        );
    }

    private function add_switch(BaseWidget $widget, string $id, string $label, string $description, array $condition): void
    {
        $widget->add_control(
            $id,
            [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
                'description' => $description,
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
