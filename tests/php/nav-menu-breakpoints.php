<?php

/**
 * The Nav Menu's breakpoint rules (openspec widgets/nav-menu, "Responsive mobile
 * dropdown"): which Elementor breakpoints the widget can collapse at, and the
 * media rules generated from their values. Real Elementor breakpoints and the
 * rendered widget are covered in tests/integration/nav-menu.php.
 */

use Elemacy\Modules\Widgets\Widgets\NavMenu\BreakpointStyles;

/** A stand-in for Elementor's Core\Breakpoints\Breakpoint. */
final class NavMenuTestBreakpoint
{
    private string $label;
    private int $value;

    public function __construct(string $label, int $value)
    {
        $this->label = $label;
        $this->value = $value;
    }

    public function get_label(): string
    {
        return $this->label;
    }

    public function get_value(): int
    {
        return $this->value;
    }
}

/** Runs $assertion with the given active breakpoints (key => [label, value]), or with no breakpoints API at all. */
function with_nav_menu_breakpoints(?array $active, callable $assertion): bool
{
    $plugin = \Elementor\Plugin::instance();
    $had = property_exists($plugin, 'breakpoints');
    $previous = $had ? $plugin->breakpoints : null;

    $plugin->breakpoints = $active === null ? null : new class ($active) {
        private array $active;

        public function __construct(array $active)
        {
            $this->active = array_map(static fn (array $breakpoint) => new NavMenuTestBreakpoint(...$breakpoint), $active);
        }

        public function get_active_breakpoints(): array
        {
            return $this->active;
        }
    };

    try {
        return (bool) $assertion();
    } finally {
        if ($had) {
            $plugin->breakpoints = $previous;
        } else {
            unset($plugin->breakpoints);
        }
    }
}

check('Nav Menu offers only the mobile and tablet breakpoints, in order, never laptop or widescreen', static function () {
    return with_nav_menu_breakpoints([
        'widescreen'   => ['Widescreen', 2400],
        'laptop'       => ['Laptop', 1366],
        'tablet_extra' => ['Tablet Landscape', 1200],
        'tablet'       => ['Tablet Portrait', 1024],
        'mobile_extra' => ['Mobile Landscape', 880],
        'mobile'       => ['Mobile Portrait', 767],
    ], static function () {
        return ['mobile', 'mobile_extra', 'tablet', 'tablet_extra'] === array_keys(BreakpointStyles::breakpoints());
    });
});

check('Nav Menu hides the main menu at or below a breakpoint and the toggle and dropdown above it', static function () {
    return with_nav_menu_breakpoints(['tablet' => ['Tablet Portrait', 1024]], static function () {
        $css = BreakpointStyles::css();

        return false !== strpos($css, '@media (max-width:1024px){.elemacy-nav-menu--breakpoint-tablet .elemacy-nav-menu__main{display:none}}')
            && false !== strpos($css, '@media (min-width:1025px){.elemacy-nav-menu--breakpoint-tablet .elemacy-nav-menu__toggle,'
                . '.elemacy-nav-menu--breakpoint-tablet .elemacy-nav-menu__container.elemacy-nav-menu__dropdown{display:none}');
    });
});

check('Nav Menu breakpoint rules follow the site\'s custom breakpoint values', static function () {
    return with_nav_menu_breakpoints(['tablet' => ['Tablet Portrait', 900], 'mobile' => ['Mobile Portrait', 600]], static function () {
        $css = BreakpointStyles::css();

        return false !== strpos($css, '@media (max-width:900px){.elemacy-nav-menu--breakpoint-tablet ')
            && false !== strpos($css, '@media (min-width:901px){.elemacy-nav-menu--breakpoint-tablet ')
            && false !== strpos($css, '@media (max-width:600px){.elemacy-nav-menu--breakpoint-mobile ')
            && false === strpos($css, '1024')
            && false === strpos($css, '767');
    });
});

check('Nav Menu emits no breakpoint rule for an inactive breakpoint', static function () {
    return with_nav_menu_breakpoints(['tablet' => ['Tablet Portrait', 1024]], static function () {
        return false === strpos(BreakpointStyles::css(), 'breakpoint-mobile');
    });
});

check('Nav Menu falls back to Elementor\'s default mobile and tablet values when the breakpoints API is unavailable', static function () {
    return with_nav_menu_breakpoints(null, static function () {
        $breakpoints = BreakpointStyles::breakpoints();
        $css = BreakpointStyles::css();

        return 767 === $breakpoints['mobile']['value']
            && 1024 === $breakpoints['tablet']['value']
            && false !== strpos($css, '@media (max-width:767px)')
            && false !== strpos($css, '@media (min-width:1025px)');
    });
});
