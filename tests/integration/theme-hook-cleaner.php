<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Modules\ThemeBuilder\Compatibility\ThemeHookCleaner;
use Elemacy\Tests\Integration\Fixtures\ThemeOwnedCallbacks;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/theme-owned-callbacks/callbacks.php';

/** A plugin-style function: defined here, outside the directory that stands in for the theme. */
function plugin_owned_function(): void
{
}

final class ThemeHookCleanerTest extends IntegrationTestCase
{
    private const HOOK = 'elemacy_test_theme_chrome_hook';

    protected function tearDown(): void
    {
        remove_all_actions(self::HOOK);
        parent::tearDown();
    }

    private function theme_directory(): string
    {
        return __DIR__ . '/fixtures/theme-owned-callbacks';
    }

    public function testRemovesEveryKindOfCallbackTheThemeDefinedAndNothingElse(): void
    {
        $theme = new ThemeOwnedCallbacks();
        $plugin_closure = static function (): void {
        };

        add_action(self::HOOK, 'Elemacy\Tests\Integration\Fixtures\theme_owned_function', 5);
        add_action(self::HOOK, ThemeOwnedCallbacks::closure(), 10);
        add_action(self::HOOK, [$theme, 'method'], 15);
        add_action(self::HOOK, 'Elemacy\Tests\Integration\Fixtures\ThemeOwnedCallbacks::static_method', 20);
        add_action(self::HOOK, $theme, 25);
        add_action(self::HOOK, 'Elemacy\Tests\Integration\plugin_owned_function', 10);
        add_action(self::HOOK, $plugin_closure, 10);
        add_action(self::HOOK, 'strtoupper', 10, 1);

        $removed = ThemeHookCleaner::remove_defined_in(self::HOOK, [$this->theme_directory()]);

        self::assertSame(5, $removed, 'The theme function, closure, method, static method and invokable object are removed.');
        self::assertSame(10, has_action(self::HOOK, 'Elemacy\Tests\Integration\plugin_owned_function'), 'A plugin function on the same priority survives.');
        self::assertSame(10, has_action(self::HOOK, $plugin_closure), 'A plugin closure survives.');
        self::assertSame(10, has_action(self::HOOK, 'strtoupper'), 'A built-in function survives.');
        self::assertFalse(has_action(self::HOOK, [$theme, 'method']));
    }

    public function testLeavesCallbacksAboveTheGivenPriority(): void
    {
        $late = new ThemeOwnedCallbacks();
        add_action(self::HOOK, 'Elemacy\Tests\Integration\Fixtures\theme_owned_function', 20);
        add_action(self::HOOK, [$late, 'method'], 999);

        $removed = ThemeHookCleaner::remove_defined_in(self::HOOK, [$this->theme_directory()], 199);

        self::assertSame(1, $removed);
        self::assertSame(999, has_action(self::HOOK, [$late, 'method']), 'A theme callback above the priority limit is kept.');
        self::assertFalse(has_action(self::HOOK, 'Elemacy\Tests\Integration\Fixtures\theme_owned_function'));
    }

    public function testDoesNothingOnAHookWithNoCallbacks(): void
    {
        self::assertSame(0, ThemeHookCleaner::remove_defined_in('elemacy_test_hook_nobody_uses', [$this->theme_directory()]));
    }

    public function testDoesNotTreatASiblingDirectoryWithTheSamePrefixAsTheTheme(): void
    {
        add_action(self::HOOK, 'Elemacy\Tests\Integration\Fixtures\theme_owned_function');

        // "theme-owned-callbacks" must not match a directory named "theme-owned".
        self::assertSame(0, ThemeHookCleaner::remove_defined_in(self::HOOK, [__DIR__ . '/fixtures/theme-owned']));
    }
}
