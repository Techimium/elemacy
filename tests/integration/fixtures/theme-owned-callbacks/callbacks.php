<?php

namespace Elemacy\Tests\Integration\Fixtures;

/**
 * Callbacks defined in a directory that stands in for a theme, so a test can
 * tell them from callbacks defined anywhere else (a plugin's, or the test's).
 */
function theme_owned_function(): void
{
}

final class ThemeOwnedCallbacks
{
    public function method(): void
    {
    }

    public static function static_method(): void
    {
    }

    public function __invoke(): void
    {
    }

    public static function closure(): \Closure
    {
        return static function (): void {
        };
    }
}
