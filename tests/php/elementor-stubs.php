<?php

/**
 * Minimal Elementor stand-ins for testing Core\Rendering's integration
 * points, in the same spirit as bootstrap.php's WordPress stubs: only what's
 * called, faithful to the real class's shape for the inputs used in tests.
 *
 * Not required by bootstrap.php on purpose — tests that need `\Elementor\Plugin`
 * to be genuinely absent (the "Elementor not loaded" branch) must run before
 * this file is included.
 */

namespace Elementor;

class Plugin
{
    /** @var self|null */
    public static $instance;

    /** @var object|null */
    public $frontend;

    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }
}

namespace Elementor\Modules\AtomicWidgets\DynamicTags;

/**
 * Faithful to the real class's one static method actually called by
 * AtomicStylesRenderer::contains_dynamic_value() — enough to unit-test the
 * dynamic/static style-filtering logic without the rest of the real
 * atomic-widgets dynamic-tags machinery.
 */
class Dynamic_Prop_Type
{
    public static function is_dynamic_prop_value($value): bool
    {
        return isset($value['$$type']) && 'dynamic' === $value['$$type'];
    }
}
