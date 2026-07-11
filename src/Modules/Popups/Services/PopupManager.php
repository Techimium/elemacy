<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Resources\PopupConfigResource;
use Elemacy\Modules\Popups\Support\DocumentDisplay;
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
    protected static ?self $instance = null;

    /**
     * Memoized matched popups for the current request.
     *
     * @var \Elemacy\Modules\Popups\DTO\PopupDTO[]|null
     */
    protected $matched = null;

    /**
     * Returns the shared PopupManager instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Wires the frontend hooks that enqueue and render matched popups.
     *
     * @return void
     */
    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue'], 20);
        add_action('wp_body_open', [$this, 'render_top_bars'], 1);
        add_action('wp_footer', [$this, 'render_footer_popups'], 99);
        add_filter('template_include', [$this, 'force_canvas_template'], 99);
    }

    /**
     * Render a singular popup through Elementor's Canvas template.
     *
     * A popup is authored on a private CPT bound to a custom Elementor document
     * type. When Elementor loads the editor preview it requests the popup on the
     * frontend; if the active theme's single template does not call the_content()
     * Elementor aborts with "You must call 'the_content' function…". Forcing the
     * Canvas template (which calls the_content() via print_content()) guarantees
     * the preview renders, with no theme header/footer — exactly what a popup
     * canvas should be. Only affects direct singular popup requests, i.e. the
     * editor preview; normal pages are untouched.
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

        if (!in_array($type, TypeRegistry::instance()->names_in_group('popup'), true)) {
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
     * Bails (empty) in the admin and inside the Elementor editor/preview so
     * popups never interfere with the builder.
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

        $this->matched = PopupDisplayResolver::instance()->resolve_all();

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
        $matched = $this->get_matched();

        if (empty($matched)) {
            return;
        }

        wp_enqueue_script('elemacy-popups-engine');
        wp_enqueue_style('elemacy-popups');

        $this->enqueue_elementor_assets($matched);

        wp_localize_script('elemacy-popups-engine', 'elemacyPopups', [
            'popups' => PopupConfigResource::collection($matched),
            'i18n'   => [
                'close' => __('Close', 'elemacy'),
            ],
        ]);
    }

    /**
     * Load Elementor's frontend assets and each matched popup's generated CSS.
     *
     * A popup's Elementor content is injected in the footer. On pages whose main
     * content is not Elementor-built, Elementor does not enqueue its frontend
     * stylesheet/scripts, the active kit, or the popup's per-post CSS — so the
     * widgets would render unstyled. Forcing these here (on wp_enqueue_scripts)
     * keeps the popup styled and interactive without a flash of unstyled content.
     *
     * @param \Elemacy\Modules\Popups\DTO\PopupDTO[] $matched The popups matched for this request.
     * @return void
     */
    protected function enqueue_elementor_assets(array $matched): void
    {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        $frontend = ElementorPlugin::instance()->frontend;
        $frontend->enqueue_styles();
        $frontend->enqueue_scripts();

        if (!class_exists('\Elementor\Core\Files\CSS\Post')) {
            return;
        }

        foreach ($matched as $popup) {
            \Elementor\Core\Files\CSS\Post::create((int) $popup->id)->enqueue();
        }
    }

    /**
     * Render top bars at the very top of the page (in normal flow) so they sit
     * above the content like a header, rather than overlaying it.
     */
    public function render_top_bars(): void
    {
        $renderer = new PopupRenderer();

        foreach ($this->get_matched() as $popup) {
            if ($this->is_top_bar($popup)) {
                $renderer->render($popup);
            }
        }
    }

    /**
     * Render the remaining popups (modals, floating, banners, bottom bars) in the
     * footer; CSS positions each one. Top bars are handled by render_top_bars().
     */
    public function render_footer_popups(): void
    {
        $renderer = new PopupRenderer();

        foreach ($this->get_matched() as $popup) {
            if (!$this->is_top_bar($popup)) {
                $renderer->render($popup);
            }
        }
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
        if ('topbar' !== $popup->type) {
            return false;
        }

        // A topbar is a top header bar unless explicitly positioned at the
        // bottom (bottom bars stay fixed and render in the footer).
        $display = DocumentDisplay::for_popup((int) $popup->id);

        return 'bottom' !== ($display['position'] ?? 'top');
    }
}
