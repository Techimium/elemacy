<?php

namespace Elemacy\Modules\Widgets\LoopItems;

defined('ABSPATH') || exit;

use Elemacy\Loop\LoopItems\TermLoopItem as CanonicalTermLoopItem;

/**
 * Moved to Elemacy\Loop\LoopItems\TermLoopItem (design.md D7). This alias is
 * a real class_exists()/instanceof-compatible identity, not a copy. Kept at
 * this path for the first supported Free/Pro compatibility window so any
 * code still importing it from here (in either plugin) is unaffected. New
 * code should import the canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\LoopItems\TermLoopItem.
 */
class_alias(CanonicalTermLoopItem::class, __NAMESPACE__ . '\TermLoopItem');
