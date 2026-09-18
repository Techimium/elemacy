<?php

namespace Elemacy\Core\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\Integrations\Elementor\Capabilities;

/**
 * Registers post IDs whose Elementor content renders on a page other than
 * their own, so that document's CSS actually gets generated.
 *
 * Elementor's classic per-post CSS file can be built for an arbitrary post ID
 * at any time, but its atomic-widgets pipeline (Atomic_Styles_Manager) only
 * produces CSS for post IDs it has learned about via the `elementor/post/render`
 * action, and it reads that list exactly once per request — when
 * Frontend::enqueue_styles() fires `after_enqueue_post_styles`, itself hooked
 * on `wp_enqueue_scripts` at priority 20 (Frontend::ENQUEUED_STYLES_PRIORITY).
 * Collecting registrations on `wp_enqueue_scripts` at the plain default
 * priority (10) guarantees every id is known before that one-shot read,
 * regardless of whether Elementor's own hook or this one runs first at 20.
 *
 * This class has no knowledge of what a registered id represents (a popup, a
 * theme template, or anything else) — modules "inform" it by listening on
 * Hooks::TEMPLATE_ASSETS_COLLECT_ACTION and calling register() themselves.
 */
class TemplateAssetsRegistrar
{
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

    /**
     * @param TemplateRenderer|null $renderer
     * @param Capabilities|null     $capabilities
     */
    public function __construct(?TemplateRenderer $renderer = null, ?Capabilities $capabilities = null)
    {
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->capabilities = $capabilities ?? new Capabilities();
    }

    /**
     * Wires the collection hook and the Global Classes related-posts filter.
     * Idempotent: a second call does nothing (design.md D7).
     *
     * @return void
     */
    public function register_hooks(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        add_action('wp_enqueue_scripts', [$this, 'collect']);
        add_filter('elementor/document/related_posts', [$this, 'get_related_posts'], 10, 2);
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
     * register()'s CSS bridging plus a full early render of $id for
     * ($context, $placement), so its widgets' interactive dependencies are
     * queued before this request's asset-output deadlines and the
     * resulting fragment can be emitted later — via TemplateRenderer's own
     * get_prepared(), with no need to hold onto this registrar instance —
     * without rendering $id again. Idempotent per ($id, $context, $placement).
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
