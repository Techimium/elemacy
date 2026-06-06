<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

final class Hooks
{
    // Core lifecycle
    const LOADED_ACTION           = 'elemacy/action/loaded';
    const REGISTER_MODULES_ACTION = 'elemacy/action/register_modules';

    // Admin scripts
    const ADMIN_SCRIPT_DATA_FILTER     = 'elemacy/filter/admin_script_data';
    const ENQUEUE_ADMIN_SCRIPTS_ACTION = 'elemacy/action/enqueue_admin_scripts';

    // Theme Builder
    const THEME_BUILDER_TEMPLATE_TYPES_FILTER  = 'elemacy/filter/theme_builder/template_types';
    const THEME_BUILDER_FALLBACK_HEADER_ACTION = 'elemacy/action/theme_builder/fallback_header';

    // Theme Builder — display conditions
    const CONDITIONS_EXCLUDED_POST_TYPES_FILTER = 'elemacy/filter/conditions/excluded_post_types';
    const CONDITIONS_EXCLUDED_TAXONOMIES_FILTER = 'elemacy/filter/conditions/excluded_taxonomies';

    // Fired after free registers its conditions (passes the ConditionManager).
    const CONDITIONS_REGISTER_ACTION = 'elemacy/action/conditions/register';

    // Popups
    const POPUP_TYPES_FILTER                = 'elemacy/filter/popups/types';
    const POPUP_DISPLAY_SETTINGS_FILTER     = 'elemacy/filter/popups/display_settings';
    const POPUP_FRONTEND_CONFIG_FILTER      = 'elemacy/filter/popups/frontend_config';
    const POPUP_MATCHED_FILTER              = 'elemacy/filter/popups/matched';
    const POPUP_DUPLICATE_EXCLUDED_META_FILTER = 'elemacy/filter/popups/duplicate_excluded_meta';

    // Fired so add-ons can register popup triggers / rules (passes the manager).
    const POPUP_TRIGGERS_REGISTER_ACTION = 'elemacy/action/popups/triggers/register';
    const POPUP_RULES_REGISTER_ACTION    = 'elemacy/action/popups/rules/register';
}
