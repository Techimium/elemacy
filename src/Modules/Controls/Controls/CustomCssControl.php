<?php

namespace Elemacy\Modules\Controls\Controls;

use Elemacy\Modules\Controls\Contracts\AbstractControl;
use Elemacy\Support\Utils;
use Elementor\Controls_Manager;

defined('ABSPATH') || exit;

class CustomCssControl extends AbstractControl
{
    public const SETTING_KEY = 'elemacy_custom_css';

    public function get_id(): string
    {
        return 'custom_css';
    }

    public function get_section_label(): string
    {
        return esc_html__('Custom CSS (Elemacy)', 'elemacy');
    }

    public function register_controls($element): void
    {
        $element->add_control(
            self::SETTING_KEY,
            [
                'label'       => esc_html__('Add your own custom CSS', 'elemacy'),
                'type'        => Controls_Manager::CODE,
                'language'    => 'css',
                'description' => sprintf(
                    /* translators: %s: selector keyword */
                    esc_html__('Use %s to target the specific element.', 'elemacy'),
                    '<code>selector</code>'
                ),
            ]
        );
    }

    public function get_editor_assets(): array
    {
        return [[
            'handle'    => 'elemacy-custom-css-editor',
            'src'       => Utils::get_plugin_url('src/Modules/Controls/assets/scripts/custom-css.js'),
            'deps'      => ['jquery', 'elementor-editor'],
            'in_footer' => true,
        ]];
    }

    public function render_element_css($post_css, $element): ?string
    {
        if ($post_css instanceof \Elementor\Core\DynamicTags\Dynamic_CSS) {
            return null;
        }

        $settings = $element->get_settings();
        $css      = isset($settings[self::SETTING_KEY]) ? trim($settings[self::SETTING_KEY]) : '';

        if ($css === '') {
            return null;
        }

        return str_replace('selector', $post_css->get_element_unique_selector($element), $css);
    }

    public function render_document_css($post_css, $document): ?string
    {
        $css = $document->get_settings(self::SETTING_KEY);

        if (!is_string($css)) {
            return null;
        }

        $css = trim($css);

        if ($css === '') {
            return null;
        }

        return str_replace('selector', $document->get_css_wrapper_selector(), $css);
    }
}
