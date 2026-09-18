<?php

namespace Elemacy\Core\Integrations\Elementor;

defined('ABSPATH') || exit;

/**
 * Walks every element in a document's saved `_elementor_data` tree —
 * classic or Atomic, the saved shape is identical for both — using
 * Elementor's own core document/DB primitives (Documents_Manager::get(),
 * Document::get_elements_data(), DB::iterate_data()), none of which belong
 * to the optional Atomic Widgets module.
 *
 * Before this class, ClassicCssRenderer::has_dynamic_settings() reused
 * Elementor's `Modules\AtomicWidgets\Utils\Utils::traverse_post_elements()`
 * purely because it happened to walk the right data structure — an
 * accidental dependency design.md D9 names for removal (task 9.3): reading
 * the installed Elementor source (modules/atomic-widgets/utils/utils.php)
 * confirms that method itself is nothing more than
 * `Documents_Manager::get()` + `Document::get_elements_data()` +
 * `DB::iterate_data()` — three genuinely core, non-optional Elementor calls
 * — wrapped in the Atomic Widgets namespace. This class makes that the
 * explicit, classic-owned contract instead of borrowing it through an
 * optional module that a classic-only Elementor install may not have.
 */
class ElementTreeWalker
{
    protected Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new Capabilities();
    }

    /**
     * Walks every element of $post_id's published content, calling
     * $callback once per element with its raw `$element_data` array
     * (including a `settings` key). A no-op — never a fatal — when
     * Elementor isn't loaded, the post has no document, or the installed
     * Elementor's Plugin/Documents_Manager/Document/DB shape doesn't (or, in
     * a test stub, doesn't yet) expose the specific methods this needs; each
     * step is defensively checked rather than assumed, the same idiom
     * TemplateRenderer::elementor_documents()/elementor_editor() already use
     * for the same reason.
     *
     * @param int      $post_id
     * @param callable $callback
     * @return void
     */
    public function walk(int $post_id, callable $callback): void
    {
        if (!$this->capabilities->plugin_loaded()) {
            return;
        }

        $documents = \Elementor\Plugin::instance()->documents;

        if (!is_object($documents) || !method_exists($documents, 'get')) {
            return;
        }

        $document = $documents->get($post_id);

        if (!is_object($document) || !method_exists($document, 'get_elements_data')) {
            return;
        }

        $elements_data = $document->get_elements_data();

        if (empty($elements_data)) {
            return;
        }

        $db = \Elementor\Plugin::instance()->db ?? null;

        if (!is_object($db) || !method_exists($db, 'iterate_data')) {
            return;
        }

        $db->iterate_data($elements_data, static function ($element_data) use ($callback) {
            call_user_func($callback, $element_data);
        });
    }
}
