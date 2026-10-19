<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elementor\Core\DocumentTypes\PageBase;

/**
 * Elementor document for 404 templates. A not-found page has no post or query
 * to preview against, so it is a plain page document: Page Layout, Body Style
 * and Hide Title, like the other content locations, without Preview Settings.
 */
class NotFoundDocument extends PageBase
{
    public static function get_type()
    {
        return 'elemacy_404';
    }

    public function get_name()
    {
        return 'elemacy_404';
    }

    public static function get_title()
    {
        return esc_html__('404 Page', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('404 Pages', 'elemacy');
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
