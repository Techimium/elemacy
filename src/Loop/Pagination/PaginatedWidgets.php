<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

use Elemacy\Loop\Rendering\ItemPresenterRegistry;

/**
 * The paginated loop widgets a document saves: those whose widget type has a
 * registered item presenter and whose Pagination setting is not None. Read
 * from the saved tree, so it needs no Elementor and no render.
 *
 * @internal
 */
final class PaginatedWidgets
{
    /** @var array<int, array{0: string, 1: array}> The last read per document: its saved JSON and the widgets found in it. */
    private static array $read = [];

    /**
     * @param int $owner_id The document that saves the widgets.
     * @return array<int, array{id: string, type: string, settings: array}>
     */
    public static function in_document(int $owner_id): array
    {
        $raw = get_post_meta($owner_id, '_elementor_data', true);

        if (!is_string($raw) || '' === $raw) {
            return [];
        }

        if (isset(self::$read[$owner_id]) && self::$read[$owner_id][0] === $raw) {
            return self::$read[$owner_id][1];
        }

        $tree = json_decode($raw, true);
        $found = [];
        $registry = ItemPresenterRegistry::instance();
        $stack = [is_array($tree) ? $tree : []];

        while ($stack) {
            foreach (array_pop($stack) as $element) {
                if (!is_array($element)) {
                    continue;
                }

                if (
                    'widget' === ($element['elType'] ?? null) && is_string($element['widgetType'] ?? null)
                    && is_string($element['id'] ?? null) && is_array($element['settings'] ?? null)
                    && !empty($element['settings']['pagination_type'])
                    && null !== $registry->for_widget($element['widgetType'])
                ) {
                    $found[] = [
                        'id' => $element['id'],
                        'type' => $element['widgetType'],
                        'settings' => $element['settings'],
                    ];
                }

                if (isset($element['elements']) && is_array($element['elements'])) {
                    $stack[] = $element['elements'];
                }
            }
        }

        self::$read[$owner_id] = [$raw, $found];

        return $found;
    }

    /**
     * Whether a widget pages through its own URL argument (PageArgument)
     * instead of the site's paged URLs: the visitor is on a singular page and
     * the widget's own document holds two or more paginated loop widgets,
     * which would otherwise all move with the one page of the URL. The same
     * answer for the widget's classic render, the page links the endpoint
     * returns and the 404 guard.
     *
     * @param int   $owner_id The document that saves the widget.
     * @param array $settings The widget's settings.
     * @return bool
     */
    public static function uses_own_argument(int $owner_id, array $settings): bool
    {
        return !empty($settings['pagination_type']) && is_singular() && count(self::in_document($owner_id)) >= 2;
    }
}
