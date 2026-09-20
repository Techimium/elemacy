<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Hooks;
use Elemacy\Elementor\Rendering\TemplateAssetsRegistrar;
use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elementor\Plugin;

class ThemeBuilderManager
{
    /**
     * TemplateAssetsRegistrar/TemplateRenderer preparation context for
     * every theme location document.
     */
    public const ASSET_CONTEXT = 'theme-builder';

    /**
     * @var ThemeBuilderManager
     */
    protected static $instance = null;

    protected CompatibilityManager $compatibility;
    protected LocationRegistry $locations;
    protected TemplateResolver $resolver;
    protected TypeRegistry $types;
    protected TemplateRenderer $renderer;

    public function __construct(
        CompatibilityManager $compatibility,
        LocationRegistry $locations,
        TemplateResolver $resolver,
        TypeRegistry $types,
        TemplateRenderer $renderer
    ) {
        $this->compatibility = $compatibility;
        $this->locations     = $locations;
        $this->resolver      = $resolver;
        $this->types         = $types;
        $this->renderer      = $renderer;
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
        add_filter('template_include', [$this, 'override_template'], 99);
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
     * @param string $template
     * @return string
     */
    public function override_template($template)
    {
        if (Plugin::instance()->preview->is_preview_mode() || Plugin::instance()->editor->is_edit_mode()) {
            return $template;
        }

        // A library post viewed directly (template/popup preview) is not site
        // content to wrap in a theme template.
        if (is_singular(LibraryPostType::POST_TYPE)) {
            return $template;
        }

        $location_template = $this->get_location_template_id();

        if ($location_template) {
            return ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-builder-wrapper.php';
        }

        return $template;
    }

    /**
     * Get the ID of the template for the current content location (Single/Archive/…).
     *
     * @return int|null
     */
    public function get_location_template_id()
    {
        $type = $this->locations->current_type();

        return $type ? $this->resolver->resolve($type) : null;
    }

    /**
     * Get the Header Template ID.
     *
     * @return int|null
     */
    public function get_header_id()
    {
        return $this->excluding_edited_template($this->resolver->resolve('header'));
    }

    /**
     * Get the Footer Template ID.
     *
     * @return int|null
     */
    public function get_footer_id()
    {
        return $this->excluding_edited_template($this->resolver->resolve('footer'));
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
     * Whether Elementor is serving this request through its Canvas template.
     * Canvas is a blank page, so — as with Elementor Pro's Theme Builder — no
     * header or footer renders on it. canvas.php tags the body with this class
     * as its first statement, ahead of wp_head() and wp_body_open() (where the
     * generic adapters print the header), and it is present however the request
     * reached Canvas: a page template, the Kit default, or a popup preview.
     */
    protected function is_canvas_template(): bool
    {
        return in_array('elementor-template-canvas', get_body_class(), true);
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
     * Request-local locations already rendered, so header/footer output
     * exactly once no matter which compatibility adapter hook fires it —
     * one instance per request; never shared across sites or preview scopes.
     *
     * @var array<string, bool>
     */
    protected array $rendered_locations = [];

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
     * Get available template types.
     *
     * @return array
     */
    public function get_available_template_types()
    {
        $types = array_map(
            static fn ($definition): array => [
                'value' => $definition->name,
                'label' => $definition->label,
            ],
            $this->types->by_group('theme')
        );

        return apply_filters(Hooks::THEME_BUILDER_TEMPLATE_TYPES_FILTER, $types);
    }
}
