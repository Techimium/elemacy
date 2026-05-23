<?php

namespace Elemacy\Modules\Controls\Contracts;

use Elementor\Controls_Manager;

defined('ABSPATH') || exit;

abstract class AbstractControl implements ControlInterface
{
    abstract public function get_id(): string;

    abstract public function get_section_label(): string;

    abstract public function register_controls($element): void;

    public function get_allowed_sections(): array
    {
        return [
            'section_advanced',
            '_section_style',
            'section_page_style',
            'section_layout',
            'section_layout_container',
        ];
    }

    public function get_section_tab(): string
    {
        return Controls_Manager::TAB_ADVANCED;
    }

    public function get_editor_assets(): array
    {
        return [];
    }

    public function render_element_css($post_css, $element): ?string
    {
        return null;
    }

    public function render_document_css($post_css, $document): ?string
    {
        return null;
    }
}
