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

    /** @var object|null */
    public $editor;

    /** @var \Elementor\Core\Documents_Manager */
    public $documents;

    public function __construct()
    {
        $this->documents = new \Elementor\Core\Documents_Manager();
    }

    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }
}

namespace Elementor\Core;

/**
 * Faithful to the real class's get_current() shape - the only member
 * PostContent's "is this document already the one being rendered" check
 * actually calls. Defaults to no active document; tests that need one set
 * $current directly.
 */
class Documents_Manager
{
    /** @var object|null */
    public $current;

    public function get_current()
    {
        return $this->current;
    }
}

namespace Elementor\Core\Base;

/**
 * Faithful to the real class's get_main_id() shape - the only member
 * PostContent's document-identity check actually calls.
 */
class Document
{
    /** @var int */
    protected $main_id;

    public function __construct($main_id)
    {
        $this->main_id = $main_id;
    }

    public function get_main_id()
    {
        return $this->main_id;
    }
}

namespace Elementor\Core\Editor;

/**
 * Faithful to the real class's is_edit_mode()/set_edit_mode() shape - the
 * only members PostContent's edit-mode toggle actually calls.
 */
class Editor
{
    /** @var bool */
    protected $edit_mode = false;

    public function is_edit_mode()
    {
        return $this->edit_mode;
    }

    public function set_edit_mode($edit_mode)
    {
        $this->edit_mode = $edit_mode;
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
