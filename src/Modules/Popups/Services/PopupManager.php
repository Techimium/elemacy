<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\RequestState;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Modules\Popups\Resources\PopupConfigResource;
use Elemacy\Modules\Popups\Support\DocumentDisplay;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elementor\Plugin as ElementorPlugin;

/**
 * Orchestrates frontend popup display: matches popups for the request,
 * conditionally enqueues the engine, and renders each popup's container — top
 * bars at the top of the page (wp_body_open), everything else in the footer.
 */
class PopupManager
{
    /**
     * TemplateAssetsRegistrar/TemplateRenderer preparation context for every
     * popup document, shared with PopupRenderer so both agree on the same
     * cache key without either hardcoding the other's string.
     */
    public const ASSET_CONTEXT = 'popup';

    protected static ?self $instance = null;

    /**
     * Memoized matched popups for the current request.
     *
     * @var \Elemacy\Modules\Popups\DTO\PopupDTO[]|null
     */
    protected $matched = null;

    /**
     * IDs of the top bars already printed this request, so a theme that fires
     * wp_body_open more than once cannot print one twice, and the footer prints
     * only the bars the body-open hook missed.
     *
     * @var array<int, true>
     */
    protected array $printed_top_bars = [];

    protected TypeRegistry $types;
    protected PopupDisplayResolver $display_resolver;
    protected PopupRenderer $renderer;

    public function __construct(
        TypeRegistry $types,
        PopupDisplayResolver $display_resolver,
        PopupRenderer $renderer
    ) {
        $this->types = $types;
        $this->display_resolver = $display_resolver;
        $this->renderer = $renderer;
    }

    /**
     * Returns the shared PopupManager instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static(TypeRegistry::instance(), PopupDisplayResolver::instance(), new PopupRenderer());
        }

        return static::$instance;
    }

    /**
     * Drops this request's memoized matched-popups list —
     * used when a popup's saved conditions/rules/triggers change within the
     * same request, or the acting site changes mid-request, so a later call
     * re-resolves instead of returning a stale or cross-site list.
     */
    public function reset(): void
    {
        $this->matched = null;
        $this->printed_top_bars = [];
    }

    /**
     * Wires the frontend hooks that enqueue and render matched popups.
     *
     * @return void
     */
    public function register_hooks(): void
    {
        RequestState::register(self::class, [$this, 'reset']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue'], 20);
        add_action('wp_body_open', [$this, 'render_top_bars'], 1);
        add_action('wp_footer', [$this, 'render_footer_popups'], 99);
        add_filter('template_include', [$this, 'force_canvas_template'], 99);
        add_action(Hooks::TEMPLATE_ASSETS_COLLECT_ACTION, [$this, 'register_template_assets']);
    }

    /**
     * Prepares each matched popup document — bridging its classic/atomic
     * Elementor CSS exactly as register() alone already did, and additionally
     * rendering it once now so its widgets' script/style dependencies are
     * queued before this request's head/footer output deadlines.
     *
     * @param TemplateAssetsRegistrar $registrar
     * @return void
     */
    public function register_template_assets(TemplateAssetsRegistrar $registrar): void
    {
        foreach ($this->get_matched_ids() as $id) {
            $registrar->prepare($id, self::ASSET_CONTEXT, (string) $id);
        }
    }

    /**
     * The IDs of the popups matched for the current request.
     *
     * @return int[]
     */
    public function get_matched_ids(): array
    {
        return array_map(static fn ($popup) => (int) $popup->id, $this->get_matched());
    }

    /**
     * Render a singular popup through Elementor's Canvas template.
     *
     * @param string $template The template path Elementor is about to load.
     * @return string
     */
    public function force_canvas_template($template)
    {
        if (!is_singular(LibraryPostType::POST_TYPE)) {
            return $template;
        }

        // Only popup-group library items should open on the popup canvas; theme
        // templates on the same CPT keep their own preview handling.
        $type = (string) get_post_meta((int) get_queried_object_id(), MetaKeys::TEMPLATE_TYPE, true);

        if (!in_array($type, $this->types->names_in_group('popup'), true)) {
            return $template;
        }

        if (defined('ELEMENTOR_PATH')) {
            $canvas = ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';

            if (is_readable($canvas)) {
                return $canvas;
            }
        }

        return $template;
    }

    /**
     * Resolve and memoize the popups matching the current request.
     *
     * @return \Elemacy\Modules\Popups\DTO\PopupDTO[]
     */
    protected function get_matched(): array
    {
        if ($this->matched !== null) {
            return $this->matched;
        }

        if (is_admin()) {
            $this->matched = [];

            return $this->matched;
        }

        if (class_exists('\Elementor\Plugin')) {
            $elementor = ElementorPlugin::instance();

            if ($elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode()) {
                $this->matched = [];

                return $this->matched;
            }
        }

        $this->matched = $this->display_resolver->resolve_all();

        return $this->matched;
    }

    /**
     * Enqueues the popup engine and localizes matched-popup config, but only
     * when at least one popup actually matches the current request.
     *
     * @return void
     */
    public function maybe_enqueue(): void
    {
        $matched = $this->get_deliverable();

        if (empty($matched)) {
            return;
        }

        wp_enqueue_script('elemacy-popups-engine');
        wp_enqueue_style('elemacy-popups');

        wp_localize_script('elemacy-popups-engine', 'elemacyPopups', [
            'popups' => PopupConfigResource::collection($matched),
            'i18n'   => [
                'close' => __('Close', 'elemacy'),
            ],
            // Lets the engine name, in the browser console, a popup it cannot
            // start because an extension's trigger or rule handler is missing.
            'debug'  => defined('WP_DEBUG') && WP_DEBUG,
        ]);
    }

    /**
     * The matched popups that actually have something to show. A popup whose
     * document renders nothing — an empty widget, or one that threw while
     * rendering — is dropped from the markup and the browser config alike, so
     * the visitor never gets an overlay with no content in it.
     *
     * Reads the fragments TemplateAssetsRegistrar prepared on
     * wp_enqueue_scripts (priority 10), which is why callers run after it.
     *
     * @return \Elemacy\Modules\Popups\DTO\PopupDTO[]
     */
    public function get_deliverable(): array
    {
        return array_values(array_filter(
            $this->get_matched(),
            fn ($popup): bool => '' !== trim($this->renderer->prepared_content($popup))
        ));
    }

    /**
     * Render top bars at the very top of the page (in normal flow) so they sit
     * above the content like a header, rather than overlaying it.
     */
    public function render_top_bars(): void
    {
        foreach ($this->get_deliverable() as $popup) {
            if ($this->is_top_bar($popup) && !isset($this->printed_top_bars[(int) $popup->id])) {
                $this->printed_top_bars[(int) $popup->id] = true;
                $this->renderer->render($popup);
            }
        }
    }

    /**
     * Render the remaining popups (modals, floating, banners, bottom bars) in the
     * footer; CSS positions each one. Top bars are handled by render_top_bars(),
     * except on a theme that never fires wp_body_open: those are printed here,
     * marked for the engine to move to the top of <body>.
     */
    public function render_footer_popups(): void
    {
        foreach ($this->get_deliverable() as $popup) {
            if (!$this->is_top_bar($popup)) {
                $this->renderer->render($popup);
            } elseif (!isset($this->printed_top_bars[(int) $popup->id])) {
                $this->printed_top_bars[(int) $popup->id] = true;
                $this->renderer->render($popup, true);
            }
        }
    }

    /**
     * Available popup types for the admin app's type picker,
     * mirroring ThemeBuilderManager::get_available_template_types() but also
     * exposing availability so a registered-but-inactive extension type
     * is distinguishable from a real, selectable one — no extension
     * currently registers one, but the shape is ready for when one does.
     *
     * @return array<int, array{value: string, label: string, availability: array}>
     */
    public function get_available_popup_types(): array
    {
        return array_map(
            fn ($definition): array => [
                'value' => $definition->name,
                'label' => $definition->label,
                'availability' => $this->types->get_availability($definition->name)->to_array(),
            ],
            $this->types->by_group('popup')
        );
    }

    /**
     * A top-positioned bar flows at the top of the document, so it is rendered
     * early (wp_body_open) instead of in the footer.
     *
     * @param \Elemacy\Modules\Popups\DTO\PopupDTO $popup The popup to check.
     * @return bool
     */
    protected function is_top_bar($popup): bool
    {
        if (PopupTypes::TOPBAR !== $popup->type) {
            return false;
        }

        // A topbar is a top header bar unless explicitly positioned at the
        // bottom (bottom bars stay fixed and render in the footer).
        $display = DocumentDisplay::for_popup((int) $popup->id);

        return 'bottom' !== ($display['position'] ?? 'top');
    }
}
