<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

use Elemacy\Modules\ThemeBuilder\DTO\CreateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateDTO;
use Elemacy\Modules\ThemeBuilder\DTO\TemplateListFilterDTO;
use Elemacy\Modules\ThemeBuilder\DTO\UpdateTemplateDTO;
use Elemacy\Modules\ThemeBuilder\PostTypes\TemplatePostType;
use WP_Query;

defined('ABSPATH') || exit;

class TemplateService
{

    /**
     * Get all templates.
     *
     * @param TemplateListFilterDTO $filter_dto
     * @return array
     */
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

    /**
     * Get a single template.
     *
     * @param int $id
     * @return TemplateDTO|null
     */
    public function get(int $id)
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TemplatePostType::POST_TYPE) {
            return null;
        }

        return $this->create_dto($post);
    }

    /**
     * Get a template by type.
     *
     * @param string $type
     * @return TemplateDTO[]
     */
    public function get_by_type($type)
    {
        $args = [
            'post_type' => TemplatePostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_key' => '_elemacy_template_type',
            'meta_value' => $type,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ];

        $query = new WP_Query($args);

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

    /**
     * Create a new template.
     *
     * @param CreateTemplateDTO $dto
     * @return TemplateDTO|\WP_Error
     */
    public function create(CreateTemplateDTO $dto)
    {
        $post_data = [
            'post_title' => isset($dto->title) ? sanitize_text_field($dto->title) : '',
            'post_content' => isset($dto->content) ? wp_kses_post($dto->content) : '',
            'post_status' => isset($dto->status) ? sanitize_text_field($dto->status) : 'publish',
            'post_type' => TemplatePostType::POST_TYPE,
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (isset($dto->type)) {
            update_post_meta($post_id, '_elemacy_template_type', sanitize_text_field($dto->type));
        }

        if (in_array($dto->type, ['header', 'footer'], true)) {
            update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');
        }

        return $this->create_dto(get_post($post_id));
    }

    /**
     * Update a template.
     *
     * @param int $id
     * @param UpdateTemplateDTO $dto
     * @return TemplateDTO|\WP_Error
     */
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
        }

        if (in_array($dto->type, ['header', 'footer'], true)) {
            update_post_meta($id, '_wp_page_template', 'elementor_canvas');
        } else {
            delete_post_meta($id, '_wp_page_template');
        }

        return $this->create_dto(get_post($id));
    }

    /**
     * Delete a template.
     *
     * @param int $id
     * @return bool|\WP_Error
     */
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

    /**
     * Format post data for API response.
     *
     * @param \WP_Post $post
     * @return TemplateDTO
     */
    protected function create_dto($post)
    {
        $dto = new TemplateDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, '_elemacy_template_type', true);
        $dto->author = (int) $post->post_author;
        $dto->date = $post->post_date_gmt;

        return $dto;
    }
}
