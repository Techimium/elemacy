<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Elemacy;
use Elemacy\Core\ModuleCatalog;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * No version has been published, so no site holds a module slug from before
 * the current names and no migration is owed for one. What must hold instead
 * is that a newly seeded site, and every later toggle, only ever stores
 * current slugs.
 */
final class ModuleSeedingTest extends IntegrationTestCase
{
    public function testANewlySeededSiteStoresOnlyCurrentModuleSlugs(): void
    {
        $previous = get_option(OptionKeys::ACTIVE_MODULES, null);
        delete_option(OptionKeys::ACTIVE_MODULES);

        try {
            $seed = new \ReflectionMethod(Elemacy::class, 'maybe_seed_current_site');
            $seed->setAccessible(true);
            $seed->invoke(Elemacy::get_instance());

            $seeded = get_option(OptionKeys::ACTIVE_MODULES, null);
            $catalog = new ModuleCatalog();
            $current = array_map(static fn($module): string => $module->get_name(), $catalog->all());

            self::assertIsArray($seeded, 'Seeding must write the active-modules option.');
            self::assertSame($catalog->default_active_slugs(), $seeded, 'A new site is seeded with exactly the default-active modules.');
            self::assertSame([], array_values(array_diff($seeded, $current)), 'Every seeded slug is a current module slug.');
            self::assertSame(['theme-builder', 'dynamic-tags', 'widgets', 'custom-css', 'popups'], $current, 'The module slugs are the permanent ones recorded in docs/CONTRACTS.md.');
            self::assertNotContains('custom_css', $seeded, 'No pre-release slug is ever seeded.');
        } finally {
            if (null === $previous) {
                delete_option(OptionKeys::ACTIVE_MODULES);
            } else {
                update_option(OptionKeys::ACTIVE_MODULES, $previous);
            }
        }
    }

    public function testAToggleCannotStoreAnUnknownSlug(): void
    {
        $previous = get_option(OptionKeys::ACTIVE_MODULES, null);

        try {
            Elemacy::get_instance()->get_module_manager()->enable_module('custom_css');
            self::fail('Enabling an unknown module slug must be refused.');
        } catch (\Elemacy\Http\Exceptions\ModuleNotFoundException $refused) {
            self::assertSame($previous, get_option(OptionKeys::ACTIVE_MODULES, null), 'A refused toggle writes nothing.');
        }
    }
}
