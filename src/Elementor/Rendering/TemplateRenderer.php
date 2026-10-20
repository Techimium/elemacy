<?php

namespace Elemacy\Elementor\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Compatibility\Capabilities;

/**
 * Renders an Elementor document's content by post ID, wherever on the page
 * it's needed. Independent of TemplateAssetsRegistrar: rendering never depends
 * on when (or whether) a document's assets were registered. See
 * docs/ARCHITECTURE.md § The Elementor boundary.
 */
class TemplateRenderer
{
    /**
     * This request's prepared fragments, keyed by cache_key(), which names
     * the acting site: a fragment prepared for one site is never served on
     * another. Cleared per page through Core\RequestState.
     *
     * @var array<string, string>
     */
    protected static array $prepared = [];

    /** Drops every prepared fragment. */
    public static function reset(): void
    {
        self::$prepared = [];
    }

    protected Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new Capabilities();
    }

    /**
     * @param int $id The post ID to render.
     * @return string
     */
    public function render(int $id): string
    {
        if (!$this->capabilities->plugin_loaded()) {
            return '';
        }

        return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($id);
    }

    /**
     * Whether Elementor knows a document with this ID.
     */
    public function exists(int $id): bool
    {
        $documents = $this->elementor_documents();

        return $documents ? (bool) $documents->get($id) : false;
    }

    /**
     * Renders $id once for ($context, $placement) — discovering its widgets'
     * script/style dependencies as a side effect of Elementor's own render
     * pipeline, so they can be queued before this request's asset-output
     * deadlines — and caches the complete returned fragment (including any
     * non-root style/link markup Elementor prints alongside the document root)
     * for later retrieval via get_prepared.
     *
     * @param int    $id        The post ID to prepare.
     * @param string $context   Caller-defined namespace, e.g. 'popup' or
     *                          'theme-builder'.
     * @param string $placement Caller-defined placement within that
     *                          context, e.g. a popup ID or 'header'. Must be
     *                          distinct per current-loop-item occurrence
     *                          when $id renders item-dependent content —
     *                          this cache never derives that on its own.
     * @return string The prepared fragment.
     */
    public function prepare(int $id, string $context, string $placement): string
    {
        $key = $this->cache_key($id, $context, $placement);

        if (array_key_exists($key, self::$prepared)) {
            return self::$prepared[$key];
        }

        $state = $this->snapshot_state();

        // A foreign document runs third-party widget code on a hook (usually
        // wp_enqueue_scripts) that every page passes through, so a throwing
        // widget must cost that document its content, never the host page.
        // The failure is cached as '' so it is not retried this request.
        try {
            $fragment = $this->render($id);
        } catch (\Throwable $error) {
            $fragment = '';
            $this->log_failure($id, $context, $placement, $error);
        } finally {
            $this->restore_state($state);
        }

        self::$prepared[$key] = $fragment;

        return $fragment;
    }

    /**
     * Records a render failure in the PHP error log when debug logging is on.
     */
    protected function log_failure(int $id, string $context, string $placement, \Throwable $error): void
    {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- opt-in debug log, same idiom as ApiExceptionHandler.
            sprintf(
                'Elemacy: failed to render document %d (%s/%s): %s: %s',
                $id,
                $context,
                $placement,
                get_class($error),
                $error->getMessage()
            )
        );
    }

    /**
     * $id's fragment previously prepared for ($context, $placement) this
     * request, or null when prepare() was never called for that exact key.
     *
     * @param int    $id
     * @param string $context
     * @param string $placement
     * @return string|null
     */
    public function get_prepared(int $id, string $context, string $placement): ?string
    {
        return self::$prepared[$this->cache_key($id, $context, $placement)] ?? null;
    }

    /**
     * @param int    $id
     * @param string $context
     * @param string $placement
     * @return string
     */
    protected function cache_key(int $id, string $context, string $placement): string
    {
        return get_current_blog_id() . '|' . $id . '|' . $context . '|' . $placement;
    }

    /**
     * @return array{post: \WP_Post|null, wp_query: \WP_Query|null, document: mixed, edit_mode: bool, ob_level: int}
     */
    protected function snapshot_state(): array
    {
        global $post, $wp_query;

        $documents = $this->elementor_documents();
        $editor = $this->elementor_editor();

        return [
            'post' => $post,
            'wp_query' => $wp_query,
            'document' => $documents ? $documents->get_current() : null,
            'edit_mode' => $editor ? $editor->is_edit_mode() : false,
            'ob_level' => ob_get_level(),
        ];
    }

    /**
     * @param array{post: \WP_Post|null, wp_query: \WP_Query|null, document: mixed, edit_mode: bool, ob_level: int} $state
     * @return void
     */
    protected function restore_state(array $state): void
    {
        global $post, $wp_query;

        // Elementor's own render pipeline nests several ob_start() calls
        // (Document::print_elements(), Element_Base::print_element(),
        // Frontend::get_builder_content()) with no try/finally of their
        // own: a widget that throws mid-render leaves every one of those
        // buffers open, silently swallowing whatever the rest of the
        // request prints next until something eventually closes them.
        // Discard back down to the level seen before this render started.
        while (ob_get_level() > $state['ob_level']) {
            ob_end_clean();
        }

        $post = $state['post']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring a snapshot taken by this same class, same idiom as PostLoopItem::enter.
        $wp_query = $state['wp_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- see above.

        if ($post instanceof \WP_Post) {
            setup_postdata($post);
        }

        $editor = $this->elementor_editor();
        if ($editor) {
            $editor->set_edit_mode($state['edit_mode']);
        }

        $documents = $this->elementor_documents();
        if (!$documents) {
            return;
        }

        $guard = 0;

        // restore_document() only pops Documents_Manager's own switch stack;
        // a render that throws mid-way (e.g. inside print_elements_with_
        // wrapper()) can leave it deeper than when this snapshot was taken,
        // since that pipeline has no finally of its own. Pop until the
        // current document matches what was snapshotted, bounded so a
        // genuinely corrupted stack can never hang the request.
        while ($documents->get_current() !== $state['document'] && $guard < 64) {
            $documents->restore_document();
            ++$guard;
        }
    }

    /**
     * @return object|null Elementor's Documents_Manager instance, or null
     *                      when Elementor isn't loaded or a test stub omits it.
     */
    protected function elementor_documents()
    {
        if (!$this->capabilities->plugin_loaded()) {
            return null;
        }

        $documents = \Elementor\Plugin::instance()->documents;

        return is_object($documents) ? $documents : null;
    }

    /**
     * @return object|null Elementor's Editor instance, or null when
     *                      Elementor isn't loaded or a test stub omits it.
     */
    protected function elementor_editor()
    {
        if (!$this->capabilities->plugin_loaded()) {
            return null;
        }

        $editor = \Elementor\Plugin::instance()->editor;

        return is_object($editor) ? $editor : null;
    }
}
