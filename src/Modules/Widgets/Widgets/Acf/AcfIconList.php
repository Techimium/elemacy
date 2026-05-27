<?php

namespace Elemacy\Modules\Widgets\Widgets\Acf;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Services\AcfRepeaterResolver;
use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Widget_Icon_List;

/**
 * Renders an Elementor icon list populated from an ACF repeater field.
 *
 * Extends Widget_Icon_List so layout/style controls work unchanged. The
 * built-in per-item repeater is removed in favour of two text controls (the
 * repeater field name and the sub-field that holds each item's text) plus a
 * single shared icon control reused for every row.
 */
class AcfIconList extends Widget_Icon_List
{
    public function get_name()
    {
        return 'elemacy-acf-icon-list';
    }

    public function get_title()
    {
        return esc_html__('ACF Icon List', 'elemacy');
    }

    public function get_icon()
    {
        return 'eicon-bullet-list';
    }

    public function get_categories()
    {
        return ['elemacy'];
    }

    public function get_keywords()
    {
        return ['icon list', 'list', 'acf', 'repeater', 'dynamic'];
    }

    protected function is_dynamic_content(): bool
    {
        return true;
    }

    protected function register_controls()
    {
        parent::register_controls();

        $this->remove_control('icon_list');

        $this->start_injection([
            'type' => 'section',
            'at'   => 'start',
            'of'   => 'section_icon',
        ]);

        $this->add_control('repeater_field', [
            'label'       => esc_html__('Repeater Field Name', 'elemacy'),
            'description' => esc_html__('Enter the name of your ACF repeater field (e.g., features).', 'elemacy'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'placeholder' => esc_html__('e.g. features', 'elemacy'),
        ]);

        $this->add_control('sub_field', [
            'label'       => esc_html__('Text Sub-Field Name', 'elemacy'),
            'description' => esc_html__('Name of the sub-field inside the repeater that holds each item\'s text.', 'elemacy'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'separator'   => 'after',
            'placeholder' => esc_html__('e.g. label', 'elemacy'),
        ]);

        $this->add_control('selected_icon', [
            'label'            => esc_html__('Icon', 'elemacy'),
            'description'      => esc_html__('Applied to every item in the list.', 'elemacy'),
            'type'             => Controls_Manager::ICONS,
            'default'          => [
                'value'   => 'fas fa-check',
                'library' => 'fa-solid',
            ],
            'fa4compatibility' => 'icon',
        ]);

        $this->end_injection();
    }

    public function get_settings_for_display($setting_key = null)
    {
        $settings = parent::get_settings_for_display();

        $settings['icon_list'] = $this->build_items($settings);

        return null !== $setting_key ? ($settings[$setting_key] ?? null) : $settings;
    }

    /**
     * @return array<int, array{text: string, selected_icon: array}>
     */
    private function build_items(array $settings): array
    {
        $repeater_field = (string) ($settings['repeater_field'] ?? '');
        $sub_field      = (string) ($settings['sub_field'] ?? '');

        if ('' === $repeater_field || '' === $sub_field) {
            return [];
        }

        $rows = AcfRepeaterResolver::resolve($repeater_field);
        $icon = $settings['selected_icon'] ?? [];

        $items = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[$sub_field])) {
                continue;
            }

            $items[] = [
                'text'          => (string) $row[$sub_field],
                'selected_icon' => $icon,
            ];
        }

        return $items;
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['icon_list'])) {
            return;
        }

        $this->add_render_attribute('icon_list', 'class', 'elementor-icon-list-items');
        $this->add_render_attribute('list_item', 'class', 'elementor-icon-list-item');

        if ('inline' === ($settings['view'] ?? '')) {
            $this->add_render_attribute('icon_list', 'class', 'elementor-inline-items');
            $this->add_render_attribute('list_item', 'class', 'elementor-inline-item');
        }
        ?>
        <ul <?php $this->print_render_attribute_string('icon_list'); ?>>
            <?php foreach ($settings['icon_list'] as $index => $item) :
                $repeater_setting_key = $this->get_repeater_setting_key('text', 'icon_list', $index);
                $this->add_render_attribute($repeater_setting_key, 'class', 'elementor-icon-list-text');
                ?>
                <li <?php $this->print_render_attribute_string('list_item'); ?>>
                    <?php if (!empty($item['selected_icon']['value'])) : ?>
                        <span class="elementor-icon-list-icon">
                            <?php Icons_Manager::render_icon($item['selected_icon'], ['aria-hidden' => 'true']); ?>
                        </span>
                    <?php endif; ?>
                    <span <?php $this->print_render_attribute_string($repeater_setting_key); ?>><?php echo esc_html($item['text']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Disable Elementor's JS template — items come from PHP-resolved ACF data
     * and have no client-side representation. Editor preview falls back to the
     * AJAX-rendered PHP output above.
     */
    protected function content_template()
    {
    }
}
