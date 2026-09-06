<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\DTO\PaginatedResultDTO;
use Elemacy\Core\Exceptions\HttpException;
use Elemacy\Core\Exceptions\NotFoundException;
use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\Constants\MetaKeys;
use Elemacy\Modules\Popups\DTO\CreatePopupDTO;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\DTO\PopupListFilterDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\DTO\UpdatePopupDTO;
use Elemacy\Support\PostDates;
use Elemacy\TemplateLibrary\Constants\MetaKeys as LibraryMetaKeys;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;
use WP_Post;
use WP_Query;

class PopupService
{
    /**
     * Default page size when the request omits (or under/over-shoots) per_page.
     *
     * @var int
     */
    const DEFAULT_PER_PAGE = 20;

    protected ConditionRepository $conditions;

    public function __construct()
    {
        $this->conditions = new ConditionRepository();
    }

    /**
     * A page of popups (PopupDTO items) matching the filter, plus pagination totals.
     */
    public function get_all(PopupListFilterDTO $filter_dto): PaginatedResultDTO
    {
        $page = max(1, (int) $filter_dto->page);
        $per_page = (int) $filter_dto->per_page;
        $per_page = $per_page > 0 ? min(100, $per_page) : self::DEFAULT_PER_PAGE;

        $query_args = [
            'post_type' => LibraryPostType::POST_TYPE,
            'post_status' => PostStatus::ANY,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (is_string($filter_dto->search) && $filter_dto->search !== '') {
            $query_args['s'] = $filter_dto->search;
        }

        // The CPT is shared with theme templates, so always scope to popup-group
        // types: either the requested type (when it really is a popup type — a
        // foreign type must not leak other groups' items through this endpoint)
        // or every popup type.
        if (!empty($filter_dto->type) && in_array($filter_dto->type, $this->popup_types(), true)) {
            $query_args['meta_query'] = [
                [
                    'key' => LibraryMetaKeys::TEMPLATE_TYPE,
                    'value' => $filter_dto->type,
                ],
            ];
        } else {
            $query_args['meta_query'] = [
                [
                    'key' => LibraryMetaKeys::TEMPLATE_TYPE,
                    'value' => $this->popup_types(),
                    'compare' => 'IN',
                ],
            ];
        }

        if (!empty($filter_dto->status)) {
            $query_args['post_status'] = $filter_dto->status;
        }

        $query = new WP_Query($query_args);

        $popups = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $popups[] = $this->create_dto(get_post());
            }
            wp_reset_postdata();
        }

        return new PaginatedResultDTO($popups, (int) $query->found_posts, (int) $query->max_num_pages, $page, $per_page);
    }

    public function get(int $id)
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            return null;
        }

        return $this->create_dto($post);
    }

    public function get_or_fail(int $id): PopupDTO
    {
        $popup = $this->get($id);

        if (!$popup) {
            throw new NotFoundException(esc_html__('Popup not found.', 'elemacy'));
        }

        return $popup;
    }

    public function create(CreatePopupDTO $dto)
    {
        $post_data = [
            'post_title' => $dto->title ?? '',
            'post_status' => $dto->status ?? PostStatus::DRAFT,
            'post_type' => LibraryPostType::POST_TYPE,
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            throw new HttpException(esc_html($post_id->get_error_message()));
        }

        $type = $dto->type ?? '';

        if ($type !== '') {
            update_post_meta($post_id, LibraryMetaKeys::TEMPLATE_TYPE, $type);
        }

        // Popups render in an isolated Elementor canvas (no theme header/footer).
        update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');

        // Bind the post to our custom Elementor document type so it opens on a
        // blank popup canvas in the editor (see Documents\PopupDocument).
        update_post_meta($post_id, '_elementor_template_type', 'elemacy_popup');
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');

        if (isset($dto->conditions)) {
            $this->conditions->save($post_id, (array) $dto->conditions);
        }

        if (isset($dto->triggers)) {
            $this->save_triggers($post_id, (array) $dto->triggers);
        }

        if (isset($dto->rules)) {
            $this->save_rules($post_id, (array) $dto->rules);
        }

        return $this->create_dto(get_post($post_id));
    }

    public function update(int $id, UpdatePopupDTO $dto)
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            throw new NotFoundException(esc_html__('Popup not found.', 'elemacy'));
        }

        $post_data = [
            'ID' => $id,
        ];

        if (isset($dto->title)) {
            $post_data['post_title'] = $dto->title;
        }

        if (isset($dto->status)) {
            $post_data['post_status'] = $dto->status;
        }

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            throw new HttpException(esc_html($result->get_error_message()));
        }

        if (isset($dto->type)) {
            update_post_meta($id, LibraryMetaKeys::TEMPLATE_TYPE, $dto->type);
        }

        if (isset($dto->conditions)) {
            $this->conditions->save($id, (array) $dto->conditions);
        }

        if (isset($dto->triggers)) {
            $this->save_triggers($id, (array) $dto->triggers);
        }

        if (isset($dto->rules)) {
            $this->save_rules($id, (array) $dto->rules);
        }

        return $this->create_dto(get_post($id));
    }

    public function duplicate(int $id)
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            throw new NotFoundException(esc_html__('Popup not found.', 'elemacy'));
        }

        /* translators: %s: original popup title. */
        $new_title = sprintf(__('%s (Copy)', 'elemacy'), $post->post_title);

        $new_post_id = wp_insert_post([
            'post_title' => $new_title,
            'post_content' => $post->post_content,
            'post_status' => PostStatus::DRAFT,
            'post_type' => LibraryPostType::POST_TYPE,
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($new_post_id)) {
            throw new HttpException(esc_html($new_post_id->get_error_message()));
        }

        $meta = get_post_meta($id);

        // Meta keys add-ons own and should not carry over to the copy (e.g. pro
        // resets analytics counters for the new popup).
        $excluded_meta = (array) apply_filters(Hooks::POPUP_DUPLICATE_EXCLUDED_META_FILTER, []);

        if (is_array($meta)) {
            foreach ($meta as $meta_key => $meta_values) {
                if (strpos($meta_key, '_edit_lock') === 0 || strpos($meta_key, '_edit_last') === 0) {
                    continue;
                }

                if (in_array($meta_key, $excluded_meta, true)) {
                    continue;
                }

                foreach ($meta_values as $meta_value) {
                    update_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
                }
            }
        }

        return $this->create_dto(get_post($new_post_id));
    }

    public function delete(int $id)
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            throw new NotFoundException(esc_html__('Popup not found.', 'elemacy'));
        }

        $result = wp_delete_post($id, true);

        if (!$result) {
            throw new HttpException(esc_html__('Failed to delete popup.', 'elemacy'));
        }

        return true;
    }

    protected function get_triggers(int $popup_id): array
    {
        $raw = get_post_meta($popup_id, MetaKeys::TRIGGERS, true);

        return TriggerDTO::collection(is_array($raw) ? $raw : []);
    }

    protected function save_triggers(int $popup_id, array $triggers): void
    {
        update_post_meta($popup_id, MetaKeys::TRIGGERS, $this->normalize_items($triggers, TriggerDTO::class));
    }

    protected function get_rules(int $popup_id): array
    {
        $raw = get_post_meta($popup_id, MetaKeys::RULES, true);

        return RuleDTO::collection(is_array($raw) ? $raw : []);
    }

    protected function save_rules(int $popup_id, array $rules): void
    {
        update_post_meta($popup_id, MetaKeys::RULES, $this->normalize_items($rules, RuleDTO::class));
    }

    /**
     * Normalize a list of trigger/rule items: hydrate, ensure each has an id, serialize back.
     *
     * @param array  $items
     * @param string $dto_class
     * @return array
     */
    protected function normalize_items(array $items, string $dto_class): array
    {
        return array_values(array_map(function ($item) use ($dto_class): array {
            $dto = $dto_class::from_array(is_array($item) ? $item : []);
            $dto->id = $dto->id !== '' ? $dto->id : wp_generate_uuid4();
            return $dto->to_array();
        }, $items));
    }

    /**
     * The library item types this service is responsible for.
     *
     * @return string[]
     */
    protected function popup_types(): array
    {
        return TypeRegistry::instance()->names_in_group('popup');
    }

    /**
     * A popup lives on the shared library CPT and carries a popup-group type, so
     * theme templates on the same CPT are never treated as popups.
     */
    protected function owns(?WP_Post $post): bool
    {
        if (!$post || $post->post_type !== LibraryPostType::POST_TYPE) {
            return false;
        }

        $type = (string) get_post_meta($post->ID, LibraryMetaKeys::TEMPLATE_TYPE, true);

        return in_array($type, $this->popup_types(), true);
    }

    protected function create_dto($post): PopupDTO
    {
        $dto = new PopupDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, LibraryMetaKeys::TEMPLATE_TYPE, true);
        $dto->author = (int) $post->post_author;
        $dto->date = PostDates::gmt_datetime($post);
        $dto->conditions = $this->conditions->get($post->ID);
        $dto->triggers = $this->get_triggers($post->ID);
        $dto->rules = $this->get_rules($post->ID);

        return $dto;
    }
}
