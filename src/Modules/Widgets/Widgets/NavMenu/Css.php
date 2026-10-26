<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu;

defined('ABSPATH') || exit;

/**
 * Class names the Nav Menu markup, its control selectors, its stylesheet and its
 * script agree on. All of them live under `elemacy-nav-menu` so neither Elementor
 * Pro's Nav Menu nor a theme's global menu styles can match them.
 */
final class Css
{
    /** Prefix of the classes Elementor puts on the widget wrapper through `prefix_class`. */
    public const WRAPPER = 'elemacy-nav-menu--';

    /** `<nav>` holding the desktop menu. */
    public const MAIN = 'elemacy-nav-menu__main';

    /** Shared by the desktop `<nav>` and the mobile dropdown `<nav>`. */
    public const CONTAINER = 'elemacy-nav-menu__container';

    /** Root `<ul>` of each rendered menu tree. */
    public const LIST = 'elemacy-nav-menu__list';

    /** Nested `<ul>` of a parent item. */
    public const SUBMENU = 'elemacy-nav-menu__submenu';

    /** Desktop submenus and the mobile dropdown: everything the Dropdown style section styles. */
    public const DROPDOWN = 'elemacy-nav-menu__dropdown';

    /** Every menu link. */
    public const LINK = 'elemacy-nav-menu__link';

    /** Top-level link. */
    public const ITEM = 'elemacy-nav-menu__item';

    /** Link inside a submenu. */
    public const SUB_ITEM = 'elemacy-nav-menu__sub-item';

    /** Link to the current page. */
    public const LINK_ACTIVE = 'elemacy-nav-menu__link--active';

    /** Link whose URL has a hash; marked active while its section is in view. */
    public const LINK_ANCHOR = 'elemacy-nav-menu__link--anchor';

    /** Submenu indicator appended to parent links. */
    public const INDICATOR = 'elemacy-nav-menu__indicator';

    /** A chevron indicator rendered as inline SVG, which is drawn smaller. */
    public const INDICATOR_CHEVRON = 'elemacy-nav-menu__indicator-chevron';

    public const TOGGLE = 'elemacy-nav-menu__toggle';
    public const TOGGLE_ICON_OPEN = 'elemacy-nav-menu__toggle-icon--open';
    public const TOGGLE_ICON_CLOSE = 'elemacy-nav-menu__toggle-icon--close';

    /** Modifier of MAIN, e.g. `elemacy-nav-menu__main--horizontal`, `--pointer-underline`, `--animation-fade`. */
    public static function main(string $modifier): string
    {
        return self::MAIN . '--' . $modifier;
    }
}
