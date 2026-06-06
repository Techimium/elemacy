<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\DTO\CreatePopupDTO;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\DTO\PopupListFilterDTO;
use Elemacy\Modules\Popups\DTO\RuleDTO;
use Elemacy\Modules\Popups\DTO\TriggerDTO;
use Elemacy\Modules\Popups\DTO\UpdatePopupDTO;
use Elemacy\Modules\Popups\PostTypes\PopupPostType;
use WP_Query;

class PopupService
{
    protected const TYPE_META_KEY      = '_elemacy_popup_type';
    protected const TRIGGERS_META_KEY  = '_elemacy_popup_triggers';
    protected const RULES_META_KEY     = '_elemacy_popup_rules';
    protected const AB_GROUP_META_KEY  = '_elemacy_popup_ab_group';

    protected ConditionRepository $conditions;

    public function __construct()
    {
        $this->conditions = new ConditionRepository();
    }

    /**
     * @return PopupDTO[]
     */
    public function get_all(PopupListFilterDTO $filter_dto)
    {
        $query_args = [
            'post_type' => PopupPostType::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($filter_dto->search)) {
            $query_args['s'] = $filter_dto->search;
        }

        if (!empty($filter_dto->type)) {
            $query_args['meta_query'] = [
                [
                    'key' => static::TYPE_META_KEY,
                    'value' => $filter_dto->type,
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

        return $popups;
    }

    public function get(int $id)
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== PopupPostType::POST_TYPE) {
            return null;
        }

        return $this->create_dto($post);
    }

    public function create(CreatePopupDTO $dto)
    {
        $post_data = [
            'post_title' => $dto->title ?? '',
            'post_status' => $dto->status ?? 'publish',
            'post_type' => PopupPostType::POST_TYPE,
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $type = $dto->type ?? '';

        if ($type !== '') {
            update_post_meta($post_id, static::TYPE_META_KEY, $type);
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

        // Display/layout settings live on the Elementor document (see
        // Documents\PopupDocument); they are read back via Support\DocumentDisplay.

        update_post_meta($post_id, static::AB_GROUP_META_KEY, '');

        return $this->create_dto(get_post($post_id));
    }

    public function update(int $id, UpdatePopupDTO $dto)
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== PopupPostType::POST_TYPE) {
            return new \WP_Error('not_found', __('Popup not found.', 'elemacy'), ['status' => 404]);
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
            return $result;
        }

        if (isset($dto->type)) {
            update_post_meta($id, static::TYPE_META_KEY, $dto->type);
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

        if (!$post || $post->post_type !== PopupPostType::POST_TYPE) {
            return new \WP_Error('not_found', __('Popup not found.', 'elemacy'), ['status' => 404]);
        }

        /* translators: %s: original popup title. */
        $new_title = sprintf(__('%s (Copy)', 'elemacy'), $post->post_title);

        $new_post_id = wp_insert_post([
            'post_title' => $new_title,
            'post_content' => $post->post_content,
            'post_status' => 'draft',
            'post_type' => PopupPostType::POST_TYPE,
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($new_post_id)) {
            return $new_post_id;
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

        if (!$post || $post->post_type !== PopupPostType::POST_TYPE) {
            return new \WP_Error('not_found', __('Popup not found.', 'elemacy'), ['status' => 404]);
        }

        $result = wp_delete_post($id, true);

        if (!$result) {
            return new \WP_Error('delete_failed', __('Failed to delete popup.', 'elemacy'), ['status' => 500]);
        }

        return true;
    }

    protected function get_triggers(int $popup_id): array
    {
        $raw = get_post_meta($popup_id, static::TRIGGERS_META_KEY, true);

        return TriggerDTO::collection(is_array($raw) ? $raw : []);
    }

    protected function save_triggers(int $popup_id, array $triggers): void
    {
        update_post_meta($popup_id, static::TRIGGERS_META_KEY, $this->normalize_items($triggers, TriggerDTO::class));
    }

    protected function get_rules(int $popup_id): array
    {
        $raw = get_post_meta($popup_id, static::RULES_META_KEY, true);

        return RuleDTO::collection(is_array($raw) ? $raw : []);
    }

    protected function save_rules(int $popup_id, array $rules): void
    {
        update_post_meta($popup_id, static::RULES_META_KEY, $this->normalize_items($rules, RuleDTO::class));
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

    protected function create_dto($post): PopupDTO
    {
        $dto = new PopupDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, static::TYPE_META_KEY, true);
        $dto->author = (int) $post->post_author;
        $dto->date = $post->post_date_gmt;
        $dto->conditions = $this->conditions->get($post->ID);
        $dto->triggers = $this->get_triggers($post->ID);
        $dto->rules = $this->get_rules($post->ID);

        return $dto;
    }
}
