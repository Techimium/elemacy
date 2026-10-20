<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Modules\Popups\DTO\PopupDTO;
use Elemacy\Modules\Popups\Resources\PopupConfigResource;
use Elemacy\Modules\Popups\Support\DocumentDisplay;
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
     * @param PopupDTO $popup    The popup to render.
     * @param bool     $relocate Mark the root for the engine to move to the top of <body> (a top bar printed late because the theme never called wp_body_open).
     * @return void
     */
    public function render(PopupDTO $popup, bool $relocate = false): void
    {
        echo $this->build_markup($popup, $relocate); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build_markup escapes attributes and Elementor returns safe builder HTML.
    }

    /**
     * The popup document's prepared fragment.
     *
     * PopupManager::register_template_assets() already prepared this exact
     * document/context/placement early, on wp_enqueue_scripts, specifically so
     * widget dependencies discovered during that one render are queued before
     * this request's output deadlines (wp_body_open for top bars, wp_footer for
     * everything else). Rendering again would not only be redundant but could
     * miss those already-queued handles being the ones actually delivered. A
     * direct prepare() fallback only runs if that invariant was somehow never
     * satisfied, so a popup is never left with no content instead of unprepared
     * content.
     *
     * @param PopupDTO $popup
     * @return string
     */
    public function prepared_content(PopupDTO $popup): string
    {
        $id = (int) $popup->id;
        $placement = (string) $id;

        return $this->renderer->get_prepared($id, PopupManager::ASSET_CONTEXT, $placement)
            ?? $this->renderer->prepare($id, PopupManager::ASSET_CONTEXT, $placement);
    }

    /**
     * Builds the popup's root container markup: hidden and templated for the
     * frontend engine to hydrate on first open, or — for a top bar that opens
     * as soon as the page loads — already visible so the page below it does not
     * move when the engine starts.
     *
     * @param PopupDTO $popup    The popup to render.
     * @param bool     $relocate See render().
     * @return string
     */
    public function build_markup(PopupDTO $popup, bool $relocate = false): string
    {
        $id   = (int) $popup->id;
        $type = (string) $popup->type;

        $content = $this->prepared_content($popup);
        $box = $this->is_prerenderable($popup) ? $this->box_markup($content, $this->close_button_enabled($popup)) : null;

        $classes = sprintf(
            'elemacy-popup elemacy-popup-%1$d elemacy-popup--%2$s',
            $id,
            esc_attr($type)
        );

        $display = DocumentDisplay::for_popup($id);

        if (null !== $box) {
            $classes .= ' is-open elemacy-popup--pos-' . esc_attr((string) ($display['position'] ?? 'top'));
            if (!empty($display['sticky'])) {
                $classes .= ' is-sticky';
            }
        }

        $html  = '<div class="' . esc_attr($classes) . '"';
        $html .= ' data-elemacy-popup-id="' . esc_attr((string) $id) . '"';
        $html .= ' data-elemacy-popup-type="' . esc_attr($type) . '"';

        if ($relocate) {
            $html .= ' data-elemacy-relocate="top"';
        }

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

        if (null !== $box) {
            if (!empty($display['z_index'])) {
                $html .= ' style="z-index:' . (int) $display['z_index'] . '"';
            }
            $html .= ' data-elemacy-prerendered aria-hidden="false">' . $box . '</div>';

            return $html;
        }

        $html .= ' aria-hidden="true" hidden>';
        $html .= '<template class="elemacy-popup-tpl">' . $content . '</template>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Whether a top bar is shown as soon as the page loads with nothing for the
     * browser to decide first: a top-positioned bar whose only trigger is a
     * page-load trigger without a delay, and no client-side rules. Such a bar
     * is printed visible; every other popup is opened by the engine.
     *
     * @param PopupDTO $popup
     * @return bool
     */
    public function is_prerenderable(PopupDTO $popup): bool
    {
        if (PopupTypes::TOPBAR !== (string) $popup->type) {
            return false;
        }

        if ('bottom' === (DocumentDisplay::for_popup((int) $popup->id)['position'] ?? 'top')) {
            return false;
        }

        $config = PopupConfigResource::make($popup);

        if (!is_array($config) || !empty($config['rules'])) {
            return false;
        }

        $triggers = is_array($config['triggers'] ?? null) ? $config['triggers'] : [];

        // A bar with no triggers opens only from links and the JavaScript API.
        return 1 === count($triggers)
            && 'page_load' === ($triggers[0]['type'] ?? '')
            && 0.0 === (float) ($triggers[0]['delay'] ?? 0);
    }

    /**
     * The popup box — the Elementor content wrapper tagged `elemacy-popup__box`,
     * its children moved into an `elemacy-popup__scroll` element and followed by
     * the close button — with any sibling markup left around it. This mirrors
     * what engine.js hydrate() builds in the browser, so a bar printed visible
     * is indistinguishable from one the engine opened. Keep the two in step;
     * the static-browser suite compares them.
     *
     * @param string $content      The prepared document fragment.
     * @param bool   $close_button Whether to print the close button.
     * @return string|null Null when the fragment has no single well-formed `.elementor` root, in which case the engine opens the popup instead.
     */
    public function box_markup(string $content, bool $close_button): ?string
    {
        $box = $this->find_content_root($content);

        if (null === $box) {
            return null;
        }

        [$open_start, $open_end, $close_start] = $box;

        $open_tag = substr($content, $open_start, $open_end - $open_start);
        $open_tag = preg_replace('/(\sclass\s*=\s*)(["\'])/i', '$1$2elemacy-popup__box ', $open_tag, 1, $count);

        if (!is_string($open_tag) || 1 !== $count) {
            return null;
        }

        $close = '';
        if ($close_button) {
            $label = __('Close', 'elemacy');
            $close = '<button type="button" class="elemacy-popup__close elemacy-popup-close" aria-label="' . esc_attr($label) . '">&times;</button>';
        }

        return substr($content, 0, $open_start)
            . $open_tag
            . '<div class="elemacy-popup__scroll">'
            . substr($content, $open_end, $close_start - $open_end)
            . '</div>'
            . $close
            . substr($content, $close_start);
    }

    /**
     * Locates the first element carrying the class `elementor` — the one engine.js
     * turns into the popup box — and its matching closing tag.
     *
     * @param string $html
     * @return array{0: int, 1: int, 2: int}|null Offsets of the opening tag's start and end and of the closing tag's start, or null when not found or unbalanced.
     */
    protected function find_content_root(string $html): ?array
    {
        $token = '#<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>|<(/?)([a-zA-Z][a-zA-Z0-9-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>#is';

        if (!preg_match_all($token, $html, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $root = null;
        $depth = 0;

        foreach ($tags as $tag) {
            if (!isset($tag[3]) || '' === $tag[3][0]) {
                continue; // A comment or a raw-text element, which hold no structure.
            }

            $closing = '/' === $tag[2][0];
            $name = strtolower($tag[3][0]);

            if (null === $root) {
                if ($closing || !preg_match('/\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag[4][0], $class)) {
                    continue;
                }

                $classes = preg_split('/\s+/', trim($class[1] !== '' ? $class[1] : ($class[2] ?? '')));

                if (is_array($classes) && in_array('elementor', $classes, true)) {
                    $root = [
                        'name' => $name,
                        'start' => $tag[0][1],
                        'end' => $tag[0][1] + strlen($tag[0][0]),
                    ];
                    $depth = 1;
                }

                continue;
            }

            if ($name !== $root['name']) {
                continue;
            }

            if ($closing) {
                --$depth;

                if (0 === $depth) {
                    return [$root['start'], $root['end'], $tag[0][1]];
                }
            } elseif ('/' !== substr(rtrim($tag[4][0]), -1)) {
                ++$depth;
            }
        }

        return null;
    }

    private function close_button_enabled(PopupDTO $popup): bool
    {
        $display = DocumentDisplay::for_popup((int) $popup->id);

        return !empty($display['close']['button']);
    }
}
