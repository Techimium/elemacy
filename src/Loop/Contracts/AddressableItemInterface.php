<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Address\AddressStep;

/**
 * Optional loop item capability: the one address step that identifies this
 * item within its enclosing item. The renderer appends it to the enclosing
 * item's address. An item without it still renders, but a loop nested inside
 * it cannot be rebuilt for a later page.
 */
interface AddressableItemInterface
{
    public function get_address_step(): AddressStep;
}
