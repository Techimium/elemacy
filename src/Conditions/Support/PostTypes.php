<?php

namespace Elemacy\Conditions\Support;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;

class PostTypes
{
    protected const EXCLUDED = ['attachment', 'elemacy_template', 'elementor_library', 'e-floating-buttons'];

    /**
     * Public post types as `[{value, label}, ...]` for use as sub_values on a condition.
     * Excludes Elementor / elemacy internal post types.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function as_sub_values(): array
    {
        $excluded   = apply_filters(Hooks::CONDITIONS_EXCLUDED_POST_TYPES_FILTER, static::EXCLUDED);
        $post_types = get_post_types(['public' => true], 'objects');
        $values     = [];

        foreach ($post_types as $pt) {
            if (in_array($pt->name, $excluded, true)) {
                continue;
            }

            $values[] = [
                'value' => $pt->name,
                'label' => $pt->labels->singular_name ?? $pt->label,
            ];
        }

        return $values;
    }
}
