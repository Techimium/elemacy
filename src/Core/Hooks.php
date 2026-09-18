<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

final class Hooks
{
    // Core lifecycle
    const LOADED_ACTION           = 'elemacy/action/loaded';
    const REGISTER_MODULES_ACTION = 'elemacy/action/register_modules';

    // Fired early — after dependency/compatibility checks, before any
    // provider's boot runs — so a service provider can
    // register itself (passes the Elemacy instance; call
    // register_provider() on it). Pro subscribes from its own plugin
    // bootstrap, so its own provider registration does not depend on the
    // later LOADED_ACTION below.
    const REGISTER_PROVIDERS_ACTION = 'elemacy/action/register_providers';

    // Fired after the stored DB version changes (passes old, new version strings).
    const VERSION_UPDATED_ACTION = 'elemacy/action/version_updated';

    // Lets extensions declare additional meta keys DocumentDuplicator must
    // exclude from a duplicate — their own generated caches/analytics beyond
    // the base exclusions it already applies (passes the excluded-keys array,
    // the library group, and the source WP_Post). Popups additionally still
    // honor the pre-existing POPUP_DUPLICATE_EXCLUDED_META_FILTER below.
    const LIBRARY_DUPLICATE_EXCLUDED_META_FILTER = 'elemacy/filter/library/duplicate_excluded_meta';

    // Fired after PageCachePurger::purge_all() runs (e.g. after a license
    // activation/deactivation/grace transition changes what a suspended
    // condition/rule/trigger/type resolves to), so anything else that caches
    // rendered output can invalidate alongside the supported page caches.
    const CACHE_PURGE_ACTION = 'elemacy/action/cache/purge';

    // Fired once after a library item's create/update/duplicate/delete is
    // fully complete — authoritative metadata included — passing a single
    // LibraryChanged payload. Cache invalidation and similar listeners
    // subscribe here instead of each of the three builder services.
    const LIBRARY_CHANGED_ACTION = 'elemacy/action/library/changed';

    // Template library
    // Fired so modules/add-ons can register library item types (passes the TypeRegistry).
    const LIBRARY_TYPES_REGISTER_ACTION = 'elemacy/action/library/types/register';

    // Fired early (before Elementor's own style pass) so modules can register
    // post IDs whose Elementor content renders outside their own page — a
    // popup, a theme template, etc. (passes the TemplateAssetsRegistrar).
    const TEMPLATE_ASSETS_COLLECT_ACTION = 'elemacy/action/template_assets/collect';

    // Admin scripts
    const ADMIN_SCRIPT_DATA_FILTER     = 'elemacy/filter/admin_script_data';
    const ENQUEUE_ADMIN_SCRIPTS_ACTION = 'elemacy/action/enqueue_admin_scripts';

    // Frontend scripts
    const FRONTEND_SCRIPT_DATA_FILTER     = 'elemacy/filter/frontend_script_data';

    // Widgets
    // Filters the widget catalog (the Config/widgets.php array) so add-ons can
    // register widgets into Elementor, the admin toggle list, and the REST API
    // in one place. Added widgets must register their own frontend asset handles.
    const WIDGETS_REGISTER_FILTER = 'elemacy/filter/widgets/register';

    // Theme Builder
    const THEME_BUILDER_TEMPLATE_TYPES_FILTER  = 'elemacy/filter/theme_builder/template_types';
    const THEME_BUILDER_FALLBACK_HEADER_ACTION = 'elemacy/action/theme_builder/fallback_header';

    // Fired so add-ons can register theme locations (passes the LocationRegistry).
    const THEME_BUILDER_LOCATIONS_REGISTER_ACTION = 'elemacy/action/theme_builder/locations/register';

    // Fired after the Theme Builder module registers its built-in theme
    // compatibility adapters, so add-ons can register their own for an
    // unrecognized theme slug without editing CompatibilityManager (passes
    // the CompatibilityManager).
    const THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION = 'elemacy/action/theme_builder/compatibility/register';

    // Fired after the Widgets module registers its built-in loop data sources,
    // so add-ons can register their own (passes the LoopDataSourceRegistry).
    const LOOP_DATA_SOURCES_REGISTER_ACTION = 'elemacy/action/loop/data_sources/register';

    // Fired after the Widgets module registers its built-in public pagination
    // placement resolvers (page, nested loop), so other modules/add-ons can
    // register their own (passes the PlacementResolverRegistry).
    const LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION = 'elemacy/action/loop/placement_resolvers/register';

    // Preview (editor real-data preview)
    // Lets add-ons adjust the WP_Query args a previewable document renders against
    // (passes the args array and the document instance).
    const PREVIEW_QUERY_ARGS_FILTER = 'elemacy/filter/preview/query_args';

    // Theme Builder — display conditions
    const CONDITIONS_EXCLUDED_POST_TYPES_FILTER = 'elemacy/filter/conditions/excluded_post_types';
    const CONDITIONS_EXCLUDED_TAXONOMIES_FILTER = 'elemacy/filter/conditions/excluded_taxonomies';

    // Fired after free registers its conditions (passes the ConditionManager).
    const CONDITIONS_REGISTER_ACTION = 'elemacy/action/conditions/register';

    // Popups
    const POPUP_DISPLAY_SETTINGS_FILTER     = 'elemacy/filter/popups/display_settings';
    const POPUP_FRONTEND_CONFIG_FILTER      = 'elemacy/filter/popups/frontend_config';
    const POPUP_MATCHED_FILTER              = 'elemacy/filter/popups/matched';
    const POPUP_DUPLICATE_EXCLUDED_META_FILTER = 'elemacy/filter/popups/duplicate_excluded_meta';

    // Fired so add-ons can register popup triggers / rules (passes the manager).
    const POPUP_TRIGGERS_REGISTER_ACTION = 'elemacy/action/popups/triggers/register';
    const POPUP_RULES_REGISTER_ACTION    = 'elemacy/action/popups/rules/register';
}
