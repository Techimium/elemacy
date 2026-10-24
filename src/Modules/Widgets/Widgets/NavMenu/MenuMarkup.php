<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu;

defined('ABSPATH') || exit;

/**
 * Renders a WordPress menu with the Nav Menu widget's markup.
 *
 * The markup comes from WordPress's own `nav_menu_*` filters, so other plugins'
 * filters on those hooks keep working. Ours are registered once and act only on
 * a menu whose arguments carry a RenderContext. A theme menu or a second menu
 * rendered in the same request, even from inside this one, is never touched.
 */
final class MenuMarkup
{
    /** The `wp_nav_menu()` argument that carries the RenderContext. */
    private const ARG = 'elemacy_nav_menu';

    private static bool $registered = false;

    /**
     * @param array<string, mixed> $args `wp_nav_menu()` arguments; output is always returned, never echoed.
     */
    public static function render(array $args, RenderContext $context): string
    {
        self::register();

        $args[self::ARG] = $context;
        $args['echo'] = false;

        // WordPress gives each menu item's ID to the first menu that renders it
        // and drops it from later ones. The widget prints no item IDs, so it
        // must not claim them: a theme menu rendered afterwards keeps its own.
        $claims_ids = remove_filter('nav_menu_item_id', '_nav_menu_item_id_use_once', 10);

        try {
            return (string) wp_nav_menu($args);
        } finally {
            if ($claims_ids) {
                add_filter('nav_menu_item_id', '_nav_menu_item_id_use_once', 10, 2);
            }
        }
    }

    private static function register(): void
    {
        if (self::$registered) {
            return;
        }

        add_filter('nav_menu_link_attributes', [self::class, 'link_attributes'], 10, 4);
        add_filter('nav_menu_submenu_css_class', [self::class, 'submenu_classes'], 10, 2);
        add_filter('nav_menu_item_id', [self::class, 'item_id'], 10, 3);
        add_filter('nav_menu_item_title', [self::class, 'item_title'], 10, 3);

        self::$registered = true;
    }

    /**
     * Level class, current-page and anchor state, and the dropdown's tab order.
     *
     * A link with a hash is never "current": it is marked active by scroll
     * position instead, so one page can have several of them.
     */
    public static function link_attributes($atts, $item, $args, $depth)
    {
        $context = self::context($args);

        if (!$context || !is_array($atts)) {
            return $atts;
        }

        $is_anchor = strpos((string) ($atts['href'] ?? ''), '#') !== false;
        $classes = [Css::LINK, $depth ? Css::SUB_ITEM : Css::ITEM];

        if ($is_anchor) {
            $classes[] = Css::LINK_ANCHOR;
        } elseif (in_array('current-menu-item', (array) ($item->classes ?? []), true)) {
            $classes[] = Css::LINK_ACTIVE;
        }

        $atts['class'] = trim(($atts['class'] ?? '') . ' ' . implode(' ', $classes));

        if ($context->is_collapsed()) {
            $atts['tabindex'] = '-1';
        }

        return $atts;
    }

    public static function submenu_classes($classes, $args)
    {
        if (!self::context($args)) {
            return $classes;
        }

        $classes = (array) $classes;
        $classes[] = Css::SUBMENU;
        $classes[] = Css::DROPDOWN;

        return $classes;
    }

    /** The widget prints the same menu twice (main and dropdown), so item IDs would repeat. */
    public static function item_id($id, $item, $args)
    {
        return self::context($args) ? '' : $id;
    }

    public static function item_title($title, $item, $args)
    {
        $context = self::context($args);

        if (!$context || $context->indicator() === '' || !in_array('menu-item-has-children', (array) ($item->classes ?? []), true)) {
            return $title;
        }

        return $title . '<span class="' . Css::INDICATOR . '">' . $context->indicator() . '</span>';
    }

    private static function context($args): ?RenderContext
    {
        $context = is_object($args) ? ($args->{self::ARG} ?? null) : null;

        return $context instanceof RenderContext ? $context : null;
    }
}
