<?php

namespace Elemacy\Modules\Widgets\Support;

defined('ABSPATH') || exit;

use Elemacy\Loop\Support\LoopItemIdentity as CanonicalLoopItemIdentity;

/**
 * Moved to Elemacy\Loop\Support\LoopItemIdentity (design.md D7). This alias
 * is a real class_exists()/instanceof-compatible identity, not a copy. Kept
 * at this path for the first supported Free/Pro compatibility window so any
 * code still importing it from here (in either plugin) is unaffected. New
 * code should import the canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\Support\LoopItemIdentity.
 */
class_alias(CanonicalLoopItemIdentity::class, __NAMESPACE__ . '\LoopItemIdentity');
