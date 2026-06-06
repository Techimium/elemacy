<?php

namespace Elemacy\Modules\Popups\Support;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Constants\MetaKeys;

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

        $type = get_post_meta($id, MetaKeys::TYPE, true);
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

        $overlay_color = isset($settings[DisplayKeys::OVERLAY_COLOR]) && '' !== $settings[DisplayKeys::OVERLAY_COLOR]
            ? $settings[DisplayKeys::OVERLAY_COLOR]
            : $def['overlay']['color'];

        $overlay_opacity = isset($settings[DisplayKeys::OVERLAY_OPACITY]['size']) && '' !== $settings[DisplayKeys::OVERLAY_OPACITY]['size']
            ? (float) $settings[DisplayKeys::OVERLAY_OPACITY]['size']
            : $def['overlay']['opacity'];

        return static::$cache[$id] = [
            'position'  => isset($settings[DisplayKeys::POSITION]) && '' !== $settings[DisplayKeys::POSITION]
                ? $settings[DisplayKeys::POSITION]
                : $def['position'],
            'sticky'    => 'yes' === ($settings[DisplayKeys::STICKY] ?? ''),
            'overlay'   => [
                'enabled' => isset($settings[DisplayKeys::OVERLAY_ENABLED])
                    ? ('yes' === $settings[DisplayKeys::OVERLAY_ENABLED])
                    : $def['overlay']['enabled'],
                'color'   => $overlay_color,
                'opacity' => $overlay_opacity,
            ],
            'animation' => [
                'in'  => isset($settings[DisplayKeys::ANIMATION_IN]) && '' !== $settings[DisplayKeys::ANIMATION_IN]
                    ? $settings[DisplayKeys::ANIMATION_IN]
                    : $def['animation']['in'],
                'out' => isset($settings[DisplayKeys::ANIMATION_OUT]) && '' !== $settings[DisplayKeys::ANIMATION_OUT]
                    ? $settings[DisplayKeys::ANIMATION_OUT]
                    : $def['animation']['out'],
            ],
            'close'     => [
                'button'           => isset($settings[DisplayKeys::CLOSE_BUTTON])
                    ? ('yes' === $settings[DisplayKeys::CLOSE_BUTTON])
                    : $def['close']['button'],
                'auto_close_s'     => isset($settings[DisplayKeys::AUTO_CLOSE])
                    ? (int) $settings[DisplayKeys::AUTO_CLOSE]
                    : 0,
                'on_overlay_click' => 'yes' === ($settings[DisplayKeys::CLOSE_ON_OVERLAY] ?? ''),
                'on_esc'           => 'yes' === ($settings[DisplayKeys::CLOSE_ON_ESC] ?? ''),
            ],
            'prevent_body_scroll' => 'yes' === ($settings[DisplayKeys::PREVENT_SCROLL] ?? ''),
            'z_index'             => isset($settings[DisplayKeys::Z_INDEX]) && '' !== $settings[DisplayKeys::Z_INDEX]
                ? (int) $settings[DisplayKeys::Z_INDEX]
                : (int) $def['z_index'],
        ];
    }
}
