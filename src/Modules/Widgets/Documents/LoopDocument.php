<?php

namespace Elemacy\Modules\Widgets\Documents;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\SinglePostPreviewDocument;
use Elementor\Modules\PageTemplates\Module as PageTemplatesModule;

/**
 * Elementor document for Loop Item templates. A loop item is a bare card rendered
 * per post by the Loop Builder widget, so it edits on Canvas and previews against
 * a single real post chosen in Preview Settings.
 */
class LoopDocument extends SinglePostPreviewDocument
{
    public static function get_type()
    {
        return 'elemacy_loop';
    }

    public function get_name()
    {
        return 'elemacy_loop';
    }

    public static function get_title()
    {
        return esc_html__('Loop Item', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Loop Items', 'elemacy');
    }

    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['support_kit']     = true;
        $properties['show_in_library'] = false;
        $properties['admin_tab_group'] = '';

        return $properties;
    }

    // Force Canvas so a freshly-created loop item opens bare, before _wp_page_template persists.
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $template = get_post_meta($data['post_id'], '_wp_page_template', true);

            if (empty($template) || 'default' === $template) {
                $template = PageTemplatesModule::TEMPLATE_CANVAS;
            }

            $data['settings']['template'] = $template;
        }

        parent::__construct($data);
    }
}
