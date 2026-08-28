<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Support\PopupTypes;
use Elemacy\Support\Utils;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;

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

        if ($id <= 0 || LibraryPostType::POST_TYPE !== get_post_type($id)) {
            return false;
        }

        $type = (string) get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true);

        return in_array($type, TypeRegistry::instance()->names_in_group('popup'), true);
    }

    protected function build_css(int $id): string
    {
        $type = get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true);
        $type = $type ? $type : PopupTypes::POPUP;
        $wrap = '.elementor-' . $id;

        $css = PopupTypes::TOPBAR === $type
            ? $this->topbar_css($wrap)
            : $this->frame_css($wrap);

        // Only the modal popup has a backdrop.
        if (PopupTypes::POPUP === $type) {
            $css .= $this->overlay_css();
        }

        return $css
            . $this->empty_document_state_css()
            . $this->close_base_css();
    }

    /**
     * Keep Elementor's document-level creation prompt limited to an empty
     * popup. The section wrapper and prompt are adjacent siblings, so this
     * responds automatically when content is added, removed, undone, or
     * restored without maintaining duplicate state in JavaScript.
     */
    protected function empty_document_state_css(): string
    {
        return '[data-elementor-type="elemacy_popup"] '
            . '.elementor-section-wrap:not(:empty) + #elementor-add-new-section{display:none;}';
    }

    /**
     * Centered/positioned box (popup + floating). Alignment comes from the
     * Position control via the --elemacy-align / --elemacy-justify variables.
     */
    protected function frame_css(string $wrap): string
    {
        $css  = 'body{margin:0;min-height:100vh;box-sizing:border-box;display:flex;padding:30px;'
            . 'align-items:var(--elemacy-align,center);justify-content:var(--elemacy-justify,center);}';
        $css .= $wrap . '{position:relative;z-index:1;max-width:100%;}';

        return $css;
    }

    protected function topbar_css(string $wrap): string
    {
        $css  = 'body{margin:0;}';
        $css .= $wrap . '{position:relative;z-index:1;}';

        return $css;
    }

    /**
     * Overlay backdrop — always shown for a popup; colour/opacity come from the
     * overlay controls' CSS variables, so they recolour live.
     */
    protected function overlay_css(): string
    {
        return 'body::before{content:"";position:fixed;inset:0;z-index:0;pointer-events:none;'
            . 'display:block;'
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
