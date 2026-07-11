<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elementor\Plugin as ElementorPlugin;

/**
 * Renders a popup's root container into the page footer.
 *
 * The built Elementor content is wrapped in a <template> so it is not painted
 * until the engine clones it on first open (lazy hydration).
 */
class PopupRenderer
{
    /**
     * Echoes the popup's markup.
     *
     * @param PopupDTO $popup The popup to render.
     * @return void
     */
    public function render(PopupDTO $popup): void
    {
        echo $this->build_markup($popup); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build_markup escapes attributes and Elementor returns safe builder HTML.
    }

    /**
     * Builds the popup's root container markup, hidden and templated for the
     * frontend engine to hydrate on first open.
     *
     * @param PopupDTO $popup The popup to render.
     * @return string
     */
    public function build_markup(PopupDTO $popup): string
    {
        $id   = (int) $popup->id;
        $type = (string) $popup->type;

        $content = '';

        if (class_exists('\Elementor\Plugin')) {
            $content = ElementorPlugin::instance()->frontend->get_builder_content_for_display($id);
        }

        $classes = sprintf(
            'elemacy-popup elemacy-popup-%1$d elemacy-popup--%2$s',
            $id,
            esc_attr($type)
        );

        $html  = '<div class="' . esc_attr($classes) . '"';
        $html .= ' data-elemacy-popup-id="' . esc_attr((string) $id) . '"';
        $html .= ' data-elemacy-popup-type="' . esc_attr($type) . '"';

        // Only the centered popup is a modal dialog. Topbars/banners/floating are
        // persistent, non-modal UI, so they must not claim role="dialog"/aria-modal.
        if (PopupTypes::POPUP === $type) {
            $html .= ' role="dialog" aria-modal="true"';

            // A dialog needs an accessible name; fall back to the popup's title.
            $label = get_the_title($id);
            if ('' !== $label) {
                $html .= ' aria-label="' . esc_attr($label) . '"';
            }
        }

        $html .= ' aria-hidden="true" hidden>';
        $html .= '<template class="elemacy-popup-tpl">' . $content . '</template>';
        $html .= '</div>';

        return $html;
    }
}
