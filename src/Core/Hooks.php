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
}
