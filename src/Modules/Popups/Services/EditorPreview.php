<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Constants\MetaKeys;
use Elemacy\Modules\Popups\PostTypes\PopupPostType;
use Elemacy\Support\Utils;

/**
 * Renders the popup "chrome" (overlay + framed box + position + a real close
 * button) inside the Elementor editor preview, so authors see the popup as it
 * appears on the site — like Elementor Pro's popup editor.
 *
 * - Structural chrome CSS is injected here; the dynamic parts read CSS custom
 *   properties set by the document controls (overlay, position), so they update
 *   live as the author edits.
 * - A real `.elemacy-popup__close` button is injected by preview.js so the Close
 *   Button style controls — including :hover and :active — preview accurately.
 *   Its look comes entirely from the document controls' generated CSS.
 */
class EditorPreview
{
    public function register_hooks(): void
    {
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueue']);
        add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_script']);
    }

    public function enqueue(): void
    {
        if (!$this->is_popup_preview()) {
            return;
        }

        $css = $this->build_css((int) get_queried_object_id());

        wp_register_style('elemacy-popup-preview', false, [], ELEMACY_VERSION);
        wp_enqueue_style('elemacy-popup-preview');
        wp_add_inline_style('elemacy-popup-preview', $css);
    }

    public function enqueue_script(): void
    {
        if (!$this->is_popup_preview()) {
            return;
        }

        $rel  = 'src/Modules/Popups/assets/scripts/preview.js';
        $file = Utils::get_plugin_path($rel);
        $ver  = is_readable($file) && filemtime($file) ? (string) filemtime($file) : ELEMACY_VERSION;

        wp_enqueue_script(
            'elemacy-popup-preview',
            Utils::get_plugin_url($rel),
            ['jquery'],
            $ver,
            true
        );
    }

    protected function is_popup_preview(): bool
    {
        $id = (int) get_queried_object_id();

        return $id > 0 && PopupPostType::POST_TYPE === get_post_type($id);
    }

    protected function build_css(int $id): string
    {
        $type = get_post_meta($id, MetaKeys::TYPE, true);
        $type = $type ? $type : 'popup';
        $wrap = '.elementor-' . $id;

        switch ($type) {
            case 'topbar':
                $css = $this->topbar_css($wrap);
                break;
            case 'banner':
            case 'floating':
            case 'popup':
            default:
                $css = $this->frame_css($wrap);
                break;
        }

        return $css . $this->overlay_css() . $this->close_base_css();
    }

    /**
     * Centered/positioned box (popup + floating). Alignment comes from the
     * Position control via the --elemacy-align / --elemacy-justify variables.
     */
    protected function frame_css(string $wrap): string
    {
        $css  = 'body{margin:0;min-height:100vh;box-sizing:border-box;display:flex;padding:30px;'
            . 'align-items:var(--elemacy-align,center);justify-content:var(--elemacy-justify,center);}';
        $css .= ':where(' . $wrap . '){background:#fff;box-shadow:0 10px 40px rgba(0,0,0,0.2);border-radius:4px;}';
        $css .= $wrap . '{position:relative;z-index:1;max-width:100%;}';

        return $css;
    }

    protected function topbar_css(string $wrap): string
    {
        $css  = 'body{margin:0;}';
        $css .= ':where(' . $wrap . '){background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.12);}';
        $css .= $wrap . '{position:relative;z-index:1;}';

        return $css;
    }

    /**
     * Overlay backdrop — visibility/colour/opacity come from the overlay
     * controls' variables, so they toggle and recolour live.
     */
    protected function overlay_css(): string
    {
        return 'body::before{content:"";position:fixed;inset:0;z-index:0;pointer-events:none;'
            . 'display:var(--elemacy-ov-display,none);'
            . 'background:var(--elemacy-ov-color,#000000);'
            . 'opacity:var(--elemacy-ov-opacity,0.6);}';
    }

    /**
     * Structural styles for the injected close button. Visibility comes from the
     * Close Button toggle (display) and all look/size from the Close Button
     * style controls, so everything previews live.
     */
    protected function close_base_css(): string
    {
        return '.elemacy-popup__close{position:absolute;display:none;z-index:2;align-items:center;'
            . 'justify-content:center;box-sizing:border-box;padding:0;margin:0;line-height:1;cursor:pointer;}';
    }
}
