<?php

namespace Elemacy\Modules\Widgets\Documents;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elementor\Controls_Manager;
use Elementor\Modules\PageTemplates\Module as PageTemplatesModule;

/** Elementor document for Loop Item templates. */
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

    /**
     * Forces Canvas so a freshly-created loop item opens bare, before _wp_page_template persists.
     */
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

    public const PREVIEW_DATA_SOURCE = 'preview_data_source';

    private ?LoopItemInterface $preview_item = null;
    private ?string $preview_item_key = null;

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

    /**
     * Resolved once per preview settings state: every dynamic tag and nested
     * loop of one editor render asks for it.
     */
    public function get_preview_item(): ?LoopItemInterface
    {
        $settings = $this->get_settings();
        $key = md5((string) wp_json_encode($settings));

        if (null !== $this->preview_item_key && $key === $this->preview_item_key) {
            return $this->preview_item;
        }

        $source = LoopDataSourceRegistry::instance()->get(
            (string) $this->get_settings(self::PREVIEW_DATA_SOURCE)
        );

        $this->preview_item = $source ? $source->resolve_preview_item($settings) : null;
        $this->preview_item_key = $key;

        return $this->preview_item;
    }
}
