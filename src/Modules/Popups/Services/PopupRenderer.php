<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\Support\PopupTypes;

/** Renders a popup's root container into the page footer. */
class PopupRenderer
{
    protected TemplateRenderer $renderer;

    public function __construct(?TemplateRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new TemplateRenderer();
    }

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

        $renderer = $this->renderer;
        $placement = (string) $id;

        // PopupManager::register_template_assets() already prepared this
        // exact document/context/placement early, on wp_enqueue_scripts,
        // specifically so widget dependencies discovered during that one
        // render are queued before this deadline (wp_body_open for top bars,
        // wp_footer for everything else). Rendering again here would not
        // only be redundant but could miss those already-queued handles
        // being the ones actually delivered. A direct prepare() fallback
        // only runs if that invariant was somehow never satisfied, so a
        // popup is never left with no content instead of unprepared content.
        $content = $renderer->get_prepared($id, PopupManager::ASSET_CONTEXT, $placement)
            ?? $renderer->prepare($id, PopupManager::ASSET_CONTEXT, $placement);

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
