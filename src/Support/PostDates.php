<?php

namespace Elemacy\Support;

defined('ABSPATH') || exit;

use DateTimeZone;
use WP_Post;

/**
 * Post date helpers shared by the library item services (templates, popups,
 * blocks), so every API resource reports dates in one canonical shape.
 */
class PostDates
{
    /** The post's publish date as a UTC 'Y-m-d H:i:s' string. */
    public static function gmt_datetime(WP_Post $post): ?string
    {
        $datetime = get_post_datetime($post, 'date', 'gmt');

        if (!$datetime) {
            $datetime = get_post_datetime($post, 'date', 'local');
        }

        if (!$datetime) {
            return null;
        }

        return $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
