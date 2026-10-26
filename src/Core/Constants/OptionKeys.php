<?php

namespace Elemacy\Core\Constants;

defined('ABSPATH') || exit;

class OptionKeys
{
    public const ACTIVE_MODULES = 'elemacy_active_modules';

    /** The plugin release version last seen — diagnostics/hooks only. */
    public const DB_VERSION = 'elemacy_db_version';

    /**
     * The data-migration marker Migrator::run() itself owns, independent of
     * DB_VERSION/ELEMACY_VERSION. A plugin release with no new
     * migration must never advance this; it only moves forward when
     * Migrator::run() completes a step.
     */
    public const SCHEMA_VERSION = 'elemacy_schema_version';
}
