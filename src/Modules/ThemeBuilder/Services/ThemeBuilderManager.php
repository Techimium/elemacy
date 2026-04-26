<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager;
use Elementor\Plugin;

class ThemeBuilderManager
{
    /**
     * @var ThemeBuilderManager
     */
    protected static $instance = null;
    protected static $template_registry = [];

    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * Register necessary hooks.
     */
    public function register_hooks()
    {
        add_filter('template_include', [$this, 'override_template'], 99);

        CompatibilityManager::instance()->register_hooks();
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

        $location_template = $this->get_location_template_id();

        if ($location_template) {
            return ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-builder-wrapper.php';
        }

        return $template;
    }

    /**
     * Get the ID of the template for the current content location (Single/Archive).
     *
     * @return int|null
     */
    public function get_location_template_id()
    {
        if (is_singular()) {
            $post_type = get_post_type();

            $cpt_template = $this->find_template_id("single_{$post_type}");

            if ($cpt_template) {
                return $cpt_template;
            }

            return $this->find_template_id('single');
        }

        if (is_archive() || is_home()) {
            if (function_exists('is_shop') && is_shop()) {
                $product_archive_template = $this->find_template_id('archive_product');

                if ($product_archive_template) {
                    return $product_archive_template;
                }
            }

            if (is_post_type_archive()) {
                $post_type = get_post_type();

                if (is_array($post_type)) {
                    $post_type = reset($post_type);
                }

                $cpt_archive_template = $this->find_template_id("archive_{$post_type}");

                if ($cpt_archive_template) {
                    return $cpt_archive_template;
                }
            }

            if (is_home() || (is_archive() && 'post' === get_post_type())) {
                $post_archive_template = $this->find_template_id('archive_post');

                if ($post_archive_template) {
                    return $post_archive_template;
                }
            }

            return $this->find_template_id('archive');
        }

        if (is_search()) {
            return $this->find_template_id('search');
        }

        if (is_404()) {
            return $this->find_template_id('404');
        }

        return null;
    }

    /**
     * Get the Header Template ID.
     *
     * @return int|null
     */
    public function get_header_id()
    {
        return $this->find_template_id('header');
    }

    /**
     * Get the Footer Template ID.
     *
     * @return int|null
     */
    public function get_footer_id()
    {
        return $this->find_template_id('footer');
    }

    /**
     * Helper to find a template by type.
     * In the future, this will handle conditions.
     *
     * @param string $type
     * @return int|null
     */
    protected function find_template_id($type)
    {
        if (isset(static::$template_registry[$type])) {
            return static::$template_registry[$type]->id ?? null;
        }

        static::$template_registry[$type] = $this->find_template($type);

        return static::$template_registry[$type]->id ?? null;
    }

    protected function find_template($type)
    {
        $template = null;
        $template_service = new TemplateService();
        $templates = $template_service->get_by_type($type);

        if ($templates) {
            // TODO: Implement proper display conditions.
            $template = $templates[0];
        }

        return $template;
    }

    /**
     * Get a template content by ID.
     *
     * @param int $post_id
     */
    public function get_template_content($post_id)
    {
        $elementor = Plugin::instance();

        return $elementor->frontend->get_builder_content_for_display($post_id);
    }

    /**
     * Render a template by ID.
     *
     * @param int $post_id
     */
    public function render_template($post_id)
    {
        // PHPCS - already escaped by Elementor.
        echo $this->get_template_content($post_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Render the header template content only (no document wrapper).
     *
     * @return void
     */
    public function render_header(): void
    {
        $id = $this->get_header_id();
        if (!$id) {
            return;
        }

        $this->render_template($id);
    }

    /**
     * Render the footer template content only (no document wrapper).
     *
     * @return void
     */
    public function render_footer(): void
    {
        $id = $this->get_footer_id();
        if (!$id) {
            return;
        }

        $this->render_template($id);
    }

    /**
     * Get available template types.
     *
     * @return array
     */
    public function get_available_template_types()
    {
        $types = [
            [
                'value' => 'header',
                'label' => __('Header', 'elemacy'),
            ],
            [
                'value' => 'footer',
                'label' => __('Footer', 'elemacy'),
            ],
            [
                'value' => 'single',
                'label' => __('Single', 'elemacy'),
            ],
            [
                'value' => 'archive',
                'label' => __('Archive', 'elemacy'),
            ],
            [
                'value' => 'archive_post',
                'label' => __('Post Archive', 'elemacy'),
            ],
            [
                'value' => '404',
                'label' => __('404 Page', 'elemacy'),
            ],
            [
                'value' => 'search',
                'label' => __('Search Results', 'elemacy'),
            ],
            [
                'value' => 'loop',
                'label' => __('Loop', 'elemacy'),
            ],
        ];

        $post_types = get_post_types(['public' => true], 'objects');

        $exclude_post_types = [
            'elementor_library',
            'e-floating-buttons',
            'elemacy_template',
            'attachment'
        ];

        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, $exclude_post_types, true)) {
                continue;
            }

            $singular_name = $post_type->labels->singular_name ?? $post_type->label;

            $types[] = [
                'value' => "single_{$post_type->name}",
                /* translators: %s: Post type singular name */
                'label' => sprintf(__('Single %s', 'elemacy'), $singular_name)
            ];

            if ($post_type->has_archive) {
                $types[] = [
                    'value' => "archive_{$post_type->name}",
                    /* translators: %s: Post type singular name */
                    'label' => sprintf(__('%s Archive', 'elemacy'), $singular_name)
                ];
            }
        }

        return $types;
    }
}
