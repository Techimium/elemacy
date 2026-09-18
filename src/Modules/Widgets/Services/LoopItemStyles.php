<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\LoopItemStyles as CanonicalLoopItemStyles;

/**
 * Moved to Elemacy\Loop\LoopItemStyles (design.md D7). This alias is a real
 * class_exists()/instanceof-compatible identity, not a copy — old and new
 * entry points share the exact same request-level "already printed" caches,
 * since they are the same class under two names. Kept at this path for the
 * first supported Free/Pro compatibility window so any code still importing
 * it from here (in either plugin) is unaffected. New code should import the
 * canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\LoopItemStyles.
 */
class_alias(CanonicalLoopItemStyles::class, __NAMESPACE__ . '\LoopItemStyles');
