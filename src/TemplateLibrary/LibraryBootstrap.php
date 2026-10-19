<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Core\Hooks;
use Elemacy\Core\RequestState;
use Elemacy\TemplateLibrary\Constants\MetaKeys;

/**
 * Wires the always-on parts of the template library: the CPT, the built-in
 * library types, the per-request candidate-cache invalidation, keeping library
 * items' own URLs private, and keeping the library CPT out of
 * display-condition target lists.
 */
class LibraryBootstrap
{
    protected bool $hooked = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing.
     */
    public function register_hooks(): void
    {
        if ($this->hooked) {
            return;
        }

        $this->hooked = true;

        LibraryPostType::register();

        $this->register_types();
        $this->register_cache_invalidation();
        $this->keep_items_private();
        $this->exclude_from_conditions();
    }

    /**
     * Core owns the generic "section" block type, so the library always has a
     * default type even with every module off; it is referenced by ID rather
     * than auto-resolved. Modules register their own specialized types at
     * the default `init` priority, and add-ons contribute more through
     * LIBRARY_TYPES_REGISTER_ACTION at priority 20 — after the modules, and
     * never on plugins_loaded, which would load translations too early.
     */
    protected function register_types(): void
    {
        add_action('init', static function (): void {
            TypeRegistry::instance()->register(
                new TypeDefinition('section', __('Section', 'elemacy'), 'block', 'core')
            );
        }, 5);

        add_action('init', static function (): void {
            do_action(Hooks::LIBRARY_TYPES_REGISTER_ACTION, TypeRegistry::instance());
        }, 20);
    }

    /**
     * The candidate index (ResolverCache) and the per-request evaluation
     * memo built on top of it (TemplateResolver) are separate layers: a write
     * invalidates both, but a bare site switch only needs to reset both back
     * to empty, not delete/rebuild the persisted index of a site that never
     * actually changed.
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
        add_action(Hooks::LIBRARY_CHANGED_ACTION, static function (): void {
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
        // for a same-named type.
        add_action('switch_blog', static function (): void {
            ResolverCache::forget_memo();
            TemplateResolver::reset();
        });

        RequestState::register(TemplateResolver::class, [TemplateResolver::class, 'reset']);
    }

    /**
     * A library item's own URL exists for Elementor's editor and previews,
     * which only someone who can edit the item opens. For anyone else the
     * main query finds nothing there, so WordPress serves its own not-found
     * page (status, template and all) and no theme loop can print the item.
     * No one's view of it is indexed. Rendering items inside pages, which
     * never goes through the main query, is unaffected.
     */
    protected function keep_items_private(): void
    {
        add_filter('posts_results', static function ($posts, $query) {
            if (!is_array($posts) || !$query instanceof \WP_Query || !$query->is_main_query() || is_admin()) {
                return $posts;
            }

            return array_values(array_filter($posts, static fn($post): bool => !$post instanceof \WP_Post
                || LibraryPostType::POST_TYPE !== $post->post_type
                || current_user_can('edit_post', $post->ID)));
        }, 10, 2);

        add_filter('wp_robots', static function ($robots) {
            if (is_array($robots) && is_singular(LibraryPostType::POST_TYPE)) {
                $robots['noindex']  = true;
                $robots['nofollow'] = true;
            }

            return $robots;
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
