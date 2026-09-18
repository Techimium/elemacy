<?php

namespace Elemacy\Integrations\Acf;

defined('ABSPATH') || exit;

/**
 * Resolves an ACF repeater/gallery field name to its row array, supporting both
 * top-level usage (get_field) and nested-loop usage (get_sub_field) so the
 * widget renders correctly inside another repeater iteration as well.
 */
class AcfRepeaterResolver
{
    public static function is_acf_active(): bool
    {
        return function_exists('get_field');
    }

    /**
     * @return array<int, mixed> Always an array (empty if nothing found).
     */
    public static function resolve(string $field_name): array
    {
        if ('' === $field_name || !self::is_acf_active()) {
            return [];
        }

        $value = get_sub_field($field_name);

        if (empty($value)) {
            $value = get_field($field_name);
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Confirms a saved field name is a currently-registered repeater field at
     * the given location, rather than an arbitrary meta key that happens to
     * share its name — the field definition can be renamed or removed after
     * a widget/loop source was authored against it.
     *
     * @param string     $field_name Saved authoring value; never request-supplied.
     * @param int|string $post_id Resolved post ID or ACF options-page identifier.
     */
    public static function is_allowed_repeater_field(string $field_name, $post_id): bool
    {
        if ('' === $field_name || !self::is_acf_active() || !function_exists('get_field_object')) {
            return false;
        }

        $field = get_field_object($field_name, $post_id, false, false);

        return is_array($field) && 'repeater' === ($field['type'] ?? null);
    }
}
