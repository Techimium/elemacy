<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Hooks;
use Elemacy\TemplateLibrary\Constants\MetaKeys;

/**
 * Wires the always-on parts of the template library: the CPT, the per-request
 * candidate-cache invalidation, the one-time data migration, and keeping the
 * library CPT out of display-condition target lists.
 */
class LibraryBootstrap
{
    protected bool $hooked = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing (design.md D7 — constructors store dependencies, they do not
     * register hooks; this is where that happens instead).
     */
    public function register_hooks(): void
    {
        if ($this->hooked) {
            return;
        }

        $this->hooked = true;

        LibraryPostType::register();

        $this->register_cache_invalidation();
        $this->exclude_from_conditions();
    }

    /**
     * The candidate index (ResolverCache) and the per-request evaluation
     * memo built on top of it (TemplateResolver) are separate layers (task
     * 11.2/11.3, design.md D11): a write invalidates both, but a bare site
     * switch only needs to reset both back to empty, not delete/rebuild the
     * persisted index of a site that never actually changed.
     */
    protected function register_cache_invalidation(): void
    {
        $invalidate = static function ($post_id): void {
            if (get_post_type($post_id) === LibraryPostType::POST_TYPE) {
                (new ResolverCache())->invalidate();
                TemplateResolver::reset();
            }
        };

        add_action('save_post', $invalidate);
        // before_delete_post (not deleted_post) so the post still exists and its
        // type can be checked.
        add_action('before_delete_post', $invalidate);
        add_action('trashed_post', $invalidate);
        add_action('untrashed_post', $invalidate);

        // Covers every builder application service's completed write
        // (create/update/duplicate/delete) uniformly — redundant with the
        // native hooks above for the common case, but the one signal that
        // still fires if a future write path ever skips wp_update_post().
        add_action(Hooks::LIBRARY_CHANGED_ACTION, static function (LibraryChanged $event): void {
            (new ResolverCache())->invalidate();
            TemplateResolver::reset();
        });

        // A bare update_post_meta()/add_post_meta()/delete_post_meta() call —
        // an import, a migration, or a direct Elementor/API write — fires
        // neither save_post nor LibraryChanged, yet changes exactly the two
        // fields the candidate index is built from. Scoped to those two meta
        // keys so unrelated metadata (e.g. _elementor_data on every content
        // edit) never triggers a rebuild.
        $invalidate_on_relevant_meta = static function ($meta_id, $post_id, $meta_key): void {
            if (!in_array($meta_key, [MetaKeys::TEMPLATE_TYPE, ConditionRepository::META_KEY], true)) {
                return;
            }

            if (get_post_type($post_id) === LibraryPostType::POST_TYPE) {
                (new ResolverCache())->invalidate();
                TemplateResolver::reset();
            }
        };

        add_action('updated_post_meta', $invalidate_on_relevant_meta, 10, 3);
        add_action('added_post_meta', $invalidate_on_relevant_meta, 10, 3);
        add_action('deleted_post_meta', $invalidate_on_relevant_meta, 10, 3);

        // The persistent option is already site-scoped (get_option() reads
        // the current site's own row), but the in-request memo/evaluation
        // cache are plain statics: without this, resolving on site B after
        // switch_to_blog() from site A would wrongly reuse site A's result
        // for a same-named type (task 11.2).
        add_action('switch_blog', static function (): void {
            ResolverCache::forget_memo();
            TemplateResolver::reset();
        });
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
