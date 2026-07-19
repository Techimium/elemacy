<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\OptionKeys;

/**
 * Runs ordered data migrations when the plugin is upgraded.
 *
 * Each migration is keyed by the plugin version that introduces it and runs
 * once, when upgrading from any earlier version. run() sorts them by version
 * before executing, so listing order in migrations() does not matter; they
 * must be idempotent. Each callback is invoked with ($from_version,
 * $to_version).
 *
 * The stored DB version is advanced to each migration's version as soon as that
 * migration completes, so if a later one fails the finished steps are not run
 * again on the next request.
 */
class Migrator
{
    /**
     * @return array<string, callable> Map of version => migration callback.
     */
    protected function migrations(): array
    {
        return [
            // '1.1.0' => [$this, 'to_1_1_0'],
        ];
    }

    /**
     * Run every migration introduced after $from_version up to and including
     * $to_version, in ascending version order, stamping the DB version after
     * each one.
     *
     * Each migration callback receives ($from_version, $to_version) so it can
     * make context-aware decisions if needed.
     */
    public function run(string $from_version, string $to_version): void
    {
        $migrations = $this->migrations();

        uksort($migrations, static function ($a, $b): int {
            return version_compare((string) $a, (string) $b);
        });

        foreach ($migrations as $version => $migration) {
            $version = (string) $version;

            $introduced_after_installed = version_compare($from_version, $version, '<');
            $within_target_version      = version_compare($version, $to_version, '<=');

            if ($introduced_after_installed && $within_target_version) {
                call_user_func($migration, $from_version, $to_version);
                update_option(OptionKeys::DB_VERSION, $version);
            }
        }
    }
}
