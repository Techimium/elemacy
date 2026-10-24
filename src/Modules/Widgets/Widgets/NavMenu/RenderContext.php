<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu;

defined('ABSPATH') || exit;

/**
 * What one `wp_nav_menu()` call of a Nav Menu widget needs from MenuMarkup's
 * filters. Its presence in the call's arguments is also what marks the menu as
 * the widget's own, so the filters leave every other menu alone.
 */
final class RenderContext
{
    private bool $collapsed;
    private string $indicator;

    /**
     * @param bool   $collapsed True for a dropdown tree that starts closed behind
     *                          the toggle: its links leave the tab order until it opens.
     * @param string $indicator Submenu indicator markup; empty for none.
     */
    public function __construct(bool $collapsed, string $indicator)
    {
        $this->collapsed = $collapsed;
        $this->indicator = $indicator;
    }

    public function is_collapsed(): bool
    {
        return $this->collapsed;
    }

    public function indicator(): string
    {
        return $this->indicator;
    }
}
