<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

/** Elementor document for Footer templates. */
class FooterDocument extends ChromeDocument
{
    public static function get_type()
    {
        return 'elemacy_footer';
    }

    public function get_name()
    {
        return 'elemacy_footer';
    }

    public static function get_title()
    {
        return esc_html__('Footer', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Footers', 'elemacy');
    }

    public static function get_side(): string
    {
        return 'footer';
    }
}
