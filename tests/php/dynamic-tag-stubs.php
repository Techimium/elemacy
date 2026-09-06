<?php

/**
 * Minimal stand-in for Elementor's dynamic-tag base classes. Unlike
 * elementor-stubs.php, this is not part of the "is Elementor active"
 * detection surface PostContent checks at runtime (via class_exists) - it's
 * a hard compile-time dependency (PostContent extends Tag) needed simply for
 * that class to be definable at all, including in tests that simulate
 * Elementor's Plugin class being absent. Required unconditionally, before
 * any test instantiates PostContent.
 */

namespace Elementor\Core\DynamicTags;

class Tag
{
    /** @var array */
    protected $settings = [];

    public function __construct($data = [])
    {
        if (isset($data['settings'])) {
            $this->settings = $data['settings'];
        }
    }

    public function get_settings($key = null)
    {
        if ($key === null) {
            return $this->settings;
        }

        return $this->settings[$key] ?? null;
    }
}

namespace Elementor\Modules\DynamicTags;

class Module
{
    const TEXT_CATEGORY = 'text';
}
