<?php

namespace Elemacy\Modules\ThemeBuilder\Support;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use WP_Taxonomy;

/**
 * Public taxonomies used to fan out the per-taxonomy conditions — "In {Tax}"
 * (singular) and "{Tax} Archive". Both free (mock) and pro (real) registration
 * enumerate through this helper so the generated condition names line up and
 * pro's implementations override free's mocks by name. Mirrors {@see PostTypes}.
 */
class Taxonomies
{
    protected const EXCLUDED = [
        'nav_menu',
        'link_category',
        'post_format',
        'elementor_library_type',
        'elementor_library_category',
        'wp_theme',
        'wp_template_part_area',
    ];

    /**
     * Public taxonomy objects, minus internal / Elementor ones.
     *
     * @return WP_Taxonomy[]
     */
    public static function public_objects(): array
    {
        $excluded   = apply_filters(Hooks::CONDITIONS_EXCLUDED_TAXONOMIES_FILTER, static::EXCLUDED);
        $taxonomies = get_taxonomies(['public' => true], 'objects');

        return array_values(array_filter(
            $taxonomies,
            static fn(WP_Taxonomy $taxonomy): bool => !in_array($taxonomy->name, $excluded, true)
        ));
    }
}
