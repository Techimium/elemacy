<?php

namespace Elemacy\Tests\Integration\Fixtures;

use Elementor\Core\Frontend\Performance;
use Elementor\Plugin;
use Elementor\Widget_Base;

/**
 * Describes a nav menu widget's controls as comparable metadata: what the builder
 * sees and what each setting means. Used to take the Elementor Pro reference
 * snapshot (tools/dump-nav-menu-reference.php) and to compare Elemacy's widget
 * against it, so both sides are described the same way.
 *
 * Selectors and prefix classes are reduced to counts and flags: Elemacy targets
 * its own classes on purpose, and the browser parity runner checks the result.
 */
final class NavMenuControlCatalog
{
    /** The widget's own sections; the common Advanced tab and motion effects are not part of it. */
    public const SECTIONS = ['section_layout', 'section_style_main-menu', 'section_style_dropdown', 'style_toggle'];

    private const KEYS = [
        'type', 'label', 'default', 'options', 'size_units', 'range', 'condition', 'conditions',
        'tab', 'section', 'tabs_wrapper', 'inner_tab', 'separator', 'return_value', 'label_on', 'label_off',
        'label_block', 'skin', 'skin_settings', 'recommended', 'exclude_inline_options', 'frontend_available',
        'render_type', 'global', 'description', 'heading', 'alert_type', 'content', 'raw', 'content_classes',
        'selectors_dictionary', 'is_responsive', 'responsive', 'groupType', 'groupPrefix', 'popover',
        'style_transfer', 'save_default', 'fa4compatibility',
    ];

    /** @return list<array<string, mixed>> */
    public static function describe(Widget_Base $widget): array
    {
        $controls = [];

        foreach (self::controls($widget) as $name => $control) {
            $section = $control['type'] === 'section' ? $name : ($control['section'] ?? '');

            if (!in_array($section, self::SECTIONS, true)) {
                continue;
            }

            $controls[] = self::normalise($name, $control);
        }

        return $controls;
    }

    /**
     * The widget's raw controls as the editor sees them. Elementor drops labels,
     * options and UI controls from the stack outside the editor, preview and REST
     * requests, so build this widget's stack in full once, then put Elementor's
     * request classification back.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function controls(Widget_Base $widget): array
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

    private static function normalise(string $name, array $control): array
    {
        $entry = ['name' => $name];

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $control)) {
                $entry[$key] = $control[$key];
            }
        }

        if (!empty($control['prefix_class'])) {
            $entry['prefix_class'] = true;
        }

        if (!empty($control['selectors'])) {
            $entry['selectors'] = count($control['selectors']);
        }

        if ($name === 'menu') {
            // The site's own menus.
            unset($entry['options'], $entry['default'], $entry['content'], $entry['description']);
            $entry['site_dependent'] = true;
        }

        if ($name === 'dropdown' && isset($entry['options'])) {
            // Labels embed the site's breakpoint pixel values; the keys and their order persist.
            $entry['options'] = array_keys($entry['options']);
            $entry['site_dependent'] = true;
        }

        if ($name === 'align_items' && isset($entry['options'])) {
            // Start/End icons swap under RTL; the option keys and titles are what the builder picks.
            $entry['options'] = array_map(static fn (array $option): array => ['title' => $option['title']], $entry['options']);
        }

        return $entry;
    }
}
