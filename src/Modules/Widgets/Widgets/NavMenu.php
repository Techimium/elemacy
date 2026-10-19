<?php

namespace Elemacy\Modules\Widgets\Widgets;

defined('ABSPATH') || exit;

use Elementor\Plugin;
use Elemacy\Elementor\Widgets\BaseWidget;
use Elemacy\Modules\Widgets\Widgets\NavMenu\Controls\DropdownStyleSection;
use Elemacy\Modules\Widgets\Widgets\NavMenu\Controls\LayoutSection;
use Elemacy\Modules\Widgets\Widgets\NavMenu\Controls\MainMenuStyleSection;
use Elemacy\Modules\Widgets\Widgets\NavMenu\Controls\ToggleStyleSection;
use Elemacy\Modules\Widgets\Widgets\NavMenu\MenuRenderer;

/**
 * A WordPress menu with the controls, responsive dropdown and look of the
 * Elementor Pro Nav Menu, requiring only Elementor. Control IDs match the
 * reference widget's, so builders find the same panel and Paste Style works
 * between the two; everything that reaches the page is Elemacy's own.
 */
class NavMenu extends BaseWidget
{
    public const LAYOUT_HORIZONTAL = 'horizontal';
    public const LAYOUT_VERTICAL = 'vertical';
    public const LAYOUT_DROPDOWN = 'dropdown';

    public const BREAKPOINT_NONE = 'none';
    public const TOGGLE_BURGER = 'burger';

    /** The Animation control that belongs to each pointer. */
    public const POINTER_ANIMATIONS = [
        'underline'   => 'animation_line',
        'overline'    => 'animation_line',
        'double-line' => 'animation_line',
        'framed'      => 'animation_framed',
        'background'  => 'animation_background',
        'text'        => 'animation_text',
    ];

    public function get_name(): string
    {
        return 'elemacy_nav_menu';
    }

    public function get_title(): string
    {
        return esc_html__('Elemacy Nav Menu', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'eicon-nav-menu';
    }

    public function get_keywords(): array
    {
        return ['menu', 'nav', 'button', 'nav menu'];
    }

    public function get_script_depends(): array
    {
        return ['elemacy-nav-menu'];
    }

    public function get_style_depends(): array
    {
        return ['elemacy-nav-menu'];
    }

    public function has_widget_inner_wrapper(): bool
    {
        $experiments = Plugin::$instance->experiments ?? null;

        return !(is_object($experiments) && $experiments->is_feature_active('e_optimized_markup'));
    }

    protected function register_controls(): void
    {
        LayoutSection::register($this);
        MainMenuStyleSection::register($this);
        DropdownStyleSection::register($this);
        ToggleStyleSection::register($this);
    }

    protected function render(): void
    {
        (new MenuRenderer($this, $this->get_active_settings()))->render();
    }

    public function render_plain_content()
    {
    }

    /** Menus are exported by term ID, which the import maps back to the imported menu. */
    public function on_export($element)
    {
        $menu = wp_get_nav_menu_object($element['settings']['menu'] ?? '');

        if (!$menu instanceof \WP_Term) {
            unset($element['settings']['menu']);

            return $element;
        }

        $element['settings']['menu_id'] = $menu->term_id;

        return $element;
    }

    /**
     * An imported menu may get a new slug (for example "-duplicate" when the
     * site already has the slug), so the widget follows the menu's new term ID.
     */
    public static function on_import_update_dynamic_content(array $config, array $data, $controls = null): array
    {
        $old_menu_id = $config['settings']['menu_id'] ?? 0;

        if (empty($old_menu_id)) {
            return $config;
        }

        $new_menu = wp_get_nav_menu_object($data['term_ids'][$old_menu_id] ?? 0);

        if ($new_menu instanceof \WP_Term) {
            $config['settings']['menu'] = $new_menu->slug;
        }

        unset($config['settings']['menu_id']);

        return $config;
    }
}
