<?php

namespace Elemacy\Core\Constants;

defined('ABSPATH') || exit;

class OptionKeys
{
    const ACTIVE_MODULES = 'elemacy_active_modules';

    /** The plugin release version last seen — diagnostics/hooks only (task 11.5: never consulted to decide which migrations have run; see SCHEMA_VERSION). */
    const DB_VERSION = 'elemacy_db_version';

    /**
     * The data-migration marker Migrator::run() itself owns, independent of
     * DB_VERSION/ELEMACY_VERSION (task 11.5, design.md D11: "Separate schema
     * version from plugin release version"). A plugin release with no new
     * migration must never advance this; it only moves forward when
     * Migrator::run() completes a step.
     */
    const SCHEMA_VERSION = 'elemacy_schema_version';
}
