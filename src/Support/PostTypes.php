<?php

namespace Elemacy\Support;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;

/**
 * Public post types Elemacy treats as user content, with the Elementor / Elemacy
 * internal post types filtered out. Shared by the display-conditions engine and the
 * editor preview pickers, so it lives in Support rather than under either feature.
 */
class PostTypes
{
    protected const EXCLUDED = ['attachment', 'elementor_library', 'e-floating-buttons'];

    /**
     * Public post types as `[{value, label}, ...]`, e.g. for a control's options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function as_sub_values(): array
    {
        // CONDITIONS_EXCLUDED_POST_TYPES_FILTER is the canonical "post types Elemacy
        // ignores" hook; named for conditions, but shared by every consumer here.
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

    /**
     * Public post type slugs (same exclusions as as_sub_values()).
     *
     * @return string[]
     */
    public static function public_names(): array
    {
        return array_map(
            static fn (array $post_type): string => $post_type['value'],
            static::as_sub_values()
        );
    }
}
