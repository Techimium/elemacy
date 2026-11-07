<?php

namespace Elemacy\Modules\ThemeBuilder\Contracts;

defined('ABSPATH') || exit;

/**
 * A theme adapter, registered through
 * {@see \Elemacy\Core\Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION}.
 *
 * @api
 */
interface ThemeCompatibilityInterface
{
    /** Register theme-specific header/footer overrides. */
    public function register(ThemeChromeInterface $manager): void;
}
