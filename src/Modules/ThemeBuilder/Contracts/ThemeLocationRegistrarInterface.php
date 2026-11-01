<?php

namespace Elemacy\Modules\ThemeBuilder\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::THEME_BUILDER_LOCATIONS_REGISTER_ACTION}
 * listeners. Adds a theme location for a template type.
 *
 * @since 1.0.0
 * @api
 */
interface ThemeLocationRegistrarInterface
{
    /**
     * @param string   $type     The location's template type.
     * @param callable $matches  Returns true when the location applies to the
     *                           current request. Evaluated lazily at resolve time.
     */
    public function register(string $type, callable $matches, int $priority = 10): self;
}
