<?php

namespace Elemacy\Core\Migrations;

defined('ABSPATH') || exit;

/**
 * A named, resumable progress cursor plus an expiring lock, for one migration
 * step that must process more records than fit in one request without timing
 * out.
 */
class BatchCursor
{
    const LOCK_TTL = 30;

    protected string $name;
    private ?DatabaseLease $lease = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    protected function option_key(): string
    {
        return 'elemacy_migration_cursor_' . $this->name;
    }

    protected function lock_key(): string
    {
        return 'elemacy_migration_cursor_lock_' . $this->name;
    }

    /**
     * Where this batch left off — 0 if it has never run or was already
     * completed (complete() deletes the option).
     */
    public function get_offset(): int
    {
        return (int) get_option($this->option_key(), 0);
    }

    public function advance(int $offset): void
    {
        if ($this->lease) {
            $this->lease->assert_owned();
        }
        update_option($this->option_key(), $offset, false);
    }

    /**
     * Marks this batch fully done and drops its cursor, so a later,
     * unrelated migration reusing the same name (unlikely, but not assumed
     * away) starts clean rather than resuming a stale offset.
     */
    public function complete(): void
    {
        if ($this->lease) {
            $this->lease->assert_owned();
        }
        delete_option($this->option_key());
    }

    /**
     * A bounded, expiring mutex so two concurrent requests processing the
     * same named batch cannot both claim and mutate the same records.
     * Losing the race is always safe: the caller should return `false`
     * (not yet complete) and let a later sync() call retry.
     */
    public function acquire_lock(): bool
    {
        $lease = new DatabaseLease($this->lock_key());
        if (!$lease->acquire(self::LOCK_TTL)) {
            return false;
        }
        $this->lease = $lease;
        return true;
    }

    public function release_lock(): void
    {
        if ($this->lease) {
            $this->lease->release();
            $this->lease = null;
        }
    }
}
