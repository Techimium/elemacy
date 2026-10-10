<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Constants\PostStatus;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use WP_Query;

/**
 * Caches the per-type candidate index used to resolve which library items
 * apply to a request. The index holds published items' ids + raw conditions.
 */
class ResolverCache
{
    public const OPTION = 'elemacy_library_index';

    /**
     * Small autoloaded counter, separate from the (non-autoloaded) index
     * option itself — bumped on every invalidate() so a rebuild started
     * under an older generation can detect a newer one landed while it was
     * still running and discard its own result instead of overwriting
     * fresher data.
     */
    public const GENERATION_OPTION = 'elemacy_library_index_generation';

    /**
     * Bounded expiring mutex (a WordPress transient, so it recovers on its
     * own if a rebuild never reaches release_lock(), e.g. a fatal error)
     * that only reduces redundant concurrent rebuild queries — correctness
     * against a stale overwrite is the generation check above, not this
     * lock, so losing the race to acquire it is always safe to skip.
     */
    public const LOCK_KEY = 'elemacy_library_index_rebuild_lock';
    public const LOCK_TTL = 30;

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

        if (is_array($index)) {
            static::$memo = $index;

            return $index;
        }

        // Cache miss: every caller still gets a correct, freshly-built index
        // for its own request immediately — never blocked on a lock
        // ("do not block a public request indefinitely"). Persisting it is a
        // best-effort optimization for the *next* request, applied only if
        // no concurrent write landed while this build ran (, same
        // generation guard as invalidate()'s own scheduled rebuild below).
        $generation = (int) get_option(self::GENERATION_OPTION, 0);
        $index = $this->build_index();

        if ($this->acquire_lock()) {
            try {
                if (!$this->generation_changed($generation)) {
                    update_option(self::OPTION, $index, false);
                }
            } finally {
                $this->release_lock();
            }
        }

        static::$memo = $index;

        return $index;
    }

    public function invalidate(): void
    {
        static::$memo = null;
        delete_option(self::OPTION);
        $generation = ((int) get_option(self::GENERATION_OPTION, 0)) + 1;
        update_option(self::GENERATION_OPTION, $generation, true);

        if (!static::$rebuild_scheduled) {
            static::$rebuild_scheduled = true;
            add_action('shutdown', static function () use ($generation): void {
                static::$memo = null;
                static::$rebuild_scheduled = false;
                (new static())->rebuild_if_still_current($generation);
            });
        }
    }

    /**
     * Rebuilds and persists the index only if no later invalidate() has
     * bumped the generation since this rebuild was scheduled — checked both
     * before and after the (potentially slow) candidate query, since a
     * concurrent request's write can land at any point during it.
     */
    protected function rebuild_if_still_current(int $expected_generation): void
    {
        if (!$this->acquire_lock()) {
            return;
        }

        try {
            if ($this->generation_changed($expected_generation)) {
                return;
            }

            $index = $this->build_index();

            if ($this->generation_changed($expected_generation)) {
                return;
            }

            update_option(self::OPTION, $index, false);
            static::$memo = $index;
        } finally {
            $this->release_lock();
        }
    }

    protected function generation_changed(int $expected_generation): bool
    {
        return ((int) get_option(self::GENERATION_OPTION, 0)) !== $expected_generation;
    }

    protected function acquire_lock(): bool
    {
        if (false !== get_transient(self::LOCK_KEY)) {
            return false;
        }

        set_transient(self::LOCK_KEY, 1, self::LOCK_TTL);

        return true;
    }

    protected function release_lock(): void
    {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Drops only the in-request memo, leaving the persistent option (this
     * site's own candidate data) untouched — used when the acting site
     * changes mid-request (switch_to_blog()/restore_current_blog()) so the
     * next get_index() call re-reads get_option() under the now-current
     * site's own table prefix instead of returning another site's cached
     * array would wrongly delete and
     * schedule a rebuild of the *current* site's index for a change that
     * happened on a *different* site).
     */
    public static function forget_memo(): void
    {
        static::$memo = null;
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
            // ID DESC breaks ties between candidates sharing the same date
            // (e.g. bulk-imported together) deterministically — SQL's ORDER
            // BY date alone does not guarantee stable ordering for ties
            // . Preserves existing newest-match precedence.
            'orderby'        => [
                'date' => 'DESC',
                'ID'   => 'DESC',
            ],
        ]);

        $post_ids = array_map('intval', $query->posts);

        if (empty($post_ids)) {
            return [];
        }

        // A candidate is selected on exactly two fields; loading every
        // Elementor document's entire meta cache (update_meta_cache()) to
        // read them would also pull in _elementor_data/_elementor_css and
        // similar large, unrelated values for every candidate.
        // One targeted query for only those two keys avoids that.
        $fields = $this->load_selection_fields($post_ids);
        $index  = [];

        foreach ($post_ids as $post_id) {
            $type = (string) ($fields[$post_id][MetaKeys::TEMPLATE_TYPE] ?? '');

            if ($type === '') {
                continue;
            }

            $conditions = $fields[$post_id][ConditionRepository::META_KEY] ?? [];

            $index[$type][] = [
                'id'         => $post_id,
                'conditions' => is_array($conditions) ? $conditions : [],
            ];
        }

        return $index;
    }

    /**
     * @param int[] $post_ids
     * @return array<int, array<string, mixed>> post_id => [meta_key => unserialized value]
     */
    protected function load_selection_fields(array $post_ids): array
    {
        global $wpdb;

        // {$wpdb->postmeta} is the table name, which $wpdb->prepare() cannot
        // itself parameterize; {$id_placeholders} interpolates only the "%d"
        // *tokens* (one per post id, counted below), never a value — every
        // actual id/key value is still bound through prepare()'s own args.
        // The sniffs below can't statically count a placeholder list built at
        // runtime, hence the disable rather than a same-line ignore (which
        // cannot reach a violation reported on a different physical line of
        // one multi-line statement).
        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $id_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}"
            . " WHERE post_id IN ({$id_placeholders}) AND meta_key IN (%s, %s)",
            array_merge($post_ids, [MetaKeys::TEMPLATE_TYPE, ConditionRepository::META_KEY])
        ));
        // phpcs:enable WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $fields = [];

        foreach ($rows as $row) {
            $fields[(int) $row->post_id][$row->meta_key] = maybe_unserialize($row->meta_value);
        }

        return $fields;
    }
}
