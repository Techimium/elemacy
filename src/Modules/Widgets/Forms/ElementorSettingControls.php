<?php

namespace Elemacy\Modules\Widgets\Forms;

use Elemacy\Forms\Contracts\FieldTypeInterface;
use Elemacy\Forms\Settings\Setting;
use Elementor\Controls_Manager;

defined('ABSPATH') || exit;

/**
 * Presents the engine's builder-neutral settings as Elementor controls, so
 * a field type or action registered by any add-on gets its editor UI here
 * without writing Elementor code.
 */
final class ElementorSettingControls
{
    private const TYPES = [
        Setting::TEXT => Controls_Manager::TEXT,
        Setting::TEXTAREA => Controls_Manager::TEXTAREA,
        Setting::NUMBER => Controls_Manager::NUMBER,
        Setting::SWITCHER => Controls_Manager::SWITCHER,
        Setting::SELECT => Controls_Manager::SELECT,
    ];

    /** The Elementor control arguments for one setting, merged with $extra. */
    public static function control(Setting $setting, array $extra = []): array
    {
        $control = [
            'label' => $setting->label(),
            'type' => self::TYPES[$setting->kind()],
        ];

        if ('' !== $setting->default() && null !== $setting->default()) {
            $control['default'] = $setting->default();
        }

        if ('' !== $setting->description()) {
            $control['description'] = $setting->description();
        }

        if ('' !== $setting->placeholder()) {
            $control['placeholder'] = $setting->placeholder();
        }

        if (Setting::SWITCHER === $setting->kind()) {
            $control['return_value'] = Setting::ON;
            $control += ['default' => ''];
        }

        if (Setting::SELECT === $setting->kind()) {
            $control['options'] = $setting->options();
        }

        if (Setting::TEXTAREA === $setting->kind()) {
            $control['rows'] = 'options' === $setting->key() ? 10 : 5;
        }

        return array_merge($control, $extra);
    }

    /**
     * Every field-type setting once, in first-declared order, with the type
     * keys that declare it (for the repeater control's condition).
     *
     * @param FieldTypeInterface[] $types
     * @return array<string, array{setting: Setting, types: string[]}>
     */
    public static function field_settings(array $types): array
    {
        $settings = [];

        foreach ($types as $type) {
            foreach ($type->settings() as $setting) {
                $settings[$setting->key()] = $settings[$setting->key()] ?? [
                    'setting' => $setting,
                    'types' => [],
                ];
                $settings[$setting->key()]['types'][] = $type->key();
            }
        }

        return $settings;
    }
}
