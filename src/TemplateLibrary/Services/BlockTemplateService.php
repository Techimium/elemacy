<?php

namespace Elemacy\TemplateLibrary\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\Core\Exceptions\HttpException;
use Elemacy\Core\Exceptions\NotFoundException;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\DTO\BlockTemplateDTO;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\DTO\CreateBlockTemplateDTO;
use Elemacy\TemplateLibrary\DTO\UpdateBlockTemplateDTO;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;
use WP_Post;
use WP_Query;

/**
 * CRUD for "block" library items — referenced-by-ID building blocks (Loop today).
 * Lives in Core because these items sit on the always-registered library CPT and
 * are not owned by any toggleable module. Deliberately self-contained: no
 * conditions, since block items are neither auto-resolved nor rendered as
 * standalone pages. They only carry editor defaults — a bare Canvas (no theme
 * header/footer) and a pinned Elementor document type.
 */
class BlockTemplateService
{
    const GROUP = 'block';

    public function get_all(BlockTemplateListFilterDTO $filter_dto): array
    {
        $query_args = [
            'post_type' => LibraryPostType::POST_TYPE,
            'post_status' => PostStatus::ANY,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($filter_dto->search)) {
            $query_args['s'] = $filter_dto->search;
        }

        // The CPT is shared with theme templates and popups, so always scope to
        // block-group types: either the requested type or every block type.
        if (!empty($filter_dto->type)) {
            $query_args['meta_query'] = [
                [
                    'key' => MetaKeys::TEMPLATE_TYPE,
                    'value' => $filter_dto->type,
                ],
            ];
        } else {
            $query_args['meta_query'] = [
                [
                    'key' => MetaKeys::TEMPLATE_TYPE,
                    'value' => $this->block_types(),
                    'compare' => 'IN',
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

    public function get(int $id): ?BlockTemplateDTO
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            return null;
        }

        return $this->create_dto($post);
    }

    public function get_or_fail(int $id): BlockTemplateDTO
    {
        $template = $this->get($id);

        if (!$template) {
            throw new NotFoundException(esc_html__('Template not found.', 'elemacy'));
        }

        return $template;
    }

    public function create(CreateBlockTemplateDTO $dto): BlockTemplateDTO
    {
        $post_id = wp_insert_post([
            'post_title' => $dto->title ?? '',
            'post_status' => $dto->status ?? PostStatus::PUBLISH,
            'post_type' => LibraryPostType::POST_TYPE,
        ], true);

        if (is_wp_error($post_id)) {
            throw new HttpException(esc_html($post_id->get_error_message()));
        }

        if (isset($dto->type)) {
            update_post_meta($post_id, MetaKeys::TEMPLATE_TYPE, $dto->type);
        }

        update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');
        update_post_meta($post_id, '_elementor_template_type', 'wp-page');
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');

        return $this->create_dto(get_post($post_id));
    }

    public function update(int $id, UpdateBlockTemplateDTO $dto): BlockTemplateDTO
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            throw new NotFoundException(esc_html__('Template not found.', 'elemacy'));
        }

        $post_data = ['ID' => $id];

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
            update_post_meta($id, MetaKeys::TEMPLATE_TYPE, $dto->type);
        }

        return $this->create_dto(get_post($id));
    }

    public function duplicate(int $id): BlockTemplateDTO
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            throw new NotFoundException(esc_html__('Template not found.', 'elemacy'));
        }

        /* translators: %s: original template title. */
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

        // Guarantee the editor defaults even when the source predates them.
        update_post_meta($new_post_id, '_wp_page_template', 'elementor_canvas');
        update_post_meta($new_post_id, '_elementor_template_type', 'wp-page');
        update_post_meta($new_post_id, '_elementor_edit_mode', 'builder');

        return $this->create_dto(get_post($new_post_id));
    }

    public function delete(int $id): bool
    {
        $post = get_post($id);

        if (!$this->owns($post)) {
            throw new NotFoundException(esc_html__('Template not found.', 'elemacy'));
        }

        $result = wp_delete_post($id, true);

        if (!$result) {
            throw new HttpException(esc_html__('Failed to delete template.', 'elemacy'));
        }

        return true;
    }

    /**
     * The library item types this service is responsible for.
     *
     * @return string[]
     */
    protected function block_types(): array
    {
        return TypeRegistry::instance()->names_in_group(self::GROUP);
    }

    /**
     * A block template lives on the shared library CPT and carries a block-group
     * type, so theme templates and popups on the same CPT are never treated as
     * block items.
     */
    protected function owns(?WP_Post $post): bool
    {
        if (!$post || $post->post_type !== LibraryPostType::POST_TYPE) {
            return false;
        }

        $type = (string) get_post_meta($post->ID, MetaKeys::TEMPLATE_TYPE, true);

        return in_array($type, $this->block_types(), true);
    }

    protected function create_dto(WP_Post $post): BlockTemplateDTO
    {
        $dto = new BlockTemplateDTO();
        $dto->id = $post->ID;
        $dto->title = $post->post_title;
        $dto->status = $post->post_status;
        $dto->type = get_post_meta($post->ID, MetaKeys::TEMPLATE_TYPE, true);
        $dto->author = (int) $post->post_author;
        $dto->date = $post->post_date_gmt;

        return $dto;
    }
}
