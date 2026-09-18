<?php

namespace Elemacy\Core\Integrations\Elementor;

defined('ABSPATH') || exit;

/**
 * The one place PostContent's exceptional "embed this exact document inside
 * its own natural render" path touches Elementor directly:
 * get_builder_content($post_id, true) — the lower-level call with no
 * same-post recursion guard of its own, matching what Elementor Pro's own
 * equivalent widget uses for this exact case. get_builder_content_for_
 * display() is deliberately not used here: it refuses to render a post the
 * same as the one currently being viewed (get_the_ID() === $post_id, which
 * is always true for this tag), which is exactly the recursion PostContent
 * needs to allow for a *different* actively-rendering document (design.md
 * D9: "do not replace this path with a generic display call that
 * reintroduces same-document recursion").
 *
 * Elementor's document-switch stack (Documents_Manager::switch_to_document(),
 * invoked internally by get_builder_content()) and its editor-mode flag are
 * both mutated with no `finally` of their own — a render that throws
 * mid-way leaves both exactly as get_builder_content() left them, not as
 * they were before this call. This class snapshots and restores both,
 * the same idiom TemplateRenderer::prepare()/restore_state() already use
 * for its own render call.
 */
class SameDocumentContentRenderer
{
    protected Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new Capabilities();
    }

    /**
     * Whether Elementor's Plugin/documents/editor/frontend shape this class
     * needs is actually present.
     */
    public function available(): bool
    {
        if (!$this->capabilities->plugin_loaded()) {
            return false;
        }

        $plugin = \Elementor\Plugin::instance();

        return is_object($plugin->documents)
            && is_object($plugin->editor)
            && method_exists($plugin->editor, 'is_edit_mode')
            && method_exists($plugin->editor, 'set_edit_mode')
            && is_object($plugin->frontend)
            && method_exists($plugin->frontend, 'get_builder_content');
    }

    /**
     * Renders $post_id's Elementor content for embedding inside another
     * actively-rendering document. Returns '' (never a fatal) when
     * unavailable or Elementor itself has no builder content for $post_id.
     * Editor mode and the document-switch stack are always restored to
     * their exact pre-call state, including when this throws.
     *
     * @param int $post_id
     * @return string
     */
    public function render(int $post_id): string
    {
        if (!$this->available()) {
            return '';
        }

        $plugin = \Elementor\Plugin::instance();
        $documents = $plugin->documents;
        $editor = $plugin->editor;

        $current_document = method_exists($documents, 'get_current') ? $documents->get_current() : null;
        $was_edit_mode = $editor->is_edit_mode();
        $ob_level_before = ob_get_level();

        $editor->set_edit_mode(false);

        try {
            return (string) $plugin->frontend->get_builder_content($post_id, true);
        } finally {
            // get_builder_content() goes through the same nested-ob_start()
            // render pipeline (Document::print_elements(), Element_Base::
            // print_element()) TemplateRenderer::restore_state() already
            // guards for the identical reason: no finally of its own, so a
            // widget that throws mid-render leaves every buffer it opened
            // still open.
            while (ob_get_level() > $ob_level_before) {
                ob_end_clean();
            }

            $editor->set_edit_mode($was_edit_mode);
            $this->restore_document_stack($documents, $current_document);
        }
    }

    /**
     * Pops Documents_Manager's own switch stack until its current document
     * matches $target — bounded so a genuinely corrupted stack can never
     * hang the request, same guard TemplateRenderer::restore_state() uses.
     *
     * @param object   $documents
     * @param mixed    $target
     * @return void
     */
    protected function restore_document_stack($documents, $target): void
    {
        if (!method_exists($documents, 'get_current') || !method_exists($documents, 'restore_document')) {
            return;
        }

        $guard = 0;

        while ($documents->get_current() !== $target && $guard < 64) {
            $documents->restore_document();
            ++$guard;
        }
    }
}
