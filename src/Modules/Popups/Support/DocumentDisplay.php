<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

/**
 * Reads display/behavior settings off the popup's Elementor document
 * (registered in PopupDocument::register_controls) and maps them into the
 * engine.js `display` config shape.
 *
 * Only behavior + overlay color/opacity live here; width/height/z-index are
 * handled by the document's generated post CSS and are intentionally omitted.
 */
class DocumentDisplay
{
    /** @var array<int, array> Per-request cache, keyed by popup id. */
    protected static $cache = [];

    /**
     * Build the engine `display` config for a popup from its Elementor
     * document settings, falling back to per-type DisplayDefaults.
     *
     * @param int $id Popup post ID.
     * @return array
     */
    public static function for_popup(int $id): array
    {
        if (isset(static::$cache[$id])) {
            return static::$cache[$id];
        }

        $type = get_post_meta($id, '_elemacy_popup_type', true);
        $def  = DisplayDefaults::for_type($type ? $type : 'popup');

        $settings = [];

        if (class_exists('\Elementor\Plugin')) {
            $document = \Elementor\Plugin::$instance->documents->get($id);

            if ($document) {
                $raw = $document->get_settings();

                if (is_array($raw)) {
                    $settings = $raw;
                }
            }
        }

        $overlay_color = isset($settings['elemacy_overlay_color']) && '' !== $settings['elemacy_overlay_color']
            ? $settings['elemacy_overlay_color']
            : $def['overlay']['color'];

        $overlay_opacity = isset($settings['elemacy_overlay_opacity']['size']) && '' !== $settings['elemacy_overlay_opacity']['size']
            ? (float) $settings['elemacy_overlay_opacity']['size']
            : $def['overlay']['opacity'];

        return static::$cache[$id] = [
            'position'  => isset($settings['elemacy_position']) && '' !== $settings['elemacy_position']
                ? $settings['elemacy_position']
                : $def['position'],
            'sticky'    => 'yes' === ($settings['elemacy_sticky'] ?? ''),
            'overlay'   => [
                'enabled' => isset($settings['elemacy_overlay_enabled'])
                    ? ('yes' === $settings['elemacy_overlay_enabled'])
                    : $def['overlay']['enabled'],
                'color'   => $overlay_color,
                'opacity' => $overlay_opacity,
            ],
            'animation' => [
                'in'  => isset($settings['elemacy_animation_in']) && '' !== $settings['elemacy_animation_in']
                    ? $settings['elemacy_animation_in']
                    : $def['animation']['in'],
                'out' => isset($settings['elemacy_animation_out']) && '' !== $settings['elemacy_animation_out']
                    ? $settings['elemacy_animation_out']
                    : $def['animation']['out'],
            ],
            'close'     => [
                'button'           => isset($settings['elemacy_close_button'])
                    ? ('yes' === $settings['elemacy_close_button'])
                    : $def['close']['button'],
                'auto_close_s'     => isset($settings['elemacy_auto_close'])
                    ? (int) $settings['elemacy_auto_close']
                    : 0,
                'on_overlay_click' => 'yes' === ($settings['elemacy_close_on_overlay'] ?? ''),
                'on_esc'           => 'yes' === ($settings['elemacy_close_on_esc'] ?? ''),
            ],
            'prevent_body_scroll' => 'yes' === ($settings['elemacy_prevent_scroll'] ?? ''),
            'z_index'             => isset($settings['elemacy_z_index']) && '' !== $settings['elemacy_z_index']
                ? (int) $settings['elemacy_z_index']
                : (int) $def['z_index'],
        ];
    }
}
