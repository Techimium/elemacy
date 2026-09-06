<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\SinglePostPreviewDocument;

/**
 * Elementor document for Single templates. A single template renders one post of
 * its matched type, so it previews against a single real post chosen in Preview
 * Settings. It edits Full Width so the theme/Elemacy header and footer wrap the
 * editable content, like Elementor Pro's theme builder.
 */
class SingleDocument extends SinglePostPreviewDocument
{
    public static function get_type()
    {
        return 'elemacy_single';
    }

    public function get_name()
    {
        return 'elemacy_single';
    }

    public static function get_title()
    {
        return esc_html__('Single', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Singles', 'elemacy');
    }

    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['support_kit']     = true;
        $properties['show_in_library'] = false;
        $properties['admin_tab_group'] = '';

        return $properties;
    }
}
