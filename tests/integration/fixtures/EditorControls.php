<?php

namespace Elemacy\Tests\Integration\Fixtures;

use Elementor\Core\Frontend\Performance;
use Elementor\Plugin;
use Elementor\Widget_Base;

/**
 * A widget's raw controls as the editor sees them. Elementor drops labels,
 * options and UI controls from the stack outside the editor, preview and REST
 * requests, so this builds the widget's stack in full once, then puts
 * Elementor's request classification back.
 */
final class EditorControls
{
    /** @return array<string, array<string, mixed>> */
    public static function of(Widget_Base $widget): array
    {
        $is_frontend = new \ReflectionProperty(Performance::class, 'is_frontend');
        $is_frontend->setAccessible(true);
        $previous = $is_frontend->getValue();

        try {
            $is_frontend->setValue(null, false);
            Plugin::$instance->controls_manager->delete_stack($widget);

            return $widget->get_controls();
        } finally {
            $is_frontend->setValue(null, $previous);
            Plugin::$instance->controls_manager->delete_stack($widget);
        }
    }
}
