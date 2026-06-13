<?php

namespace Elemacy\TemplateLibrary\Constants;

defined('ABSPATH') || exit;

/**
 * Post-meta keys owned by the template library. The type discriminator is shared
 * by every library item (theme templates and popups alike); module-specific keys
 * (popup triggers/rules, analytics) stay in their own modules.
 */
class MetaKeys
{
    const TEMPLATE_TYPE = '_elemacy_template_type';
}
