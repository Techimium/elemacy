<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;

/**
 * Wires the always-on parts of the template library: the CPT, the per-request
 * candidate-cache invalidation, the one-time data migration, and keeping the
 * library CPT out of display-condition target lists.
 */
class LibraryBootstrap
{
    public function __construct()
    {
        LibraryPostType::register();

        $this->register_cache_invalidation();
        $this->exclude_from_conditions();
    }

    protected function register_cache_invalidation(): void
    {
        $invalidate = static function ($post_id): void {
            if (get_post_type($post_id) === LibraryPostType::POST_TYPE) {
                (new ResolverCache())->invalidate();
            }
        };

        add_action('save_post', $invalidate);
        // before_delete_post (not deleted_post) so the post still exists and its
        // type can be checked.
        add_action('before_delete_post', $invalidate);
        add_action('trashed_post', $invalidate);
        add_action('untrashed_post', $invalidate);
    }

    /**
     * Library items are condition consumers, never condition targets.
     */
    protected function exclude_from_conditions(): void
    {
        add_filter(Hooks::CONDITIONS_EXCLUDED_POST_TYPES_FILTER, static function ($excluded) {
            $excluded = is_array($excluded) ? $excluded : [];

            if (!in_array(LibraryPostType::POST_TYPE, $excluded, true)) {
                $excluded[] = LibraryPostType::POST_TYPE;
            }

            return $excluded;
        });
    }
}
