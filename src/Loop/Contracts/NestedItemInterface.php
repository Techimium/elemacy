<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * Optional loop item capability: the item it was found under, when it only
 * exists inside another item (an ACF sub-repeater row inside its parent row).
 * Lets an editor preview, which has no real outer loop, rebuild the enclosing
 * frames so ancestor reads and nested loops behave as they will on the page.
 */
interface NestedItemInterface
{
    public function get_parent_item(): ?LoopItemInterface;
}
