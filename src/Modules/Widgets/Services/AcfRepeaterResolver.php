<?php

namespace Elemacy\Modules\Widgets\Services;

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
}
