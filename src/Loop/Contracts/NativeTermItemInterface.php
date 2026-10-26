<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use WP_Term;

/**
 * Optional capability: a loop item backed by a real WP_Term implements this so
 * callers that specifically need native term identity (Dynamic Tags' own
 * Taxonomy resolver, chiefly) can ask for it directly — a narrower, more
 * precise seam than routing everything through the generic
 * LoopItemInterface::get_field() key contract, and one that never requires
 * checking a concrete item class.
 */
interface NativeTermItemInterface
{
    public function get_term(): WP_Term;
}
