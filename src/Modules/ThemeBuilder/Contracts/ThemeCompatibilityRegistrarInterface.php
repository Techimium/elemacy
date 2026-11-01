<?php

namespace Elemacy\Modules\ThemeBuilder\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION}
 * listeners. Adds a compatibility adapter for an exact theme slug; a later registration for the same slug replaces the earlier one.
 *
 * @since 1.0.0
 * @api
 */
interface ThemeCompatibilityRegistrarInterface
{
    /**
     * @param string $theme_slug    Stylesheet or template directory name.
     * @param string $adapter_class A ThemeCompatibilityInterface class name.
     */
    public function register(string $theme_slug, string $adapter_class): void;
}
