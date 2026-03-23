<?php

namespace Elemacy\Modules\Controls\Services;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class CustomCssManager
{
    /**
     * CustomCssManager constructor.
     */
    public function __construct()
    {
        add_action('elementor/element/after_section_end', [$this, 'register_controls'], 10, 2);

        add_action('elementor/element/parse_css', [$this, 'add_element_custom_css'], 10, 2);
        add_action('elementor/css-file/post/parse', [$this, 'add_page_settings_css']);
    }

    /**
     * Register Custom CSS controls.
     *
     * @param \Elementor\Controls_Stack $element
     * @param string $section_id
     */
    public function register_controls($element, string $section_id): void
    {
        $allowed_sections = [
            'section_advanced',
            'section_page_style',
            'section_layout',
            '_section_style',
        ];

        if (!in_array($section_id, $allowed_sections, true)) {
            return;
        }

        // Avoid double registration if already added
        if ($element->get_controls('elemacy_section_custom_css')) {
            return;
        }

        $element->start_controls_section(
            'elemacy_section_custom_css',
            [
                'label' => esc_html__('Custom CSS (Elemacy)', 'elemacy'),
                'tab' => Controls_Manager::TAB_ADVANCED,
            ]
        );

        $element->add_control(
            'elemacy_custom_css',
            [
                'label' => esc_html__('Add your own custom CSS', 'elemacy'),
                'type' => Controls_Manager::CODE,
                'language' => 'css',
                'description' => sprintf(
                    /* translators: 1: selector keyword */
                    esc_html__('Use %s to target the specific element.', 'elemacy'),
                    '<code>selector</code>'
                ),
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Add custom CSS to the post CSS file for a specific element.
     *
     * @param \Elementor\Core\Files\CSS\Post $post_css
     * @param \Elementor\Element_Base $element
     */
    public function add_element_custom_css($post_css, $element): void
    {
        if ($post_css instanceof \Elementor\Core\DynamicTags\Dynamic_CSS) {
            return;
        }

        $element_settings = $element->get_settings();

        if (empty($element_settings['elemacy_custom_css'])) {
            return;
        }

        $css = trim($element_settings['elemacy_custom_css']);

        if (empty($css)) {
            return;
        }

        // Replace "selector" with the actual element selector
        $css = str_replace('selector', $post_css->get_element_unique_selector($element), $css);

        // Add a css comment for better debugging in the generated CSS
        $css = sprintf('/* Start Elemacy Custom CSS for %s */', $element->get_name()) . $css . '/* End Elemacy Custom CSS */';

        $post_css->get_stylesheet()->add_raw_css($css);
    }

    /**
     * Add page settings custom CSS.
     *
     * @param \Elementor\Core\Files\CSS\Post $post_css
     */
    public function add_page_settings_css($post_css): void
    {
        $post_id = $post_css->get_post_id();
        $document = \Elementor\Plugin::$instance->documents->get($post_id);

        if (!$document) {
            return;
        }

        $custom_css = $document->get_settings('elemacy_custom_css');

        if (empty($custom_css)) {
            return;
        }

        $custom_css = trim($custom_css);

        if (empty($custom_css)) {
            return;
        }

        $custom_css = str_replace('selector', $document->get_css_wrapper_selector(), $custom_css);

        // Add a css comment
        $custom_css = '/* Start Elemacy Page Custom CSS */' . $custom_css . '/* End Elemacy Page Custom CSS */';

        $post_css->get_stylesheet()->add_raw_css($custom_css);
    }
}
