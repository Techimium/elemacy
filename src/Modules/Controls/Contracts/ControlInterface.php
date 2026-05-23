<?php

namespace Elemacy\Modules\Controls\Contracts;

defined('ABSPATH') || exit;

interface ControlInterface
{
    /**
     * Stable unique slug. Used as the Elementor section id prefix.
     * Example: 'custom_css' becomes section id 'elemacy_section_custom_css'.
     */
    public function get_id(): string;

    /**
     * Elementor section IDs (e.g. 'section_advanced', 'section_layout_container')
     * after which this control's section should be injected.
     *
     * @return string[]
     */
    public function get_allowed_sections(): array;

    /**
     * Label shown on the section header in the Elementor panel.
     */
    public function get_section_label(): string;

    /**
     * Elementor tab constant (e.g. \Elementor\Controls_Manager::TAB_ADVANCED).
     */
    public function get_section_tab(): string;

    /**
     * Register the actual fields on the element. The registry opens and
     * closes the controls section around this call.
     *
     * @param \Elementor\Controls_Stack $element
     */
    public function register_controls($element): void;

    /**
     * Editor-side JS/CSS assets this control needs.
     * Each entry: [ 'handle' => string, 'src' => string, 'deps' => string[], 'in_footer' => bool ].
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_editor_assets(): array;

    /**
     * Output element-level CSS for the post stylesheet. Return null to skip.
     *
     * @param \Elementor\Core\Files\CSS\Post $post_css
     * @param \Elementor\Element_Base $element
     */
    public function render_element_css($post_css, $element): ?string;

    /**
     * Output document/page-level CSS for the post stylesheet. Return null to skip.
     *
     * @param \Elementor\Core\Files\CSS\Post $post_css
     * @param \Elementor\Core\DocumentTypes\PageBase $document
     */
    public function render_document_css($post_css, $document): ?string;
}
