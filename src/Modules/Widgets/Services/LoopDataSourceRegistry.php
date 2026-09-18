<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\LoopDataSourceRegistry as CanonicalLoopDataSourceRegistry;

/**
 * Moved to Elemacy\Loop\LoopDataSourceRegistry (design.md D7). This alias is
 * a real class_exists()/instanceof-compatible identity, not a copy — old and
 * new entry points read/write the exact same registered sources, since they
 * are the same class under two names. Kept at this path for the first
 * supported Free/Pro compatibility window so any code still importing it
 * from here (in either plugin) is unaffected. New code should import the
 * canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\LoopDataSourceRegistry.
 */
class_alias(CanonicalLoopDataSourceRegistry::class, __NAMESPACE__ . '\LoopDataSourceRegistry');
