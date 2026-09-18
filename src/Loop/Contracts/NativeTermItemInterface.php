<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use WP_Term;

/**
 * Optional capability (design.md D7): a loop item backed by a real WP_Term
 * implements this so callers that specifically need native term identity
 * (Dynamic Tags' own Taxonomy resolver, chiefly) can ask for it directly —
 * a narrower, more precise seam than routing everything through the generic
 * LoopItemInterface::get_field() key contract, and one that never requires
 * checking a concrete item class. The base LoopItemInterface itself is not
 * widened with this method: most item types have no native term at all.
 */
interface NativeTermItemInterface
{
    public function get_term(): WP_Term;
}
