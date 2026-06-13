<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

/**
 * Runs ordered data migrations when the plugin is upgraded.
 *
 * Each migration is keyed by the plugin version that introduces it and runs
 * once, when upgrading from any earlier version. Register new migrations in
 * ascending version order in migrations(); they must be idempotent, since a
 * failed request can cause the same upgrade step to run again.
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
     * $to_version.
     */
    public function run(string $from_version, string $to_version): void
    {
        foreach ($this->migrations() as $version => $migration) {
            $introduced_after_installed = version_compare($from_version, (string) $version, '<');
            $within_target_version      = version_compare((string) $version, $to_version, '<=');

            if ($introduced_after_installed && $within_target_version) {
                call_user_func($migration);
            }
        }
    }
}
