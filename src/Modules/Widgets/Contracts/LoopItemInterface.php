<?php

namespace Elemacy\Modules\Widgets\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\LoopItemInterface as CanonicalLoopItemInterface;

/**
 * Moved to Elemacy\Loop\Contracts\LoopItemInterface (design.md D7). This
 * alias is a real interface_exists()/instanceof-compatible identity, not a
 * copy — kept at this path for the first supported Free/Pro compatibility
 * window so any code still importing it from here (in either plugin) is
 * unaffected. New code should import the canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\Contracts\LoopItemInterface.
 */
class_alias(CanonicalLoopItemInterface::class, __NAMESPACE__ . '\LoopItemInterface');
