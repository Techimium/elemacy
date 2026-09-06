<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\Controls\Query\QueryControl;
use Elemacy\Core\Controls\Query\QueryService;
use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Core\Documents\SinglePostPreviewDocument;
use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elemacy\Modules\Widgets\Contracts\LoopItemInterface;
use Elemacy\Modules\Widgets\DTO\LoopResultDTO;
use Elemacy\Modules\Widgets\LoopItems\PostLoopItem;
use Elemacy\Support\PostTypes;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use WP_Query;

/**
 * The built-in Posts data source: reproduces exactly what LoopGrid's and
 * LoopCarousel's Query section did before this abstraction existed (post
 * type select including "Current Query", posts-per-page, offset, order,
 * excluding the current post), so every existing site's saved widget
 * settings keep working unchanged (design.md D5).
 *
 * `current_query` mode has two callers with different needs:
 *  - A live widget render reuses the page's own already-executed main
 *    query (global $wp_query) — there is nothing else it could mean there.
 *  - AjaxPaginationController has no live main query to reuse (an
 *    admin-ajax.php request never runs the site's main query), so it
 *    sanitizes the original page's query vars itself and passes the
 *    resulting WP_Query args through the `current_query_args` settings key
 *    — see AjaxPaginationController::index().
 */
class PostsDataSource implements LoopDataSourceInterface
{
    public function get_key(): string
    {
        return 'posts';
    }

    public function get_label(): string
    {
        return esc_html__('Posts', 'elemacy');
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
        $post_type = $settings['post_type'] ?? 'post';

        if ('current_query' === $post_type) {
            if (array_key_exists('current_query_args', $settings)) {
                $query = new WP_Query($settings['current_query_args']);
            } else {
                global $wp_query;
                $query = $wp_query;
            }
        } else {
            $query = new WP_Query($this->build_query_args($settings));
        }

        $items = [];

        foreach ($query->posts as $post) {
            $items[] = new PostLoopItem($post);
        }

        $max_num_pages = (int) $query->max_num_pages;

        // Fix max_num_pages when a custom offset was in play for this AJAX
        // page: WP_Query computes it from found_posts alone, which
        // over-counts once an offset has already consumed some of those
        // posts. `offset_override_base` (the original, uncompounded offset)
        // is only ever set by sanitize_ajax_settings() below.
        if (isset($settings['offset_override_base'])) {
            $posts_per_page = max(1, (int) ($settings['posts_per_page'] ?? 1));
            $remaining = max(0, (int) $query->found_posts - (int) $settings['offset_override_base']);
            $max_num_pages = (int) ceil($remaining / $posts_per_page);
        }

        return new LoopResultDTO(
            $items,
            (int) $query->found_posts,
            $max_num_pages,
            true
        );
    }

    /**
     * `exclude_current_post` and `paged` each have two valid sources
     * depending on the caller:
     *  - A live widget render has no `current_post_id`/`paged` settings key
     *    (neither is a registered control) — falls back to `is_singular()`/
     *    `get_the_ID()` and `get_query_var('paged')`, exactly as before
     *    this abstraction existed.
     *  - AjaxPaginationController has neither a live "queried object" nor a
     *    live `paged` query var to read (an admin-ajax.php request runs
     *    outside the site's main query), so it passes both explicitly,
     *    reconstructed from its own sanitized request input.
     */
    protected function build_query_args(array $settings): array
    {
        $args = [
            'post_type' => $settings['post_type'],
            'posts_per_page' => $settings['posts_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
            'post_status' => PostStatus::PUBLISH,
        ];

        if (!empty($settings['offset'])) {
            $args['offset'] = $settings['offset'];
        }

        if ('yes' === $settings['exclude_current_post']) {
            if (array_key_exists('current_post_id', $settings)) {
                if (!empty($settings['current_post_id'])) {
                    $args['post__not_in'] = [(int) $settings['current_post_id']];
                }
            } elseif (is_singular()) {
                $args['post__not_in'] = [get_the_ID()];
            }
        }

        if (!empty($settings['pagination_type']) && empty($settings['offset'])) {
            $paged = $settings['paged']
                ?? (get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1));
            $args['paged'] = $paged;
        }

        return $args;
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

    /**
     * Relocated from AjaxPaginationController (loop-data-source-taxonomy
     * design.md D1) — validation, clamping, and offset math are unchanged
     * from before the move.
     */
    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        $post_type = isset($raw_settings['post_type']) ? sanitize_key((string) $raw_settings['post_type']) : '';
        $allowed_post_types = array_merge(['current_query'], get_post_types(['public' => true], 'names'));

        if ($post_type === '' || !in_array($post_type, $allowed_post_types, true)) {
            throw new ValidationException(esc_html__('Invalid post type.', 'elemacy'));
        }

        if ('current_query' === $post_type) {
            $current_vars = isset($raw_settings['current_query_vars']) && is_array($raw_settings['current_query_vars'])
                ? $raw_settings['current_query_vars']
                : [];

            $query_args = $this->sanitize_current_query_vars($current_vars);
            $query_args['paged'] = $paged;
            $query_args['post_status'] = PostStatus::PUBLISH;

            return [
                'post_type' => 'current_query',
                'current_query_args' => $query_args,
            ];
        }

        $allowed_orderby = ['date', 'title', 'menu_order', 'rand'];
        $orderby = isset($raw_settings['orderby']) ? sanitize_key((string) $raw_settings['orderby']) : 'date';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'date';
        }

        $order = isset($raw_settings['order']) ? strtoupper(sanitize_text_field((string) $raw_settings['order'])) : 'DESC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $posts_per_page = isset($raw_settings['posts_per_page']) ? (int) $raw_settings['posts_per_page'] : 6;
        $posts_per_page = max(1, min(100, $posts_per_page));

        $item_settings = [
            'post_type' => $post_type,
            'posts_per_page' => $posts_per_page,
            'orderby' => $orderby,
            'order' => $order,
            'paged' => $paged,
            'pagination_type' => 'ajax', // any non-empty value: gates paged/offset handling in build_query_args()
            'exclude_current_post' => 'yes' === ($raw_settings['exclude_current_post'] ?? '') ? 'yes' : 'no',
            'current_post_id' => !empty($raw_settings['current_post_id']) ? (int) $raw_settings['current_post_id'] : 0,
        ];

        // The first page has the plain offset. Subsequent pages need offset +
        // previous pages' posts; offset_override_base carries the original,
        // uncompounded offset through to get_items() so it can correct
        // max_num_pages (see get_items() above).
        if (!empty($raw_settings['offset'])) {
            $item_settings['offset_override_base'] = (int) $raw_settings['offset'];
            $item_settings['offset'] = (int) $raw_settings['offset'] + (($paged - 1) * $posts_per_page);
        }

        return $item_settings;
    }

    /**
     * Re-derive a safe subset of the page's main-query vars for "current query"
     * pagination. Relocated unchanged from AjaxPaginationController
     * (loop-data-source-taxonomy design.md D1). The widget round-trips the
     * original query vars through the browser, so on the way back they are
     * attacker-controlled; rather than merge them into WP_Query wholesale
     * (which would allow injecting meta_query, unbounded posts_per_page,
     * arbitrary post__in, etc.) copy only known-safe archive vars and sanitize
     * each.
     *
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    protected function sanitize_current_query_vars(array $vars): array
    {
        $safe = [];

        if (isset($vars['post_type'])) {
            $public_types = get_post_types(['public' => true], 'names');
            $requested = array_map('sanitize_key', array_map('strval', (array) $vars['post_type']));
            $allowed = array_values(array_intersect($requested, $public_types));

            if (!empty($allowed)) {
                $safe['post_type'] = count($allowed) === 1 ? $allowed[0] : $allowed;
            }
        }

        $string_keys = ['category_name', 'tag', 'author_name', 'name', 'pagename', 'taxonomy', 'term', 's'];
        foreach ($string_keys as $key) {
            if (isset($vars[$key]) && is_scalar($vars[$key])) {
                $safe[$key] = sanitize_text_field((string) $vars[$key]);
            }
        }

        $int_keys = ['cat', 'tag_id', 'author', 'year', 'monthnum', 'day', 'w', 'hour', 'minute', 'second', 'post_parent'];
        foreach ($int_keys as $key) {
            if (isset($vars[$key]) && is_numeric($vars[$key])) {
                $safe[$key] = (int) $vars[$key];
            }
        }

        if (isset($vars['orderby']) && is_scalar($vars['orderby'])) {
            $orderby = sanitize_key((string) $vars['orderby']);
            $allowed_orderby = ['date', 'title', 'menu_order', 'rand', 'id', 'name', 'modified', 'comment_count'];

            if (in_array($orderby, $allowed_orderby, true)) {
                $safe['orderby'] = $orderby;
            }
        }

        if (isset($vars['order']) && is_scalar($vars['order'])) {
            $order = strtoupper(sanitize_text_field((string) $vars['order']));
            $safe['order'] = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
        }

        if (isset($vars['posts_per_page']) && is_numeric($vars['posts_per_page'])) {
            $safe['posts_per_page'] = max(1, min(100, (int) $vars['posts_per_page']));
        }

        return $safe;
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
