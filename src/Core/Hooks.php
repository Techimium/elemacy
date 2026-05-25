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
    const THEME_BUILDER_RESOLVE_TEMPLATE_FILTER = 'elemacy/filter/theme_builder/resolve_template';
    const THEME_BUILDER_TEMPLATE_TYPES_FILTER   = 'elemacy/filter/theme_builder/template_types';
    const THEME_BUILDER_FALLBACK_HEADER_ACTION  = 'elemacy/action/theme_builder/fallback_header';

    // Theme Builder — template request extensibility
    const THEME_BUILDER_REQUEST_RULES_FILTER   = 'elemacy/filter/theme_builder/request_rules';
    const THEME_BUILDER_REQUEST_FILTERS_FILTER = 'elemacy/filter/theme_builder/request_filters';

    // Theme Builder — controller lifecycle
    const THEME_BUILDER_TEMPLATE_AFTER_CREATE_ACTION    = 'elemacy/action/theme_builder/template/after_create';
    const THEME_BUILDER_TEMPLATE_AFTER_UPDATE_ACTION    = 'elemacy/action/theme_builder/template/after_update';
    const THEME_BUILDER_TEMPLATE_AFTER_DUPLICATE_ACTION = 'elemacy/action/theme_builder/template/after_duplicate';
    const THEME_BUILDER_TEMPLATE_AFTER_DELETE_ACTION    = 'elemacy/action/theme_builder/template/after_delete';

    // Theme Builder — resource shape
    const THEME_BUILDER_TEMPLATE_RESOURCE_FILTER = 'elemacy/filter/theme_builder/template_resource';
}
