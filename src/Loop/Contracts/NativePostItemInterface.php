<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use WP_Post;

/**
 * Optional capability: a loop item backed by a real WP_Post implements this so
 * callers that need the native post (the placement registry's "current post"
 * fact, chiefly) can ask for it directly, without checking a concrete item
 * class.
 */
interface NativePostItemInterface
{
    public function get_post(): WP_Post;
}
