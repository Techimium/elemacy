<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

use Elemacy\Modules\ThemeBuilder\PostTypes\TemplatePostType;
use Elementor\Plugin;

class ThemeBuilderManager
{
    /**
     * @var ThemeBuilderManager
     */
    private static $instance = null;
    private static $template_registry = [];

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register necessary hooks.
     */
    public function register_hooks()
    {
        add_filter('template_include', [$this, 'override_template'], 99);
        add_action('get_header', [$this, 'get_header']);
        add_action('get_footer', [$this, 'get_footer']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_template_assets']);
    }

    /**
     * Override the template if a matched Theme Builder template is found.
     *
     * @param string $template
     * @return string
     */
    public function override_template($template)
    {
        if (is_singular(TemplatePostType::POST_TYPE) && Plugin::$instance->editor->is_edit_mode()) {
            // todo implement for header/footer
            return ELEMENTOR_PATH . '/modules/page-templates/templates/canvas.php';
        }

        $location_template = $this->get_location_template_id();

        if ($location_template) {
            return ELEMACY_PATH . 'src/Modules/ThemeBuilder/views/theme-builder-wrapper.php';
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
            return $this->find_template_id('single');
        } elseif (is_archive() || is_home() || is_search() || is_404()) {
            return $this->find_template_id('archive');
        }
        //todo implement for custom post types and more

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
        if (isset(self::$template_registry[$type])) {
            return self::$template_registry[$type]->id ?? null;
        }

        self::$template_registry[$type] = $this->find_template($type);

        return self::$template_registry[$type]->id ?? null;
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
        echo wp_kses_post($this->get_template_content($post_id));
    }

    /**
     * Get the Header Template.
     *
     * @param string $name
     * @return void
     */
    public function get_header($name = '')
    {
        if (!$this->get_header_id()) {
            return;
        }

        require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-support-header.php';

        $templates = [];
        $name = (string) $name;
        if ('' !== $name) {
            $templates[] = "header-{$name}.php";
        }

        $templates[] = 'header.php';

        remove_all_actions('wp_head');
        ob_start();
        locate_template($templates, true);
        ob_get_clean();
    }

    /**
     * Get the Footer Template.
     *
     * @param string $name
     * @return void
     */
    public function get_footer($name = '')
    {
        if (!$this->get_footer_id()) {
            return;
        }

        require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-support-footer.php';

        $templates = [];
        $name = (string) $name;
        if ('' !== $name) {
            $templates[] = "footer-{$name}.php";
        }

        $templates[] = 'footer.php';

        ob_start();
        locate_template($templates, true);
        ob_get_clean();
    }

    /**
     * Enqueue template assets.
     * 
     * @return void
     */
    public function enqueue_template_assets()
    {
        $template_ids = [
            $this->get_header_id(),
            $this->get_footer_id(),
            $this->get_location_template_id(),
        ];

        foreach ($template_ids as $id) {
            if ($id) {
                $css_file = new \Elementor\Core\Files\CSS\Post($id);
                $css_file->enqueue();
            }
        }
    }
}
