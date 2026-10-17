<?php

namespace Elemacy\Elementor\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\RequestState;
use Elemacy\Elementor\Compatibility\Capabilities;

/**
 * Registers post IDs whose Elementor content renders on a page other than
 * their own, so that document's CSS actually gets generated.
 */
class TemplateAssetsRegistrar
{
    /**
     * Prepended to a stylesheet's id to take it out of the pattern the editor
     * canvas discards; see keep_in_editor_canvas().
     */
    protected const EDITOR_CANVAS_ID_PREFIX = 'elemacy-';

    /**
     * @var array<int, int> Post IDs already registered this request, keyed by id.
     */
    protected array $registered_ids = [];

    /**
     * @var TemplateRenderer
     */
    protected TemplateRenderer $renderer;

    protected bool $registered = false;

    protected Capabilities $capabilities;

    public function __construct(TemplateRenderer $renderer, Capabilities $capabilities)
    {
        $this->renderer = $renderer;
        $this->capabilities = $capabilities;
    }

    /**
     * Wires the collection hook and the Global Classes related-posts filter.
     * Idempotent: a second call does nothing.
     *
     * @return void
     */
    public function register_hooks(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        RequestState::register(self::class, function (): void {
            $this->registered_ids = [];
        });
        add_action('wp_enqueue_scripts', [$this, 'collect']);
        add_filter('elementor/document/related_posts', [$this, 'get_related_posts'], 10, 2);
        add_filter('style_loader_tag', [$this, 'keep_in_editor_canvas'], 10, 2);
    }

    /**
     * Fires the collect action so modules can register their post IDs, then
     * forces Elementor's base frontend assets to load if anything was
     * registered — needed on host pages that aren't themselves built with
     * Elementor, which would otherwise never enqueue Elementor's frontend
     * stylesheet/scripts or active kit at all.
     *
     * @return void
     */
    public function collect(): void
    {
        do_action(Hooks::TEMPLATE_ASSETS_COLLECT_ACTION, $this);

        if (empty($this->registered_ids) || !$this->capabilities->plugin_loaded()) {
            return;
        }

        $frontend = \Elementor\Plugin::instance()->frontend;
        $frontend->enqueue_styles();
        $frontend->enqueue_scripts();
    }

    /**
     * Declares that $id's Elementor content will render on the current page,
     * bridging both of Elementor's CSS pipelines for it. Idempotent per request.
     *
     * @param int $id The post ID whose CSS should be generated.
     * @return void
     */
    public function register(int $id): void
    {
        if (isset($this->registered_ids[$id])) {
            return;
        }

        $this->registered_ids[$id] = $id;

        if ($this->capabilities->classic_css_files()) {
            \Elementor\Core\Files\CSS\Post::create($id)->enqueue();
        }

        do_action('elementor/post/render', $id); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Elementor owns this public hook name.
    }

    /**
     * The CSS bridging of register(), plus a full early render of $id for ($context,
     * $placement), so its widgets' interactive dependencies are queued before
     * this request's asset-output deadlines and the resulting fragment can be
     * emitted later — via TemplateRenderer's own get_prepared(), with no need to
     * hold onto this registrar instance — without rendering $id again.
     *
     * @param int    $id
     * @param string $context   Caller-defined namespace, e.g. 'popup' or
     *                          'theme-builder'.
     * @param string $placement Caller-defined placement within that
     *                          context; see TemplateRenderer::prepare().
     * @return string The prepared fragment.
     */
    public function prepare(int $id, string $context, string $placement): string
    {
        $this->register($id);

        return $this->renderer->prepare($id, $context, $placement);
    }

    /**
     * @param int    $id
     * @param string $context
     * @param string $placement
     * @return string|null Null when prepare() was never called for this exact key.
     */
    public function get_prepared(int $id, string $context, string $placement): ?string
    {
        return $this->renderer->get_prepared($id, $context, $placement);
    }

    /**
     * Handler for WordPress's `style_loader_tag` filter. In the Elementor
     * editor's preview frame, keeps a registered template's atomic stylesheet
     * from being discarded by the canvas.
     *
     * Once the edited document has atomic styles of its own, the editor renders
     * them in the browser and removes every `local-<id>-<context>-<breakpoint>-css`
     * link from the frame. That pattern carries no document id, so it also
     * removes the server-rendered atomic CSS of a header, footer or other
     * template that only renders on this page — and nothing replaces it, so the
     * template loses its styles the moment an atomic element is added. Giving a
     * registered template's link a different id takes it out of that pattern;
     * the stylesheet itself is untouched. The edited document's own link is
     * left alone: the editor takes that one over, and removing it is right.
     *
     * @param string $tag    The <link> tag about to be printed.
     * @param string $handle The stylesheet's registered handle.
     * @return string
     */
    public function keep_in_editor_canvas($tag, $handle)
    {
        if (!is_string($tag) || !is_string($handle) || 1 !== preg_match('/^local-(\d+)-/', $handle, $match)) {
            return $tag;
        }

        $id = (int) $match[1];
        $edited_id = $this->editor_preview_document_id();

        if (null === $edited_id || $id === $edited_id || !isset($this->registered_ids[$id])) {
            return $tag;
        }

        $renamed = preg_replace(
            '/(\sid=([\'"]))(' . preg_quote($handle . '-css', '/') . ')\2/',
            '${1}' . self::EDITOR_CANVAS_ID_PREFIX . '${3}${2}',
            $tag,
            1
        );

        return is_string($renamed) ? $renamed : $tag;
    }

    /**
     * The document being edited when this request is the editor's preview
     * frame; null on every other request.
     *
     * @return int|null
     */
    protected function editor_preview_document_id(): ?int
    {
        if (!$this->capabilities->plugin_loaded()) {
            return null;
        }

        $preview = \Elementor\Plugin::instance()->preview ?? null;

        if (!is_object($preview) || !$preview->is_preview_mode()) {
            return null;
        }

        return (int) $preview->get_post_id();
    }

    /**
     * Handler for Elementor's `elementor/document/related_posts` filter: feeds
     * this request's registered ids into Elementor's Global Classes
     * cache-dependency tracking, so a global class edit correctly invalidates
     * pages that render a registered document using it.
     *
     * @param array $related The related post ids accumulated so far.
     * @param int   $post_id The post the filter is resolving related ids for.
     * @return array
     */
    public function get_related_posts(array $related, $post_id): array
    {
        $queried_post_id = (int) get_the_ID();

        if (!$queried_post_id || (int) $post_id !== $queried_post_id) {
            return $related;
        }

        return array_values(array_unique(array_merge($related, $this->registered_ids)));
    }
}
