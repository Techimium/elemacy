<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use WP_Query;

/**
 * Caches the per-type candidate index used to resolve which library items apply
 * to a request. The index holds published items' ids + raw conditions. It is
 * NOT autoloaded — it grows with content, and `get_option()` already rides the
 * persistent `options` cache group — so plain requests never carry it in
 * `alloptions`. On any change it is deleted, then rebuilt on this request's
 * `shutdown` (after all service/Elementor meta writes have landed), so the
 * saving request pays the rebuild query instead of the next frontend visitor.
 */
class ResolverCache
{
    const OPTION = 'elemacy_library_index';

    /**
     * @var array<string, array<int, array{id:int, conditions:array}>>|null
     */
    protected static ?array $memo = null;

    protected static bool $rebuild_scheduled = false;

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
            update_option(self::OPTION, $index, false);
        }

        static::$memo = $index;

        return $index;
    }

    public function invalidate(): void
    {
        static::$memo = null;
        delete_option(self::OPTION);

        if (!static::$rebuild_scheduled) {
            static::$rebuild_scheduled = true;
            add_action('shutdown', static function (): void {
                static::$memo = null;
                (new static())->get_index();
            });
        }
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

            $conditions = get_post_meta($post_id, ConditionRepository::META_KEY, true);

            $index[$type][] = [
                'id'         => $post_id,
                'conditions' => is_array($conditions) ? $conditions : [],
            ];
        }

        return $index;
    }
}
