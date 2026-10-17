<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

final class Hooks
{
    // Core lifecycle.
    public const LOADED_ACTION           = 'elemacy/action/loaded';
    public const REGISTER_MODULES_ACTION = 'elemacy/action/register_modules';

    // Fired early — after dependency/compatibility checks, before any
    // provider's boot runs — so a service provider can
    // register itself (passes the Elemacy instance; call
    // register_provider() on it). Pro subscribes from its own plugin
    // bootstrap, so its own provider registration does not depend on the
    // later LOADED_ACTION below.
    public const REGISTER_PROVIDERS_ACTION = 'elemacy/action/register_providers';

    // Fired after the stored DB version changes (passes old, new version strings).
    public const VERSION_UPDATED_ACTION = 'elemacy/action/version_updated';

    // Lets extensions declare additional meta keys DocumentDuplicator must
    // exclude from a duplicate — their own generated caches/analytics beyond
    // the base exclusions it already applies (passes the excluded-keys array,
    // the library group, and the source WP_Post). Popups additionally still
    // honor the pre-existing POPUP_DUPLICATE_EXCLUDED_META_FILTER below.
    public const LIBRARY_DUPLICATE_EXCLUDED_META_FILTER = 'elemacy/filter/library/duplicate_excluded_meta';

    // Fired once after a library item's create/update/duplicate/delete is
    // fully complete — authoritative metadata included — passing a single
    // LibraryChanged payload. Cache invalidation and similar listeners
    // subscribe here instead of each of the three builder services.
    public const LIBRARY_CHANGED_ACTION = 'elemacy/action/library/changed';

    // Template library
    // Fired so modules/add-ons can register library item types (passes the TypeRegistry).
    public const LIBRARY_TYPES_REGISTER_ACTION = 'elemacy/action/library/types/register';

    // Fired early (before Elementor's own style pass) so modules can register
    // post IDs whose Elementor content renders outside their own page — a
    // popup, a theme template, etc. (passes the TemplateAssetsRegistrar).
    public const TEMPLATE_ASSETS_COLLECT_ACTION = 'elemacy/action/template_assets/collect';

    // Admin scripts.
    public const ADMIN_SCRIPT_DATA_FILTER     = 'elemacy/filter/admin_script_data';
    public const ENQUEUE_ADMIN_SCRIPTS_ACTION = 'elemacy/action/enqueue_admin_scripts';

    // Frontend scripts.
    public const FRONTEND_SCRIPT_DATA_FILTER     = 'elemacy/filter/frontend_script_data';

    // Widgets
    // Filters the widget catalog (the Config/widgets.php array) so add-ons can
    // register widgets into Elementor, the admin toggle list, and the REST API
    // in one place. Added widgets must register their own frontend asset handles.
    public const WIDGETS_REGISTER_FILTER = 'elemacy/filter/widgets/register';

    // Theme Builder.
    public const THEME_BUILDER_TEMPLATE_TYPES_FILTER  = 'elemacy/filter/theme_builder/template_types';

    // Whether the active theme's own header or footer is replaced this request,
    // by whichever adapter serves the theme (passes true and the side,
    // 'header' or 'footer'; not asked for a side with no template).
    // Returning false leaves that side to the theme, as though no template
    // were configured for it.
    public const THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER = 'elemacy/filter/theme_builder/suppress_native_chrome';

    // Fired so add-ons can register theme locations (passes the LocationRegistry).
    public const THEME_BUILDER_LOCATIONS_REGISTER_ACTION = 'elemacy/action/theme_builder/locations/register';

    // Fired after the Theme Builder module registers its built-in theme
    // compatibility adapters, so add-ons can register their own for an
    // unrecognized theme slug without editing CompatibilityManager (passes
    // the CompatibilityManager).
    public const THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION = 'elemacy/action/theme_builder/compatibility/register';

    // Fired after the Widgets module registers its built-in loop data sources,
    // so add-ons can register their own (passes the LoopDataSourceRegistry).
    public const LOOP_DATA_SOURCES_REGISTER_ACTION = 'elemacy/action/loop/data_sources/register';

    // Fired after the Widgets module registers its built-in public pagination
    // placement resolvers (page, nested loop), so other modules/add-ons can
    // register their own (passes the PlacementResolverRegistry).
    public const LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION = 'elemacy/action/loop/placement_resolvers/register';

    // Fired after free registers its elemacy_query picker resolvers, so add-ons
    // can register their own kind of live-search picker (passes the
    // QueryResolverRegistry). Runs on the first picker search, never earlier.
    public const QUERY_RESOLVERS_REGISTER_ACTION = 'elemacy/action/query_control/resolvers/register';

    // Preview (editor real-data preview)
    // Lets add-ons adjust the WP_Query args a previewable document renders against
    // (passes the args array and the document instance).
    public const PREVIEW_QUERY_ARGS_FILTER = 'elemacy/filter/preview/query_args';

    // Theme Builder — display conditions.
    public const CONDITIONS_EXCLUDED_POST_TYPES_FILTER = 'elemacy/filter/conditions/excluded_post_types';
    public const CONDITIONS_EXCLUDED_TAXONOMIES_FILTER = 'elemacy/filter/conditions/excluded_taxonomies';

    // Fired after free registers its conditions (passes the ConditionManager).
    public const CONDITIONS_REGISTER_ACTION = 'elemacy/action/conditions/register';

    // Popups.
    public const POPUP_DISPLAY_SETTINGS_FILTER     = 'elemacy/filter/popups/display_settings';
    public const POPUP_FRONTEND_CONFIG_FILTER      = 'elemacy/filter/popups/frontend_config';
    public const POPUP_MATCHED_FILTER              = 'elemacy/filter/popups/matched';
    public const POPUP_DUPLICATE_EXCLUDED_META_FILTER = 'elemacy/filter/popups/duplicate_excluded_meta';

    // Fired so add-ons can register popup triggers / rules (passes the manager).
    public const POPUP_TRIGGERS_REGISTER_ACTION = 'elemacy/action/popups/triggers/register';
    public const POPUP_RULES_REGISTER_ACTION    = 'elemacy/action/popups/rules/register';

    // Dynamic Tags.
    // Fired after free registers its dynamic tags and groups, so add-ons can
    // register their own (passes Elementor's dynamic tags manager). A tag
    // registered under a built-in tag's name replaces it.
    public const DYNAMIC_TAGS_REGISTER_ACTION = 'elemacy/action/dynamic_tags/register';
}
