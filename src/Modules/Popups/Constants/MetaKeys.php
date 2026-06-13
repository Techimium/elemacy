<?php

namespace Elemacy\Modules\Popups\Constants;

defined('ABSPATH') || exit;

/**
 * Popup-owned post-meta keys. The type discriminator is not here — it lives in
 * the shared TemplateLibrary\Constants\MetaKeys::TEMPLATE_TYPE.
 */
class MetaKeys
{
    const TRIGGERS = '_elemacy_popup_triggers';
    const RULES    = '_elemacy_popup_rules';
}
