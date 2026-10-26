<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Container;
use Elemacy\Core\Elemacy;
use Elemacy\Core\ModuleCatalog;
use Elemacy\Core\SiteInstaller;

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
            Container::instance()->singleton(SiteInstaller::class)->seed_current_site_if_missing();

            $seeded = get_option(OptionKeys::ACTIVE_MODULES, null);
            $catalog = new ModuleCatalog();
            $current = array_map(static fn($module): string => $module->get_name(), $catalog->all());

            self::assertSame([], $seeded, 'Seeding writes the module-preferences option with no recorded choice, so every module follows its declared default.');
            self::assertSame(['theme-builder', 'dynamic-tags', 'widgets', 'custom-css', 'popups'], $current, 'The module slugs are the permanent ones recorded in docs/CONTRACTS.md.');
        } finally {
            if (null === $previous) {
                delete_option(OptionKeys::ACTIVE_MODULES);
            } else {
                update_option(OptionKeys::ACTIVE_MODULES, $previous);
            }
        }
    }

    public function testAToggleStoresOnlyCurrentSlugsAsExplicitChoices(): void
    {
        $previous = get_option(OptionKeys::ACTIVE_MODULES, null);
        $manager = Elemacy::get_instance()->get_module_manager();

        try {
            update_option(OptionKeys::ACTIVE_MODULES, []);
            $manager->disable_module('popups');
            $manager->enable_module('popups');

            self::assertSame(['popups' => true], get_option(OptionKeys::ACTIVE_MODULES), 'A toggle records one explicit current-slug choice and nothing else.');
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
