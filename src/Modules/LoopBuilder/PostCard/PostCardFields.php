<?php

namespace Elemacy\Modules\LoopBuilder\PostCard;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfFieldReader;
use WP_Post;

/**
 * Reads a custom field for display on a card: post meta or an ACF field, as
 * plain text, and nothing a visitor should not see.
 *
 * @internal
 */
final class PostCardFields
{
    public static function value(string $key, WP_Post $post): string
    {
        if ('' === $key || is_protected_meta($key, 'post') || post_password_required($post)) {
            return '';
        }

        return self::to_text((new AcfFieldReader())->get_field_value($key, $post->ID));
    }

    /** @param mixed $value */
    private static function to_text($value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return implode(', ', array_filter($value, 'is_scalar'));
        }

        return '';
    }
}
