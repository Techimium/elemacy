<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

final class Hooks
{
    // Core lifecycle
    const LOADED           = 'elemacy/loaded';
    const REGISTER_MODULES = 'elemacy/register_modules';

    // Admin scripts
    const ADMIN_SCRIPT_DATA     = 'elemacy/admin_script_data';
    const ENQUEUE_ADMIN_SCRIPTS = 'elemacy/enqueue_admin_scripts';

    // Theme Builder
    const THEME_BUILDER_RESOLVE_TEMPLATE = 'elemacy/theme_builder/resolve_template';
    const THEME_BUILDER_TEMPLATE_TYPES   = 'elemacy/theme_builder/template_types';
    const THEME_BUILDER_FALLBACK_HEADER  = 'elemacy/theme_builder/fallback_header';

    // Controls
    const REGISTER_CONTROLS = 'elemacy/register_controls';

}
