<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

/** Elementor document for Header templates. */
class HeaderDocument extends ChromeDocument
{
    public static function get_type()
    {
        return 'elemacy_header';
    }

    public function get_name()
    {
        return 'elemacy_header';
    }

    public static function get_title()
    {
        return esc_html__('Header', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Headers', 'elemacy');
    }
}
