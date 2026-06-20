<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\PostStatus;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use WP_Query;

/**
 * Caches the per-type candidate index used to resolve which library items apply
 * to a request. The index holds only published items' ids + raw conditions, so
 * it is small enough to autoload — the frontend hot path reads it from
 * `alloptions` with no extra query. It is deleted (not rebuilt) on any change
 * and lazily rebuilt on the next read, which sidesteps save-ordering issues.
 */
class ResolverCache
{
    const OPTION = 'elemacy_library_index';

    /**
     * @var array<string, array<int, array{id:int, conditions:array}>>|null
     */
    protected static ?array $memo = null;

    /**
     * @return array<string, array<int, array{id:int, conditions:array}>>
     */
    public function get_index(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        $index = get_option(self::OPTION, null);

        if (!is_array($index)) {
            $index = $this->build_index();
            update_option(self::OPTION, $index, true);
        }

        static::$memo = $index;

        return $index;
    }

    public function invalidate(): void
    {
        static::$memo = null;
        delete_option(self::OPTION);
    }

    /**
     * @return array<string, array<int, array{id:int, conditions:array}>>
     */
    protected function build_index(): array
    {
        $query = new WP_Query([
            'post_type'      => LibraryPostType::POST_TYPE,
            'post_status'    => PostStatus::PUBLISH,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $index = [];

        foreach ($query->posts as $post_id) {
            $post_id = (int) $post_id;
            $type    = (string) get_post_meta($post_id, MetaKeys::TEMPLATE_TYPE, true);

            if ($type === '') {
                continue;
            }

            $conditions = get_post_meta($post_id, '_elemacy_conditions', true);

            $index[$type][] = [
                'id'         => $post_id,
                'conditions' => is_array($conditions) ? $conditions : [],
            ];
        }

        return $index;
    }
}
