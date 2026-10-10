<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Container;
use Elemacy\Core\Migrations\DatabaseLease;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\DocumentWriter;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;

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
    public const BASELINE_VERSION = '0.0.0';

    /**
     * Bounded expiring mutex so two concurrent requests can
     * never both run a due migration and duplicate its mutation — the
     * second simply skips this sync() call; the marker is unchanged, so its
     * own later call (or the next real request) picks up wherever the first
     * one left off, exactly like ResolverCache's own rebuild lock.
     */
    public const LOCK_TTL = 30;

    /**
     * Priority on `init` at which a schema sync runs: after every registry
     * that describes stored data is populated (Core registers library types at
     * priority 20, modules at the default 10), so a migration can ask the
     * registry that owns an identifier what it means instead of re-deriving
     * it. See docs/ARCHITECTURE.md § Caches and schema.
     */
    public const SYNC_PRIORITY = 30;

    /** Library items reconciled per request by reconcile_document_types(). */
    public const RECONCILE_BATCH_SIZE = 100;

    /** The cursor reconcile_document_types() resumes a bounded pass from. */
    public const RECONCILE_CURSOR_OPTION = 'elemacy_document_type_migration_cursor';

    /**
     * Queues this Migrator's sync for the point in the request where every
     * registry it may consult is populated, and returns without running
     * anything itself. Never call sync() inline from a plugin bootstrap: both
     * plugins bootstrap on `plugins_loaded`, where the library-type and
     * document registries are still empty.
     */
    public function schedule(bool $is_fresh_install): void
    {
        add_action('init', function () use ($is_fresh_install): void {
            $this->sync($is_fresh_install);
        }, static::SYNC_PRIORITY);
    }

    /**
     * @return array<string, callable> Map of schema version => migration callback.
     */
    protected function migrations(): array
    {
        return [
            '1' => [$this, 'reconcile_document_types'],
        ];
    }

    /**
     * Brings every library item's stored Elementor document type back in line
     * with the document its template type declares in the registry.
     *
     * Deliberately generic: it asks the registry what a type's document is
     * rather than naming any type, so any later change to which document backs
     * a type is repaired by this same step. It writes only where the stored and
     * declared values actually differ, so running it again changes nothing, and
     * it touches no other field — content, conditions and publication state are
     * never read or written here.
     *
     * An item whose template type the registry does not know — an inactive
     * module's, or a removed extension's — is left exactly as it is rather than
     * reconciled against a fallback the registry never declared.
     *
     * @return bool False to resume on a later request (a failed read, or more
     *              items than fit in one bounded pass); true when complete.
     */
    protected function reconcile_document_types(): bool
    {
        global $wpdb;

        $cursor = (int) get_option(static::RECONCILE_CURSOR_OPTION, 0);

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
            LibraryPostType::POST_TYPE,
            $cursor,
            static::RECONCILE_BATCH_SIZE
        ));

        if ($wpdb->last_error) {
            return false;
        }

        $writer = Container::instance()->make(DocumentWriter::class);
        $types  = TypeRegistry::instance();

        foreach ($ids as $id) {
            $id   = (int) $id;
            $type = (string) get_post_meta($id, MetaKeys::TEMPLATE_TYPE, true);

            if ('' !== $type && null !== $types->get($type)) {
                $declared = $writer->document_type($type);

                if ((string) get_post_meta($id, '_elementor_template_type', true) !== $declared) {
                    update_post_meta($id, '_elementor_template_type', $declared);
                }
            }

            if ($this->lease) {
                $this->lease->assert_owned();
            }

            update_option(static::RECONCILE_CURSOR_OPTION, $id, false);
        }

        if (count($ids) === static::RECONCILE_BATCH_SIZE) {
            return false;
        }

        delete_option(static::RECONCILE_CURSOR_OPTION);

        return true;
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
