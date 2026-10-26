<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu;

defined('ABSPATH') || exit;

use Elementor\Icons_Manager;
use Elementor\Plugin;
use Elemacy\Modules\Widgets\Widgets\NavMenu;

/**
 * Prints one Nav Menu: the desktop `<nav>`, the toggle, and the mobile dropdown
 * `<nav>`, each only when the settings can ever show it.
 */
final class MenuRenderer
{
    /**
     * Request-wide, so the same widget rendered twice on a page still gets unique menu IDs.
     *
     * @var int
     */
    private static int $menu_index = 0;

    private NavMenu $widget;

    /**
     * The widget's active settings.
     *
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(NavMenu $widget, array $settings)
    {
        $this->widget = $widget;
        $this->settings = $settings;
    }

    public function render(): void
    {
        $slug = (string) ($this->settings['menu'] ?? '');

        // A deleted menu renders nothing, rather than WordPress's fallback to another menu.
        if ($slug === '' || !wp_get_nav_menu_object($slug)) {
            return;
        }

        $layout = $this->settings['layout'] ?? NavMenu::LAYOUT_HORIZONTAL;
        $has_main = $layout !== NavMenu::LAYOUT_DROPDOWN;
        $has_toggle = ($this->settings['toggle'] ?? '') === NavMenu::TOGGLE_BURGER;
        $has_dropdown = !$has_main || ($this->settings['dropdown'] ?? NavMenu::BREAKPOINT_NONE) !== NavMenu::BREAKPOINT_NONE;
        $indicator = $this->indicator();

        $main = $has_main ? $this->menu($slug, new RenderContext(false, $indicator)) : '';
        $dropdown = $has_dropdown ? $this->menu($slug, new RenderContext($has_toggle, $indicator)) : '';

        if ($main === '' && $dropdown === '') {
            return;
        }

        if ($main !== '') {
            $this->print_main($main);
        }

        if ($dropdown !== '') {
            if ($has_toggle) {
                $this->print_toggle();
            }

            $this->print_dropdown($dropdown, $has_toggle);
        }
    }

    private function menu(string $slug, RenderContext $context): string
    {
        return MenuMarkup::render([
            'menu'        => $slug,
            'menu_class'  => Css::LIST,
            'menu_id'     => 'elemacy-menu-' . (++self::$menu_index) . '-' . $this->widget->get_id(),
            'fallback_cb' => '__return_empty_string',
            'container'   => '',
        ], $context);
    }

    private function print_main(string $menu): void
    {
        $layout = $this->settings['layout'];
        $pointer = (string) ($this->settings['pointer'] ?? '');
        $classes = [Css::MAIN, Css::CONTAINER, Css::main($layout)];

        if ($pointer !== '') {
            $classes[] = Css::main('pointer-' . $pointer);

            $animation_control = NavMenu::POINTER_ANIMATIONS[$pointer] ?? '';

            if (!empty($this->settings[$animation_control])) {
                $classes[] = Css::main('animation-' . $this->settings[$animation_control]);
            }
        }

        $this->widget->add_render_attribute('main-menu', 'class', $classes);

        if (!empty($this->settings['menu_name'])) {
            $this->widget->add_render_attribute('main-menu', 'aria-label', $this->settings['menu_name']);
        }

        ?>
        <nav <?php $this->widget->print_render_attribute_string('main-menu'); ?>>
            <?php echo $menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by wp_nav_menu(). ?>
        </nav>
        <?php
    }

    private function print_toggle(): void
    {
        $this->widget->add_render_attribute('menu-toggle', [
            'class'         => Css::TOGGLE,
            'role'          => 'button',
            'tabindex'      => '0',
            'aria-label'    => esc_attr__('Menu Toggle', 'elemacy'),
            'aria-expanded' => 'false',
        ]);

        if ($this->is_edit_mode()) {
            $this->widget->add_render_attribute('menu-toggle', 'class', 'elementor-clickable');
        }

        $animation = empty($this->settings['toggle_icon_hover_animation'])
            ? ''
            : ' elementor-animation-' . $this->settings['toggle_icon_hover_animation'];

        ?>
        <div <?php $this->widget->print_render_attribute_string('menu-toggle'); ?>>
            <?php
            $this->print_toggle_icon('toggle_icon_normal', 'eicon-menu-bar', Css::TOGGLE_ICON_OPEN . $animation);
            $this->print_toggle_icon('toggle_icon_active', 'eicon-close', Css::TOGGLE_ICON_CLOSE . $animation);
            ?>
        </div>
        <?php
    }

    /** Elementor's own icon when none is chosen. An uploaded SVG has no class of its own, so it is wrapped. */
    private function print_toggle_icon(string $setting, string $fallback_icon, string $icon_class): void
    {
        $icon = !empty($this->settings[$setting]['value'])
            ? $this->settings[$setting]
            : [
                'library' => 'eicons',
                'value' => $fallback_icon,
            ];

        $is_svg = $icon['library'] === 'svg';

        if ($is_svg) {
            echo '<span class="' . esc_attr($icon_class) . '">';
        }

        Icons_Manager::render_icon($icon, [
            'aria-hidden' => 'true',
            'role'        => 'presentation',
            'class'       => $icon_class,
        ]);

        if ($is_svg) {
            echo '</span>';
        }
    }

    /**
     * Behind a toggle the dropdown starts closed and hidden from assistive
     * technology. Without one it is always shown below the breakpoint, so it
     * stays readable and reachable.
     */
    private function print_dropdown(string $menu, bool $collapsed): void
    {
        $this->widget->add_render_attribute('dropdown-menu', 'class', [Css::DROPDOWN, Css::CONTAINER]);

        if ($collapsed) {
            $this->widget->add_render_attribute('dropdown-menu', 'aria-hidden', 'true');
        }

        ?>
        <nav <?php $this->widget->print_render_attribute_string('dropdown-menu'); ?>>
            <?php echo $menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by wp_nav_menu(). ?>
        </nav>
        <?php
    }

    /**
     * Submenu indicator markup, or '' for none. Font icons only: the control
     * excludes SVG uploads. Loads the icon library's font when Elementor is not
     * inlining font icons as SVG.
     */
    private function indicator(): string
    {
        $icon = $this->settings['submenu_icon'] ?? [];
        $value = is_array($icon) ? (string) ($icon['value'] ?? '') : '';

        if ($value === '') {
            return '';
        }

        if (strpos($value, 'fa ') === 0 && Icons_Manager::is_migration_allowed()) {
            $value = 'fas ' . substr($value, 3);
            $icon['value'] = $value;
        }

        if ($this->is_font_icon_svg()) {
            $attributes = ['aria-hidden' => 'true'];

            // Rendered smaller, as the reference does for its chevron.
            if (strpos($value, 'chevron-down') !== false) {
                $attributes['class'] = Css::INDICATOR_CHEVRON;
            }

            return (string) Icons_Manager::render_font_icon($icon, $attributes);
        }

        $this->enqueue_icon_library((string) ($icon['library'] ?? ''));

        return sprintf('<i class="%s" aria-hidden="true"></i>', esc_attr($value));
    }

    private function enqueue_icon_library(string $library): void
    {
        $tabs = Icons_Manager::get_icon_manager_tabs();

        if ($library === '' || !isset($tabs[$library]['name'])) {
            return;
        }

        $handle = 'elementor-icons-' . $tabs[$library]['name'];

        if (wp_style_is($handle, 'registered')) {
            wp_enqueue_style($handle);
        }
    }

    private function is_font_icon_svg(): bool
    {
        $experiments = Plugin::$instance->experiments ?? null;

        return is_object($experiments) && $experiments->is_feature_active('e_font_icon_svg');
    }

    private function is_edit_mode(): bool
    {
        $editor = Plugin::$instance->editor ?? null;

        return is_object($editor) && $editor->is_edit_mode();
    }
}
