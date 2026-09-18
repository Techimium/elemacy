<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Migrations\DatabaseLease;

/**
 * Runs ordered data migrations when the plugin is upgraded. See
 * docs/ARCHITECTURE.md § Caches and schema.
 */
class Migrator
{
    private ?DatabaseLease $lease = null;
    /**
     * Sentinel meaning "no migration has ever run" — the schema-version seed
     * for an existing install that predates OptionKeys::SCHEMA_VERSION, since
     * no migration exists yet for any past release to have already applied.
     */
    const BASELINE_VERSION = '0.0.0';

    /**
     * Bounded expiring mutex so two concurrent requests can
     * never both run a due migration and duplicate its mutation — the
     * second simply skips this sync() call; the marker is unchanged, so its
     * own later call (or the next real request) picks up wherever the first
     * one left off, exactly like ResolverCache's own rebuild lock.
     */
    const LOCK_TTL = 30;

    /**
     * @return array<string, callable> Map of schema version => migration callback.
     */
    protected function migrations(): array
    {
        return [
            // '1' => [$this, 'migration_one'],
        ];
    }

    /**
     * The option this Migrator's own schema marker is stored under.
     * Overridden by a subclass that owns an independent versioned-data
     * domain.
     */
    protected function option_key(): string
    {
        return OptionKeys::SCHEMA_VERSION;
    }

    /**
     * The database lease this Migrator's own concurrency lock is stored under.
     * Derived from option_key() by default so a subclass overriding only
     * option_key() automatically gets its own independent lock too.
     */
    protected function lock_key(): string
    {
        return $this->option_key() . '_lock';
    }

    /**
     * The highest schema version any currently-registered migration
     * introduces — what a fresh install (already shaped like current code,
     * needing no migration at all) seeds its marker to, and what an upgrade
     * targets.
     */
    public function latest_version(): string
    {
        $versions = array_keys($this->migrations());

        if (empty($versions)) {
            return self::BASELINE_VERSION;
        }

        usort($versions, 'version_compare');

        return (string) end($versions);
    }

    /**
     * Run every migration introduced after $from_version up to and including
     * $to_version, in ascending version order, stamping the schema version
     * (option_key() — never DB_VERSION/ELEMACY_VERSION) after each one, so a
     * step that throws leaves the marker at the last successfully completed
     * step rather than skipping ahead or crash-looping the finished ones on
     * the next request.
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
                $result = call_user_func($migration, $from_version, $to_version);

                if ($result === false) {
                    return;
                }

                if ($this->lease) {
                    $this->lease->assert_owned();
                }
                update_option($this->option_key(), $version, false);
            }
        }
    }

    /**
     * Seeds/advances OptionKeys::SCHEMA_VERSION and runs any due migration —
     * the one place that decides "what schema version is this site actually
     * at" so Core\Elemacy's bootstrap does not need to know Migrator's
     * internal seeding rules.
     *
     * @param bool $is_fresh_install True only when the plugin has genuinely
     *                               never run on this site before (no prior
     *                               DB_VERSION at all) — such a site already
     *                               matches current code and is seeded at
     *                               latest_version(), never run through the
     *                               migrations meant for upgrading sites.
     */
    public function sync(bool $is_fresh_install): void
    {
        $schema_version = get_option($this->option_key(), false);

        if ($schema_version === false) {
            // No marker yet: a brand-new site needs no migration; an
            // existing site that predates this marker is seeded at
            // "nothing has run" — correct today because no migration has
            // ever shipped for any past release to have already silently
            // applied. Never inferred from ELEMACY_VERSION.
            $schema_version = $is_fresh_install ? $this->latest_version() : self::BASELINE_VERSION;
            add_option($this->option_key(), $schema_version, '', false);
            $schema_version = get_option($this->option_key(), $schema_version);
        }

        $target = $this->latest_version();

        // A downgrade (schema_version > target, an older codebase with fewer
        // registered migrations) runs nothing and leaves the marker exactly
        // where it is — never lowered to match the older code's own ceiling.
        if (version_compare((string) $schema_version, $target, '>=')) {
            return;
        }

        if (!$this->acquire_lock()) {
            return;
        }

        try {
            $current = $this->current_schema_version();
            $this->run((string) $current, $target);
        } finally {
            $this->release_lock();
        }
    }

    /**
     * @see lock_key()
     */
    /** Read after claiming the lease without another process's stale option-cache entry. */
    protected function current_schema_version(): string
    {
        global $wpdb;
        return (string) ($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->option_key())) ?? self::BASELINE_VERSION);
    }

    protected function acquire_lock(): bool
    {
        $lease = new DatabaseLease($this->lock_key());
        if (!$lease->acquire(self::LOCK_TTL)) {
            return false;
        }
        $this->lease = $lease;
        return true;
    }

    protected function release_lock(): void
    {
        if ($this->lease) {
            $this->lease->release();
            $this->lease = null;
        }
    }
}
