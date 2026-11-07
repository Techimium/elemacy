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

    /** @var object|null */
    public $preview;

    /** @var \Elementor\Core\Documents_Manager */
    public $documents;

    /** @var \Elementor\DB */
    public $db;

    public function __construct()
    {
        $this->documents = new \Elementor\Core\Documents_Manager();
        $this->db = new \Elementor\DB();
    }

    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }
}

/**
 * Empty on purpose: tests that need a source's register_controls(Widget_Base
 * $widget) call to actually run only need *some* instance satisfying the
 * type hint — none call any method on it.
 */
class Widget_Base
{
}

/**
 * Faithful to the real class's iterate_data() shape (includes/db.php) — the
 * one method ElementTreeWalker calls. Genuinely core Elementor, not part of
 * the optional Atomic Widgets module; stubbed here so
 * ClassicCssRenderer::has_dynamic_settings()'s real "found a dynamic
 * binding" branch is exercisable in this fast harness even though the real
 * Atomic Widgets classes are never stubbed (design.md D9, task 9.3 — this is
 * exactly the decoupling that makes this positive-path test possible at
 * all, where it previously was not: has_dynamic_settings() unconditionally
 * returned false in this harness before task 9.3, since it depended on
 * Atomic Widgets classes this harness never defines).
 */
class DB
{
    public function iterate_data($data_container, $callback, $args = [])
    {
        if (isset($data_container['elType'])) {
            if (!empty($data_container['elements'])) {
                $data_container['elements'] = $this->iterate_data($data_container['elements'], $callback, $args);
            }

            return call_user_func($callback, $data_container, $args);
        }

        foreach ($data_container as $key => $value) {
            $data_container[$key] = $this->iterate_data($value, $callback, $args);
        }

        return $data_container;
    }
}

namespace Elementor\Core;

/**
 * Faithful to the real class's get_current()/get() shape - the only members
 * PostContent's "is this document already the one being rendered" check and
 * ElementTreeWalker::walk() actually call. Defaults to no active document
 * and an empty by-id registry; tests that need either set $current/$by_id
 * directly.
 */
class Documents_Manager
{
    /** @var object|null */
    public $current;

    /** @var array<int, object> */
    public $by_id = [];

    public function get_current()
    {
        return $this->current;
    }

    public function get($post_id)
    {
        return $this->by_id[$post_id] ?? null;
    }
}

namespace Elementor\Core\Base;

/**
 * Faithful to the real class's get_main_id()/get_elements_data() shape - the
 * only members PostContent's document-identity check and
 * ElementTreeWalker::walk() actually call, plus the get_properties() defaults
 * a document subclass narrows in its own override.
 */
class Document
{
    /** @var int */
    protected $main_id;

    /**
     * The real class's default property set, copied verbatim for the keys a
     * document subclass reads or overrides. A document that narrows one of
     * these must see the same starting value the real Elementor gives it.
     */
    public static function get_properties()
    {
        return [
            'has_elements' => true,
            'is_editable' => true,
            'edit_capability' => '',
            'show_in_finder' => true,
            'show_on_admin_bar' => true,
            'support_kit' => false,
            'show_navigator' => true,
            'allow_adding_widgets' => true,
            'support_page_layout' => true,
            'show_copy_and_share' => false,
            'allow_closing_remote_library' => true,
        ];
    }

    /** @var array */
    public $elements_data = [];

    public function __construct($main_id)
    {
        $this->main_id = $main_id;
    }

    public function get_main_id()
    {
        return $this->main_id;
    }

    public function get_elements_data()
    {
        return $this->elements_data;
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

namespace Elementor\Core\DynamicTags;

/**
 * Faithful to the real class's one constant ClassicCssRenderer::
 * has_dynamic_settings() actually reads (core/dynamic-tags/manager.php);
 * genuinely core Elementor, not part of the optional Atomic Widgets module.
 */
class Manager
{
    const DYNAMIC_SETTING_KEY = '__dynamic__';
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

namespace Elementor\Core\DocumentTypes;

/**
 * Stand-in for the page document base Theme Builder's chrome documents
 * extend: only the static property surface those subclasses narrow, plus the
 * `admin_tab_group`/`support_wp_page_templates` the real class adds.
 */
abstract class PageBase extends \Elementor\Core\Base\Document
{
    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['admin_tab_group'] = '';
        $properties['support_wp_page_templates'] = true;

        return $properties;
    }
}
