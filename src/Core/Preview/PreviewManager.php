<?php

namespace Elemacy\Core\Preview;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\PreviewContextProviderInterface;
use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Loop\Preview\LoopItemPreviewContextProvider;
use Elementor\Plugin;
use WP_Query;

/**
 * Makes previewable documents render against real content in the Elementor editor.
 *
 * The editor never renders the document through the frontend `the_content` path;
 * it resolves elements against the template post itself in three places, each of
 * which we swap for the document's chosen preview context:
 *
 *  - Editor page load → Document::get_initial_config() renders every widget's HTML
 *                       to seed the preview, with the global post pinned to the
 *                       template. We bracket the editor's enqueue_scripts span,
 *                       where that render runs, via before/after_enqueue_scripts.
 *  - `render_tags`    → DynamicTags\Manager switches to the document post, wrapped
 *                       by the before/after_render actions we hook here.
 *  - `render_widget`  → Widgets_Manager calls query_posts([ p => editor_post_id ]),
 *                       which we rewrite via pre_get_posts.
 *
 * A document previews a single post (Single → switch_to_post), a query (Archive,
 * Search → switch_to_query), or one data-source-provided loop item (Loop Item →
 * $loop_provider->enter(), the same LoopContext::push() + item->enter() seam Loop
 * Grid/Carousel rendering already uses, just behind PreviewContextProviderInterface
 * so this class itself never imports a concrete Loop class — design.md D7),
 * decided by get_preview_query_args()/get_preview_post_id()/get_preview_item().
 * Scoped to previewable documents, so normal pages and the frontend are untouched.
 */
class PreviewManager
{
    /** @var array{kind: string, context?: mixed} */
    protected array $tags_context = ['kind' => ''];

    /** @var array{kind: string, context?: mixed} */
    protected array $config_context = ['kind' => ''];

    protected bool $registered = false;

    protected PreviewContextProviderInterface $loop_provider;

    /**
     * @param PreviewContextProviderInterface|null $loop_provider Defaults to
     *        the Loop-owned implementation — this class itself never
     *        imports a concrete Loop class, only this Core-owned contract
     *        (design.md D7).
     */
    public function __construct(?PreviewContextProviderInterface $loop_provider = null)
    {
        $this->loop_provider = $loop_provider ?? new LoopItemPreviewContextProvider();
    }

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing (design.md D7 — constructors store dependencies, they do not
     * register hooks; this is where that happens instead).
     */
    public function register_hooks(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        add_action('elementor/editor/before_enqueue_scripts', [$this, 'before_editor_config']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'after_editor_config']);
        add_action('elementor/dynamic_tags/before_render', [$this, 'before_tags_render']);
        add_action('elementor/dynamic_tags/after_render', [$this, 'after_tags_render']);
        add_action('pre_get_posts', [$this, 'override_render_widget_query']);
    }

    public function before_editor_config(): void
    {
        $this->config_context = $this->enter_preview((int) Plugin::instance()->editor->get_post_id());
    }

    public function after_editor_config(): void
    {
        $this->leave_preview($this->config_context);
        $this->config_context = ['kind' => ''];
    }

    public function before_tags_render(): void
    {
        $this->tags_context = $this->enter_preview((int) get_the_ID());
    }

    public function after_tags_render(): void
    {
        $this->leave_preview($this->tags_context);
        $this->tags_context = ['kind' => ''];
    }

    public function override_render_widget_query(WP_Query $query): void
    {
        // Only the editor's ajax_render_widget queries a single post by id; gating
        // on a previewable document (below) keeps this off every other query.
        if (!wp_doing_ajax()) {
            return;
        }

        $template_post_id = (int) $query->get('p');
        $document = $this->previewable_document($template_post_id);

        if (!$document) {
            return;
        }

        $query_args = $document->get_preview_query_args();

        if (!empty($query_args)) {
            // Query-context doc (archive/search): turn the single-post render query
            // into the previewed query so "Current Query" widgets show real posts.
            $query->set('p', 0);

            foreach ($query_args as $key => $value) {
                $query->set($key, $value);
            }

            return;
        }

        $preview_id = $document->get_preview_post_id();

        if ($preview_id > 0 && $preview_id !== $template_post_id) {
            $query->set('p', $preview_id);
        }
    }

    /**
     * Swap the global post/query/loop-item for the document's preview context.
     * Returns the context leave_preview() rolls back — its 'kind' is
     * 'post' | 'query' | 'item' | '', with the loop provider's own opaque
     * token attached when 'item' (Core has no idea what that token is —
     * only the provider that produced it in enter() knows how to consume it
     * in leave()).
     *
     * @return array{kind: string, context?: mixed}
     */
    protected function enter_preview(int $document_post_id): array
    {
        $document = $this->previewable_document($document_post_id);

        if (!$document) {
            return ['kind' => ''];
        }

        $query_args = $document->get_preview_query_args();

        if (!empty($query_args)) {
            Plugin::instance()->db->switch_to_query($query_args);

            return ['kind' => 'query'];
        }

        $preview_id = $document->get_preview_post_id();

        if ($preview_id > 0 && $preview_id !== $document_post_id) {
            Plugin::instance()->db->switch_to_post($preview_id);

            return ['kind' => 'post'];
        }

        $item = $document->get_preview_item();

        if ($item) {
            return [
                'kind' => 'item',
                'context' => $this->loop_provider->enter($item),
            ];
        }

        return ['kind' => ''];
    }

    /**
     * @param array{kind: string, context?: mixed} $context
     */
    protected function leave_preview(array $context): void
    {
        if ('query' === $context['kind']) {
            Plugin::instance()->db->restore_current_query();
        } elseif ('post' === $context['kind']) {
            Plugin::instance()->db->restore_current_post();
        } elseif ('item' === $context['kind']) {
            $this->loop_provider->leave($context['context']);
        }
    }

    /**
     * The previewable document for a post id, or null when the post is not a
     * previewable document (a normal page, the frontend, …).
     */
    protected function previewable_document(int $document_post_id): ?PreviewablePageBase
    {
        if ($document_post_id <= 0) {
            return null;
        }

        $document = Plugin::instance()->documents->get($document_post_id);

        return $document instanceof PreviewablePageBase ? $document : null;
    }
}
