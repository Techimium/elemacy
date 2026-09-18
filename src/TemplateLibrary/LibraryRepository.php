<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\HttpException;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use WP_Post;

/**
 * Owns native post/meta operations for the shared library CPT — never an
 * HTTP request, never a builder-specific decision. {@see DocumentWriter}
 * coordinates those on top of this.
 */
class LibraryRepository
{
    protected TypeRegistry $types;

    public function __construct(?TypeRegistry $types = null)
    {
        $this->types = $types ?? TypeRegistry::instance();
    }

    public function find(int $id): ?WP_Post
    {
        $post = get_post($id);

        return $post instanceof WP_Post ? $post : null;
    }

    /**
     * Whether a post is a library item belonging to the given group. A
     * popup service (group `popup`) cannot mutate a block or theme item
     * simply because its ID exists, and vice versa.
     */
    public function owns(?WP_Post $post, string $group): bool
    {
        if (!$post || $post->post_type !== LibraryPostType::POST_TYPE) {
            return false;
        }

        $type = (string) get_post_meta($post->ID, MetaKeys::TEMPLATE_TYPE, true);

        return in_array($type, $this->types->names_in_group($group), true);
    }

    /**
     * @param array $post_fields wp_insert_post()'s own $postarr, minus post_type (pinned here).
     * @throws HttpException
     */
    public function insert(array $post_fields): int
    {
        $post_id = wp_insert_post(array_merge($post_fields, [
            'post_type' => LibraryPostType::POST_TYPE,
        ]), true);

        if (is_wp_error($post_id)) {
            throw new HttpException(esc_html($post_id->get_error_message()));
        }

        return (int) $post_id;
    }

    /**
     * @param array $post_fields wp_update_post()'s own $postarr, minus ID (pinned here).
     * @throws HttpException
     */
    public function update(int $id, array $post_fields): void
    {
        if (empty($post_fields)) {
            return;
        }

        $result = wp_update_post(array_merge($post_fields, ['ID' => $id]), true);

        if (is_wp_error($result)) {
            throw new HttpException(esc_html($result->get_error_message()));
        }
    }

    /**
     * @throws HttpException
     */
    public function delete(int $id): void
    {
        if (!wp_delete_post($id, true)) {
            throw new HttpException(esc_html__('Failed to delete item.', 'elemacy'));
        }
    }

    /**
     * @return mixed
     */
    public function get_meta(int $id, string $key)
    {
        return get_post_meta($id, $key, true);
    }

    /**
     * update_post_meta() returns false when the given value already matches
     * what is stored — nothing was wrong, there was simply nothing to write.
     * Callers must never treat that as a failure; this wrapper intentionally
     * has no return value so nothing here is tempted to.
     */
    public function set_meta(int $id, string $key, $value): void
    {
        update_post_meta($id, $key, $value);
    }
}
