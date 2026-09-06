<?php

namespace Elemacy\Modules\Widgets\Documents;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Modules\Widgets\Contracts\LoopItemInterface;
use Elemacy\Modules\Widgets\Services\LoopDataSourceRegistry;
use Elementor\Controls_Manager;
use Elementor\Modules\PageTemplates\Module as PageTemplatesModule;

/**
 * Elementor document for Loop Item templates. A loop item is a bare card
 * rendered per item by the Loop Builder widget, so it edits on Canvas and
 * previews against one real item from whichever registered data source
 * (Posts, Terms, Users, ACF Repeater, …) is chosen in Preview Settings —
 * the same registry Loop Grid/Carousel use to render, so a newly registered
 * source gets standalone preview support automatically.
 */
class LoopDocument extends PreviewablePageBase
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

    const PREVIEW_DATA_SOURCE = 'preview_data_source';

    protected function register_preview_controls(): void
    {
        $registry = LoopDataSourceRegistry::instance();

        $options = [];
        foreach ($registry->all() as $key => $source) {
            $options[$key] = $source->get_label();
        }

        $this->add_control(
            self::PREVIEW_DATA_SOURCE,
            [
                'label' => esc_html__('Preview Data Source', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $options,
                'default' => 'posts',
            ]
        );

        // Every registered source's preview controls are always registered;
        // each scopes its own visibility with a preview_data_source
        // condition, mirroring how LoopGrid registers every source's Query
        // controls unconditionally and lets `condition` hide the rest.
        foreach ($registry->all() as $source) {
            $source->register_preview_controls($this);
        }
    }

    public function get_preview_item(): ?LoopItemInterface
    {
        $source = LoopDataSourceRegistry::instance()->get(
            (string) $this->get_settings(self::PREVIEW_DATA_SOURCE)
        );

        if (!$source) {
            return null;
        }

        return $source->resolve_preview_item($this->get_settings());
    }
}
