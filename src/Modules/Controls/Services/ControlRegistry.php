<?php

namespace Elemacy\Modules\Controls\Services;

use Elemacy\Modules\Controls\Contracts\ControlInterface;

defined('ABSPATH') || exit;

class ControlRegistry
{
    /** @var array<string, ControlInterface> */
    protected array $controls = [];

    protected bool $listeners_attached = false;

    public function register(ControlInterface $control): void
    {
        $this->controls[$control->get_id()] = $control;
    }

    /** @return array<string, ControlInterface> */
    public function all(): array
    {
        return $this->controls;
    }

    public function get(string $id): ?ControlInterface
    {
        return $this->controls[$id] ?? null;
    }

    public function attach_listeners(): void
    {
        if ($this->listeners_attached) {
            return;
        }

        add_action('elementor/element/after_section_end', [$this, 'inject_controls'], 10, 2);
        add_action('elementor/element/parse_css', [$this, 'parse_element_css'], 10, 2);
        add_action('elementor/css-file/post/parse', [$this, 'parse_document_css']);

        $this->listeners_attached = true;
    }

    /**
     * @param \Elementor\Controls_Stack $element
     */
    public function inject_controls($element, string $section_id): void
    {
        foreach ($this->controls as $control) {
            if (!in_array($section_id, $control->get_allowed_sections(), true)) {
                continue;
            }

            $section_key = $this->section_key($control);

            if ($element->get_controls($section_key)) {
                continue;
            }

            $element->start_controls_section(
                $section_key,
                [
                    'label' => $control->get_section_label(),
                    'tab'   => $control->get_section_tab(),
                ]
            );

            $control->register_controls($element);

            $element->end_controls_section();
        }
    }

    /**
     * @param \Elementor\Core\Files\CSS\Post $post_css
     * @param \Elementor\Element_Base $element
     */
    public function parse_element_css($post_css, $element): void
    {
        foreach ($this->controls as $control) {
            $css = $control->render_element_css($post_css, $element);

            if ($css === null || $css === '') {
                continue;
            }

            $wrapped = sprintf(
                '/* Start Elemacy %1$s for %2$s */%3$s/* End Elemacy %1$s */',
                $control->get_id(),
                $element->get_name(),
                $css
            );

            $post_css->get_stylesheet()->add_raw_css($wrapped);
        }
    }

    /**
     * @param \Elementor\Core\Files\CSS\Post $post_css
     */
    public function parse_document_css($post_css): void
    {
        $post_id  = $post_css->get_post_id();
        $document = \Elementor\Plugin::$instance->documents->get($post_id);

        if (!$document) {
            return;
        }

        foreach ($this->controls as $control) {
            $css = $control->render_document_css($post_css, $document);

            if ($css === null || $css === '') {
                continue;
            }

            $wrapped = sprintf(
                '/* Start Elemacy Page %1$s */%2$s/* End Elemacy Page %1$s */',
                $control->get_id(),
                $css
            );

            $post_css->get_stylesheet()->add_raw_css($wrapped);
        }
    }

    protected function section_key(ControlInterface $control): string
    {
        return 'elemacy_section_' . $control->get_id();
    }
}
