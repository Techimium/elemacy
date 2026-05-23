<?php

namespace Elemacy\Modules\Widgets\Widgets\Acf;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Services\AcfRepeaterResolver;
use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Utils;
use Elementor\Widget_Accordion;

/**
 * Renders an Elementor accordion populated from an ACF repeater field.
 *
 * Extends the core Widget_Accordion so all of its style controls (icons,
 * typography, colors, borders, etc.) work unchanged. We replace the static
 * `tabs` repeater with three text controls that point at an ACF repeater and
 * its title / content sub-fields, then synthesize `$settings['tabs']` at
 * display time from the resolved rows.
 */
class AcfAccordion extends Widget_Accordion
{
    public function get_name()
    {
        return 'elemacy-acf-accordion';
    }

    public function get_title()
    {
        return esc_html__('ACF Accordion', 'elemacy');
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
        return ['accordion', 'faq', 'acf', 'repeater', 'dynamic'];
    }

    protected function is_dynamic_content(): bool
    {
        return true;
    }

    public function show_in_panel(): bool
    {
        return true;
    }

    public function has_widget_inner_wrapper(): bool
    {
        return false;
    }

    public function get_script_depends()
    {
        return ['elemacy-acf-accordion'];
    }

    /**
     * Inherits the .elementor-widget-accordion class so the core accordion CSS
     * targets this widget as well.
     */
    protected function get_html_wrapper_class()
    {
        return 'elementor-widget-accordion';
    }

    protected function register_controls()
    {
        parent::register_controls();

        $this->remove_control('tabs');
        $this->remove_control('deprecation_message');

        $this->start_injection([
            'type' => 'section',
            'at'   => 'start',
            'of'   => 'section_title',
        ]);

        $this->add_control('repeater_field', [
            'label'       => esc_html__('Repeater Field Name', 'elemacy'),
            'description' => esc_html__('Enter the name of your ACF repeater field (e.g., faq_items).', 'elemacy'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'placeholder' => esc_html__('e.g. faq_items', 'elemacy'),
        ]);

        $this->add_control('title_sub_field', [
            'label'       => esc_html__('Title Sub-Field Name', 'elemacy'),
            'description' => esc_html__('Name of the sub-field inside the repeater that holds the accordion title.', 'elemacy'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'placeholder' => esc_html__('e.g. question', 'elemacy'),
        ]);

        $this->add_control('content_sub_field', [
            'label'       => esc_html__('Content Sub-Field Name', 'elemacy'),
            'description' => esc_html__('Name of the sub-field inside the repeater that holds the accordion content.', 'elemacy'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'separator'   => 'after',
            'placeholder' => esc_html__('e.g. answer', 'elemacy'),
        ]);

        $this->end_injection();
    }

    public function get_settings_for_display($setting_key = null)
    {
        $settings = parent::get_settings_for_display();

        $settings['tabs'] = $this->build_tabs($settings);

        return null !== $setting_key ? ($settings[$setting_key] ?? null) : $settings;
    }

    /**
     * @return array<int, array{tab_title: string, tab_content: string}>
     */
    private function build_tabs(array $settings): array
    {
        $repeater_field    = (string) ($settings['repeater_field'] ?? '');
        $title_sub_field   = (string) ($settings['title_sub_field'] ?? '');
        $content_sub_field = (string) ($settings['content_sub_field'] ?? '');

        if ('' === $repeater_field || '' === $title_sub_field || '' === $content_sub_field) {
            return [];
        }

        $rows = AcfRepeaterResolver::resolve($repeater_field);
        $tabs = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tabs[] = [
                'tab_title'   => (string) ($row[$title_sub_field] ?? ''),
                'tab_content' => (string) ($row[$content_sub_field] ?? ''),
            ];
        }

        return $tabs;
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['tabs'])) {
            return;
        }

        $migrated = isset($settings['__fa4_migrated']['selected_icon']);
        $is_new   = empty($settings['icon']) && Icons_Manager::is_migration_allowed();
        $has_icon = (!$is_new || !empty($settings['selected_icon']['value']));
        $id_int   = substr($this->get_id_int(), 0, 3);
        ?>
        <div class="elementor-accordion" role="tablist">
            <?php foreach ($settings['tabs'] as $index => $item) :
                $tab_count = $index + 1;

                $tab_title_setting_key   = $this->get_repeater_setting_key('tab_title', 'tabs', $index);
                $tab_content_setting_key = $this->get_repeater_setting_key('tab_content', 'tabs', $index);

                $this->add_render_attribute($tab_title_setting_key, [
                    'id'            => 'elementor-tab-title-' . $id_int . $tab_count,
                    'class'         => ['elementor-tab-title'],
                    'data-tab'      => $tab_count,
                    'role'          => 'tab',
                    'aria-controls' => 'elementor-tab-content-' . $id_int . $tab_count,
                    'aria-expanded' => 'false',
                ]);

                $this->add_render_attribute($tab_content_setting_key, [
                    'id'              => 'elementor-tab-content-' . $id_int . $tab_count,
                    'class'           => ['elementor-tab-content', 'elementor-clearfix'],
                    'data-tab'        => $tab_count,
                    'role'            => 'tabpanel',
                    'aria-labelledby' => 'elementor-tab-title-' . $id_int . $tab_count,
                ]);
                ?>
                <div class="elementor-accordion-item">
                    <<?php Utils::print_validated_html_tag($settings['title_html_tag']); ?> <?php $this->print_render_attribute_string($tab_title_setting_key); ?>>
                        <?php if ($has_icon) : ?>
                            <span class="elementor-accordion-icon elementor-accordion-icon-<?php echo esc_attr($settings['icon_align']); ?>" aria-hidden="true">
                                <?php if ($is_new || $migrated) : ?>
                                    <span class="elementor-accordion-icon-closed"><?php Icons_Manager::render_icon($settings['selected_icon']); ?></span>
                                    <span class="elementor-accordion-icon-opened"><?php Icons_Manager::render_icon($settings['selected_active_icon']); ?></span>
                                <?php else : ?>
                                    <i class="elementor-accordion-icon-closed <?php echo esc_attr($settings['icon']); ?>"></i>
                                    <i class="elementor-accordion-icon-opened <?php echo esc_attr($settings['icon_active']); ?>"></i>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <a class="elementor-accordion-title" tabindex="0"><?php echo esc_html($item['tab_title']); ?></a>
                    </<?php Utils::print_validated_html_tag($settings['title_html_tag']); ?>>
                    <div <?php $this->print_render_attribute_string($tab_content_setting_key); ?>><?php
                        $this->print_text_editor($item['tab_content']);
                    ?></div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($settings['faq_schema']) && 'yes' === $settings['faq_schema']) :
                $json = [
                    '@context'   => 'https://schema.org',
                    '@type'      => 'FAQPage',
                    'mainEntity' => [],
                ];

                foreach ($settings['tabs'] as $item) {
                    $json['mainEntity'][] = [
                        '@type'          => 'Question',
                        'name'           => wp_strip_all_tags($item['tab_title']),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => $this->parse_text_editor($item['tab_content']),
                        ],
                    ];
                }
                ?>
                <script type="application/ld+json"><?php echo wp_json_encode($json); ?></script>
            <?php endif; ?>
        </div>
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
