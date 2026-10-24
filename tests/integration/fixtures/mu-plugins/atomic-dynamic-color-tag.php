<?php

/**
 * A color dynamic tag Elementor's atomic widgets accept in a style prop, so a
 * loop item template can carry genuinely per-item atomic CSS: the tag prints
 * the current post's `elemacy_atomic_color` meta. A data tag with no controls
 * of its own: Elementor converts a tag for atomic use only when every control
 * carries a label, and the plain Tag base adds unlabelled before/after/
 * fallback controls. Used by atomic-rendering.php.
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

add_action('elementor/dynamic_tags/register', static function ($manager): void {
    if (!class_exists('Elemacy_Fixture_Item_Color_Tag')) {
        class Elemacy_Fixture_Item_Color_Tag extends \Elementor\Core\DynamicTags\Data_Tag
        {
            public function get_name()
            {
                return 'elemacy-fixture-item-color';
            }

            public function get_title()
            {
                return 'Fixture Item Color';
            }

            public function get_group()
            {
                return 'site';
            }

            public function get_categories()
            {
                return [\Elementor\Modules\DynamicTags\Module::COLOR_CATEGORY];
            }

            public function get_value(array $options = [])
            {
                return (string) get_post_meta(get_the_ID(), 'elemacy_atomic_color', true);
            }
        }
    }

    $manager->register(new Elemacy_Fixture_Item_Color_Tag());
});
