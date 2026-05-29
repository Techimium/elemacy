<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\DTO\ConditionRuleDTO;
use Elemacy\Modules\ThemeBuilder\DTO\CreateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateListFilterDTO;
use Elemacy\Modules\ThemeBuilder\DTO\UpdateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\PostTypes\TemplatePostType;
use WP_Query;

class TemplateService
{
    protected const CONDITIONS_META_KEY = '_elemacy_conditions';

    public function get_all(TemplateListFilterDTO $filter_dto)
    {
        $query_args = [
            'post_type' => TemplatePostType::POST_TYPE,
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
                    'key' => '_elemacy_template_type',
                    'value' => $filter_dto->type,
                ],
            ];
        }

        if (!empty($filter_dto->status)) {
            $query_args['post_status'] = $filter_dto->status;
        }

        $query = new WP_Query($query_args);

        $templates = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $templates[] = $this->create_dto(get_post());
            }
            wp_reset_postdata();
        }

        return $templates;
    }

    public function get(int $id)
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TemplatePostType::POST_TYPE) {
            return null;
        }

        return $this->create_dto($post);
    }

    public function create(CreateTemplateDTO $dto)
    {
        $post_data = [
            'post_title' => $dto->title ?? '',
            'post_status' => $dto->status ?? 'publish',
            'post_type' => TemplatePostType::POST_TYPE,
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (isset($dto->type)) {
            update_post_meta($post_id, '_elemacy_template_type', $dto->type);
        }

        if (in_array($dto->type, ['header', 'footer'], true)) {
            update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');
        }

        if (isset($dto->conditions)) {
            $this->save_conditions($post_id, (array) $dto->conditions);
        }

        return $this->create_dto(get_post($post_id));
    }

    public function update(int $id, UpdateTemplateDTO $dto)
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TemplatePostType::POST_TYPE) {
            return new \WP_Error('not_found', __('Template not found.', 'elemacy'), ['status' => 404]);
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
            update_post_meta($id, '_elemacy_template_type', $dto->type);

            if (in_array($dto->type, ['header', 'footer'], true)) {
                update_post_meta($id, '_wp_page_template', 'elementor_canvas');
            } else {
                delete_post_meta($id, '_wp_page_template');
            }
        }

        if (isset($dto->conditions)) {
            $this->save_conditions($id, (array) $dto->conditions);
        }

        return $this->create_dto(get_post($id));
    }

    public function duplicate(int $id)
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TemplatePostType::POST_TYPE) {
            return new \WP_Error('not_found', __('Template not found.', 'elemacy'), ['status' => 404]);
        }

        /* translators: %s: original template title. */
        $new_title = sprintf(__('%s (Copy)', 'elemacy'), $post->post_title);

        $new_post_id = wp_insert_post([
            'post_title' => $new_title,
            'post_content' => $post->post_content,
            'post_status' => 'draft',
            'post_type' => TemplatePostType::POST_TYPE,
            'post_author' => get_current_user_id(),
        ], true);

        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }

        $meta = get_post_meta($id);

        if (is_array($meta)) {
            foreach ($meta as $meta_key => $meta_values) {
                if (strpos($meta_key, '_edit_lock') === 0 || strpos($meta_key, '_edit_last') === 0) {
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

        if (!$post || $post->post_type !== TemplatePostType::POST_TYPE) {
            return new \WP_Error('not_found', __('Template not found.', 'elemacy'), ['status' => 404]);
        }

        $result = wp_delete_post($id, true);

        if (!$result) {
            return new \WP_Error('delete_failed', __('Failed to delete template.', 'elemacy'), ['status' => 500]);
        }

        return true;
    }

    protected function get_conditions(int $template_id): array
    {
        $raw = get_post_meta($template_id, static::CONDITIONS_META_KEY, true);

        return ConditionRuleDTO::collection(is_array($raw) ? $raw : []);
    }

    protected function save_conditions(int $template_id, array $conditions): void
    {
        update_post_meta($template_id, static::CONDITIONS_META_KEY, $this->normalize_conditions($conditions));
    }

    protected function normalize_conditions(array $conditions): array
    {
        return array_values(array_map(function ($rule): array {
            $dto = ConditionRuleDTO::from_array(is_array($rule) ? $rule : []);
            $dto->id = $dto->id !== '' ? $dto->id : wp_generate_uuid4();
            return $dto->to_array();
        }, $conditions));
    }

    protected function create_dto($post): TemplateDTO
    {
        $dto = new TemplateDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, '_elemacy_template_type', true);
        $dto->author = (int) $post->post_author;
        $dto->date = $post->post_date_gmt;
        $dto->conditions = $this->get_conditions($post->ID);

        return $dto;
    }
}
