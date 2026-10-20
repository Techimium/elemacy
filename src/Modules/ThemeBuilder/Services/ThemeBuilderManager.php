<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Hooks;
use Elemacy\Core\RequestState;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager;
use Elemacy\Modules\ThemeBuilder\Compatibility\ElementorProLocations;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elementor\Modules\PageTemplates\Module as PageTemplates;
use Elementor\Plugin;

class ThemeBuilderManager
{
    /**
     * TemplateAssetsRegistrar/TemplateRenderer preparation context for
     * every theme location document.
     */
    public const ASSET_CONTEXT = 'theme-builder';

    /** The body class Elementor's own Full Width template adds. */
    private const FULL_WIDTH_BODY_CLASS = 'elementor-template-full-width';

    /**
     * @var ThemeBuilderManager
     */
    protected static $instance = null;

    protected CompatibilityManager $compatibility;
    protected LocationRegistry $locations;
    protected TemplateResolver $resolver;
    protected TypeRegistry $types;
    protected TemplateRenderer $renderer;
    protected ElementorProLocations $elementor_pro;

    public function __construct(
        CompatibilityManager $compatibility,
        LocationRegistry $locations,
        TemplateResolver $resolver,
        TypeRegistry $types,
        TemplateRenderer $renderer,
        ?ElementorProLocations $elementor_pro = null
    ) {
        $this->compatibility = $compatibility;
        $this->locations     = $locations;
        $this->resolver      = $resolver;
        $this->types         = $types;
        $this->renderer      = $renderer;
        $this->elementor_pro = $elementor_pro ?? new ElementorProLocations();
    }

    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new self(
                CompatibilityManager::instance(),
                LocationRegistry::instance(),
                TemplateResolver::instance(),
                TypeRegistry::instance(),
                new TemplateRenderer()
            );
        }

        return static::$instance;
    }

    /**
     * Register necessary hooks.
     */
    public function register_hooks()
    {
        RequestState::register(self::class, [$this, 'forget_rendered_locations']);
        add_filter('template_include', [$this, 'override_template'], 99);
        add_filter('template_include', [$this, 'record_served_template'], PHP_INT_MAX);
        add_filter('body_class', [$this, 'add_location_body_class']);
        add_action(Hooks::TEMPLATE_ASSETS_COLLECT_ACTION, [$this, 'register_template_assets']);

        $this->compatibility->register_hooks();
    }

    /**
     * Prepares the header, footer, and current location template — bridging
     * their classic/atomic Elementor CSS exactly as register() alone already
     * did, and additionally rendering each once now so its widgets'
     * script/style dependencies are queued before this request's head/footer
     * output deadlines.
     *
     * @param TemplateAssetsRegistrar $registrar
     * @return void
     */
    public function register_template_assets(TemplateAssetsRegistrar $registrar): void
    {
        $canvas = $this->is_canvas_template();

        $placements = [
            'header' => $canvas ? null : $this->get_header_id(),
            'footer' => $canvas ? null : $this->get_footer_id(),
            'location' => $this->get_location_template_id(),
        ];

        foreach ($placements as $placement => $id) {
            if ($id) {
                $registrar->prepare((int) $id, self::ASSET_CONTEXT, $placement);
            }
        }
    }

    /**
     * Override the template if a matched Theme Builder template is found.
     *
     * A page layout other than Default decides what the visitor sees, as in
     * Elementor Pro: the viewed post's own layout wins over the Single
     * template, and a location template set to Canvas is served bare.
     *
     * @param string $template
     * @return string
     */
    public function override_template($template)
    {
        $location_template = (int) $this->get_location_template_id();

        if (!$location_template) {
            return $template;
        }

        $this->served_location = $location_template;

        if ($this->uses_canvas_layout($location_template)) {
            $canvas = $this->canvas_template_for($location_template);
            if (null !== $canvas) {
                return $canvas;
            }
        }

        if ($this->uses_full_width_layout($location_template)) {
            Plugin::instance()->frontend->add_body_class(self::FULL_WIDTH_BODY_CLASS);
        }

        return ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-builder-wrapper.php';
    }

    /**
     * Whether the post being viewed carries a page layout of its own (Canvas,
     * Full Width or a theme page template). Elementor's page-templates module
     * has already chosen the matching file for it, as Pro relies on.
     */
    private function viewed_post_has_own_layout(): bool
    {
        if (!is_singular()) {
            return false;
        }

        $document = Plugin::instance()->documents->get_doc_for_frontend(get_the_ID());
        if (!$document || !$document::get_property('support_wp_page_templates')) {
            return false;
        }

        $layout = (string) $document->get_meta('_wp_page_template');

        return '' !== $layout && 'default' !== $layout;
    }

    /** The Page Layout chosen on a template, '' when none. */
    private function layout_of(int $template_id): string
    {
        return (string) get_post_meta($template_id, '_wp_page_template', true);
    }

    protected function uses_canvas_layout(int $template_id): bool
    {
        return PageTemplates::TEMPLATE_CANVAS === $this->layout_of($template_id);
    }

    protected function uses_full_width_layout(int $template_id): bool
    {
        return PageTemplates::TEMPLATE_HEADER_FOOTER === $this->layout_of($template_id);
    }

    /** Elementor's page-templates module, or null when this Elementor has none. */
    protected function page_templates_module()
    {
        return Plugin::instance()->modules_manager->get_modules('page-templates');
    }

    /**
     * Elementor's own Canvas template, set to print the location template in
     * place of the viewed post, the way Elementor Pro's Theme Builder does it.
     * Null when the page-templates module is not there, so the caller falls
     * back to the wrapper instead of printing a blank page.
     */
    protected function canvas_template_for(int $template_id): ?string
    {
        $module = $this->page_templates_module();
        if (!$module) {
            return null;
        }

        $path = $module->get_template_path(PageTemplates::TEMPLATE_CANVAS);
        if (!$path) {
            return null;
        }

        $module->set_print_callback(function () use ($template_id) {
            $this->render_template($template_id, 'location');
        });

        return $path;
    }

    /**
     * Whether this request is a front-end page Theme Builder may shape at
     * all. An embed, a feed, the admin (AJAX included) and the REST API are
     * served as WordPress serves them.
     */
    public function is_themable_request(): bool
    {
        return !is_admin()
            && !is_embed()
            && !is_feed()
            && !(defined('REST_REQUEST') && REST_REQUEST);
    }

    /**
     * The ID of the content location template (Single/Archive/…) this
     * request is served with, or null when none is. This is the one place
     * that decides it: the template override, early asset preparation and
     * the wrapper view all ask here, so a template is never prepared for a
     * page it is not displayed on.
     *
     * None is served while Elementor edits or previews, on a library item's
     * own URL (a template or popup preview), on a post with a page layout of
     * its own — Elementor's page-templates module already gave it Canvas,
     * Full Width or a theme page template — or where Elementor Pro's Theme
     * Builder serves the location.
     *
     * @return int|null
     */
    public function get_location_template_id()
    {
        if (!array_key_exists('location', $this->display)) {
            $this->display['location'] = $this->resolve_location_template();
        }

        return $this->display['location'];
    }

    private function resolve_location_template(): ?int
    {
        if (
            !$this->is_themable_request()
            || $this->is_elementor_edit_or_preview()
            || is_singular(LibraryPostType::POST_TYPE)
            || $this->viewed_post_has_own_layout()
        ) {
            return null;
        }

        $type = $this->locations->current_type();

        if (null === $type || $this->elementor_pro->serves($type)) {
            return null;
        }

        return $this->resolver->resolve($type);
    }

    private function is_elementor_edit_or_preview(): bool
    {
        $elementor = Plugin::instance();

        return (is_object($elementor->preview) && $elementor->preview->is_preview_mode())
            || (is_object($elementor->editor) && $elementor->editor->is_edit_mode());
    }

    /**
     * Get the Header Template ID.
     *
     * @return int|null
     */
    public function get_header_id()
    {
        return $this->chrome_template_id('header');
    }

    /**
     * Get the Footer Template ID.
     *
     * @return int|null
     */
    public function get_footer_id()
    {
        return $this->chrome_template_id('footer');
    }

    /** The template displayed as $side ('header' or 'footer') on this request, or null. */
    private function chrome_template_id(string $side): ?int
    {
        if (!$this->is_themable_request() || $this->elementor_pro->serves($side)) {
            return null;
        }

        return $this->excluding_edited_template($this->resolver->resolve($side));
    }

    /**
     * A template is never its own header or footer. While one is edited or
     * previewed the request is for that template, and rendering it inside
     * itself is what Elementor's recursion guard refuses ("The Template ID
     * cannot be the same as the currently edited template").
     *
     * Deliberately scoped to a request *for* a library post: only then is
     * get_queried_object_id() a post id at all. On an archive it is a term id,
     * and on an author archive a user id — separate numbering from post ids,
     * so an unscoped comparison would drop the header from any archive whose
     * term merely happened to carry a resolved template's number.
     */
    protected function excluding_edited_template(?int $id): ?int
    {
        if (null === $id || !is_singular(LibraryPostType::POST_TYPE)) {
            return $id;
        }

        return $id === (int) get_queried_object_id() ? null : $id;
    }

    /**
     * Records the template file WordPress finally serves this request with,
     * after every other `template_include` filter has had its say.
     *
     * @param string $template
     * @return string Unchanged.
     */
    public function record_served_template($template)
    {
        $this->served_template = is_string($template) ? $template : null;
        unset($this->display['canvas']);

        return $template;
    }

    /**
     * Whether Elementor is serving this request through its Canvas template.
     * Canvas is a blank page, so — as with Elementor Pro's Theme Builder — no
     * header or footer renders on it. Read from the template file actually
     * served, so it holds however the request reached Canvas (a page
     * template, the Kit default, a location template set to Canvas, or a
     * popup preview) and does not depend on body classes another plugin or
     * theme may filter.
     */
    protected function is_canvas_template(): bool
    {
        if (!array_key_exists('canvas', $this->display)) {
            $this->display['canvas'] = $this->served_template !== null && $this->is_canvas_path($this->served_template);
        }

        return $this->display['canvas'];
    }

    private function is_canvas_path(string $template): bool
    {
        $module = $this->page_templates_module();
        $canvas = $module ? (string) $module->get_template_path(PageTemplates::TEMPLATE_CANVAS) : '';

        return '' !== $canvas && wp_normalize_path($canvas) === wp_normalize_path($template);
    }

    /**
     * Gives the page the location template's `elementor-page-{id}` body
     * class, as Elementor Pro does for its theme page documents, so the
     * template's own page settings (Body Style, Hide Title) and custom CSS
     * written against `selector` apply where it is displayed.
     *
     * @param string[] $classes
     * @return string[]
     */
    public function add_location_body_class($classes)
    {
        if (null !== $this->served_location && is_array($classes)) {
            $classes[] = 'elementor-page-' . $this->served_location;
        }

        return $classes;
    }

    /**
     * A template's content by ID, for $placement ('header', 'footer' or
     * 'location').
     *
     * @param int    $post_id
     * @param string $placement
     * @return string
     */
    public function get_template_content($post_id, string $placement)
    {
        $id = (int) $post_id;

        return $this->renderer->get_prepared($id, self::ASSET_CONTEXT, $placement)
            ?? $this->renderer->prepare($id, self::ASSET_CONTEXT, $placement);
    }

    /**
     * Render a template by ID, for $placement ('header', 'footer' or 'location').
     *
     * @param int    $post_id
     * @param string $placement
     */
    public function render_template($post_id, string $placement)
    {
        // PHPCS - already escaped by Elementor.
        echo $this->get_template_content($post_id, $placement); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Locations already rendered this page, so header/footer output exactly
     * once no matter which compatibility adapter hook fires it. Cleared per
     * page through Core\RequestState.
     *
     * @var array<string, bool>
     */
    protected array $rendered_locations = [];

    /**
     * Per side, whether native chrome is replaced this request; asked once
     * (see replaces_native_chrome()). Cleared per page like the render guard.
     *
     * @var array<string, bool>
     */
    protected array $replaced_sides = [];

    /**
     * This request's display decisions, each made once: 'location' (the
     * served location template id or null) and 'canvas'.
     *
     * @var array<string, int|bool|null>
     */
    protected array $display = [];

    /**
     * The template file served for this request, once known.
     *
     * @var string|null
     */
    protected ?string $served_template = null;

    /**
     * The location template override_template() actually served, if any.
     *
     * @var int|null
     */
    protected ?int $served_location = null;

    /**
     * Sides whose template the active theme wraps in its own header or
     * footer element this request (see keep_theme_landmark()).
     *
     * @var array<string, bool>
     */
    protected array $theme_landmarks = [];

    /**
     * Called by a compatibility adapter that renders the $side template
     * inside the theme's own `<header>`/`<footer>` element (or prints one
     * around it), so the template does not render a second landmark of the
     * same kind inside it.
     *
     * @param string $side 'header' or 'footer'.
     */
    public function keep_theme_landmark(string $side): void
    {
        $this->theme_landmarks[$side] = true;
    }

    /** Whether the active theme provides the landmark element around the $side template. */
    public function keeps_theme_landmark(string $side): bool
    {
        return !empty($this->theme_landmarks[$side]);
    }

    /** Lets the next page render its header and footer again, and decide again what it displays. */
    public function forget_rendered_locations(): void
    {
        $this->rendered_locations = [];
        $this->replaced_sides = [];
        $this->theme_landmarks = [];
        $this->display = [];
        $this->served_template = null;
        $this->served_location = null;
        $this->elementor_pro->forget();
    }

    /**
     * Whether the active theme's own header or footer is replaced on this
     * request: a template applies to $side and the site has not opted out of
     * replacing it. Every compatibility adapter asks this, and only this, so
     * the opt-out behind Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER
     * reaches all of them. The filter is asked at most once per side per
     * request, and not at all for a side with no template.
     *
     * @param string $side 'header' or 'footer'.
     */
    public function replaces_native_chrome(string $side): bool
    {
        if (!isset($this->replaced_sides[$side])) {
            $id = 'header' === $side ? $this->get_header_id() : $this->get_footer_id();
            $this->replaced_sides[$side] = (bool) $id
                && (bool) apply_filters(Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER, true, $side)
                && $this->renders_content((int) $id, $side);
        }

        return $this->replaced_sides[$side];
    }

    /**
     * Whether the $side template renders any content. One that fails to render
     * is prepared as an empty fragment (see TemplateRenderer::prepare()), and
     * replacing the theme's own chrome with nothing would leave the page with
     * neither, so such a template is treated as not displayed.
     *
     * Preparing here caches the fragment the adapter prints later, so the
     * template still renders once.
     */
    private function renders_content(int $id, string $side): bool
    {
        return '' !== trim($this->get_template_content($id, $side));
    }

    /**
     * Render the header template content only (no document wrapper), at
     * most once per request regardless of which hook calls it.
     *
     * @return void
     */
    public function render_header(): void
    {
        if (!empty($this->rendered_locations['header']) || $this->is_canvas_template()) {
            return;
        }

        $id = $this->get_header_id();
        if (!$id) {
            return;
        }

        $this->rendered_locations['header'] = true;
        $this->render_template($id, 'header');
    }

    /**
     * Render the footer template content only (no document wrapper), at
     * most once per request regardless of which hook calls it.
     *
     * @return void
     */
    public function render_footer(): void
    {
        if (!empty($this->rendered_locations['footer']) || $this->is_canvas_template()) {
            return;
        }

        $id = $this->get_footer_id();
        if (!$id) {
            return;
        }

        $this->rendered_locations['footer'] = true;
        $this->render_template($id, 'footer');
    }

    /**
     * The Theme Builder template types an author can create, with how each
     * targets requests and the conditions a new one starts with. Add-ons add
     * a type by registering it in TypeRegistry, the same registry saving
     * validates against.
     *
     * @return array<int, array{value: string, label: string, targeting: string, default_conditions: array}>
     */
    public function get_available_template_types()
    {
        return array_values(array_map(
            static fn ($definition): array => [
                'value'              => $definition->name,
                'label'              => $definition->label,
                'targeting'          => $definition->targeting,
                'default_conditions' => $definition->default_conditions,
            ],
            $this->types->by_group('theme')
        ));
    }
}
