<?php

namespace Elemacy\Modules\Widgets\Widgets\NavMenu\Controls;

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elemacy\Modules\Widgets\Widgets\NavMenu;
use Elemacy\Modules\Widgets\Widgets\NavMenu\BreakpointStyles;
use Elemacy\Modules\Widgets\Widgets\NavMenu\Css;
use Elemacy\Modules\Widgets\Widgets\NavMenu\MenuOptions;

/** Content > Layout: which menu, how it is laid out, and how it collapses on small screens. */
final class LayoutSection
{
    public static function register(NavMenu $widget): void
    {
        $widget->start_controls_section('section_layout', [
            'label' => esc_html__('Layout', 'elemacy'),
        ]);

        self::register_menu($widget);
        self::register_layout($widget);
        self::register_pointer($widget);
        self::register_submenu_indicator($widget);
        self::register_mobile_dropdown($widget);
        self::register_toggle($widget);

        $widget->end_controls_section();
    }

    private static function register_menu(NavMenu $widget): void
    {
        $widget->add_control('menu_name', [
            'label'   => esc_html__('Menu Name', 'elemacy'),
            'type'    => Controls_Manager::TEXT,
            'default' => esc_html__('Menu', 'elemacy'),
        ]);

        $menus = MenuOptions::all();

        if (!$menus) {
            $widget->add_control('menu', [
                'type'       => Controls_Manager::ALERT,
                'alert_type' => 'info',
                'heading'    => esc_html__('There are no menus in your site.', 'elemacy'),
                'content'    => sprintf(
                    /* translators: 1: Link opening tag, 2: Link closing tag. */
                    esc_html__('Go to the %1$sMenus screen%2$s to create one.', 'elemacy'),
                    sprintf('<a href="%s" target="_blank">', esc_url(admin_url('nav-menus.php?action=edit&menu=0'))),
                    '</a>'
                ),
                'separator'  => 'after',
            ]);

            return;
        }

        $widget->add_control('menu', [
            'label'        => esc_html__('Menu', 'elemacy'),
            'type'         => Controls_Manager::SELECT,
            'options'      => $menus,
            'default'      => array_keys($menus)[0],
            'save_default' => true,
            'separator'    => 'after',
            'description'  => sprintf(
                /* translators: 1: Link opening tag, 2: Link closing tag. */
                esc_html__('Go to the %1$sMenus screen%2$s to manage your menus.', 'elemacy'),
                sprintf('<a href="%s" target="_blank">', esc_url(admin_url('nav-menus.php'))),
                '</a>'
            ),
        ]);
    }

    private static function register_layout(NavMenu $widget): void
    {
        $widget->add_control('layout', [
            'label'              => esc_html__('Layout', 'elemacy'),
            'type'               => Controls_Manager::SELECT,
            'default'            => NavMenu::LAYOUT_HORIZONTAL,
            'options'            => [
                NavMenu::LAYOUT_HORIZONTAL => esc_html__('Horizontal', 'elemacy'),
                NavMenu::LAYOUT_VERTICAL   => esc_html__('Vertical', 'elemacy'),
                NavMenu::LAYOUT_DROPDOWN   => esc_html__('Dropdown', 'elemacy'),
            ],
            'frontend_available' => true,
        ]);

        $start = is_rtl() ? 'end' : 'start';
        $end = is_rtl() ? 'start' : 'end';

        $widget->add_control('align_items', [
            'label'        => esc_html__('Alignment', 'elemacy'),
            'type'         => Controls_Manager::CHOOSE,
            'options'      => [
                'start'   => [
                    'title' => esc_html__('Start', 'elemacy'),
                    'icon'  => "eicon-align-$start-h",
                ],
                'center'  => [
                    'title' => esc_html__('Center', 'elemacy'),
                    'icon'  => 'eicon-align-center-h',
                ],
                'end'     => [
                    'title' => esc_html__('End', 'elemacy'),
                    'icon'  => "eicon-align-$end-h",
                ],
                'justify' => [
                    'title' => esc_html__('Stretch', 'elemacy'),
                    'icon'  => 'eicon-align-stretch-h',
                ],
            ],
            'prefix_class' => Css::WRAPPER . 'align-',
            'condition'    => ['layout!' => NavMenu::LAYOUT_DROPDOWN],
        ]);
    }

    private static function register_pointer(NavMenu $widget): void
    {
        $widget->add_control('pointer', [
            'label'          => esc_html__('Pointer', 'elemacy'),
            'type'           => Controls_Manager::SELECT,
            'default'        => 'underline',
            'options'        => [
                'none'        => esc_html__('None', 'elemacy'),
                'underline'   => esc_html__('Underline', 'elemacy'),
                'overline'    => esc_html__('Overline', 'elemacy'),
                'double-line' => esc_html__('Double Line', 'elemacy'),
                'framed'      => esc_html__('Framed', 'elemacy'),
                'background'  => esc_html__('Background', 'elemacy'),
                'text'        => esc_html__('Text', 'elemacy'),
            ],
            'style_transfer' => true,
            'condition'      => ['layout!' => NavMenu::LAYOUT_DROPDOWN],
        ]);

        // One Animation control per pointer family; NavMenu::POINTER_ANIMATIONS maps pointers to them.
        $animations = [
            'animation_line'       => [
                'pointers' => ['underline', 'overline', 'double-line'],
                'default'  => 'fade',
                'options'  => [
                    'fade'     => 'Fade',
                    'slide'    => 'Slide',
                    'grow'     => 'Grow',
                    'drop-in'  => 'Drop In',
                    'drop-out' => 'Drop Out',
                    'none'     => 'None',
                ],
            ],
            'animation_framed'     => [
                'pointers' => 'framed',
                'default'  => 'fade',
                'options'  => [
                    'fade'    => 'Fade',
                    'grow'    => 'Grow',
                    'shrink'  => 'Shrink',
                    'draw'    => 'Draw',
                    'corners' => 'Corners',
                    'none'    => 'None',
                ],
            ],
            'animation_background' => [
                'pointers' => 'background',
                'default'  => 'fade',
                'options'  => [
                    'fade'                   => 'Fade',
                    'grow'                   => 'Grow',
                    'shrink'                 => 'Shrink',
                    'sweep-left'             => 'Sweep Left',
                    'sweep-right'            => 'Sweep Right',
                    'sweep-up'               => 'Sweep Up',
                    'sweep-down'             => 'Sweep Down',
                    'shutter-in-vertical'    => 'Shutter In Vertical',
                    'shutter-out-vertical'   => 'Shutter Out Vertical',
                    'shutter-in-horizontal'  => 'Shutter In Horizontal',
                    'shutter-out-horizontal' => 'Shutter Out Horizontal',
                    'none'                   => 'None',
                ],
            ],
            'animation_text'       => [
                'pointers' => 'text',
                'default'  => 'grow',
                'options'  => [
                    'grow'   => 'Grow',
                    'shrink' => 'Shrink',
                    'sink'   => 'Sink',
                    'float'  => 'Float',
                    'skew'   => 'Skew',
                    'rotate' => 'Rotate',
                    'none'   => 'None',
                ],
            ],
        ];

        foreach ($animations as $id => $animation) {
            $widget->add_control($id, [
                'label'     => esc_html__('Animation', 'elemacy'),
                'type'      => Controls_Manager::SELECT,
                'default'   => $animation['default'],
                'options'   => $animation['options'],
                'condition' => [
                    'layout!' => NavMenu::LAYOUT_DROPDOWN,
                    'pointer' => $animation['pointers'],
                ],
            ]);
        }
    }

    private static function register_submenu_indicator(NavMenu $widget): void
    {
        $widget->add_control('submenu_icon', [
            'label'                  => esc_html__('Submenu Indicator', 'elemacy'),
            'type'                   => Controls_Manager::ICONS,
            'separator'              => 'before',
            'default'                => [
                'value'   => (Icons_Manager::is_migration_allowed() ? 'fas ' : 'fa ') . 'fa-caret-down',
                'library' => 'fa-solid',
            ],
            'recommended'            => [
                'fa-solid' => ['chevron-down', 'angle-down', 'caret-down', 'plus'],
            ],
            'label_block'            => false,
            'skin'                   => 'inline',
            'exclude_inline_options' => ['svg'],
            'frontend_available'     => true,
        ]);
    }

    private static function register_mobile_dropdown(NavMenu $widget): void
    {
        $widget->add_control('heading_mobile_dropdown', [
            'label'     => esc_html__('Mobile Dropdown', 'elemacy'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['layout!' => NavMenu::LAYOUT_DROPDOWN],
        ]);

        $breakpoints = [];

        foreach (BreakpointStyles::breakpoints() as $key => $breakpoint) {
            $breakpoints[$key] = sprintf(
                /* translators: 1: Breakpoint label, 2: Comparison operator, 3: Breakpoint value in pixels. */
                esc_html__('%1$s (%2$s %3$dpx)', 'elemacy'),
                $breakpoint['label'],
                '>',
                $breakpoint['value']
            );
        }

        $breakpoints[NavMenu::BREAKPOINT_NONE] = esc_html__('None', 'elemacy');

        $widget->add_control('dropdown', [
            'label'        => esc_html__('Breakpoint', 'elemacy'),
            'type'         => Controls_Manager::SELECT,
            'default'      => 'tablet',
            'options'      => $breakpoints,
            'prefix_class' => Css::WRAPPER . 'breakpoint-',
            'condition'    => ['layout!' => NavMenu::LAYOUT_DROPDOWN],
        ]);

        $widget->add_control('full_width', [
            'label'              => esc_html__('Full Width', 'elemacy'),
            'type'               => Controls_Manager::SWITCHER,
            'description'        => esc_html__('Stretch the dropdown of the menu to full width.', 'elemacy'),
            'prefix_class'       => Css::WRAPPER,
            'return_value'       => 'stretch',
            'frontend_available' => true,
            'condition'          => ['dropdown!' => NavMenu::BREAKPOINT_NONE],
        ]);

        $widget->add_control('text_align', [
            'label'        => esc_html__('Text Align', 'elemacy'),
            'type'         => Controls_Manager::SELECT,
            'default'      => 'aside',
            'options'      => [
                'aside'  => esc_html__('Aside', 'elemacy'),
                'center' => esc_html__('Center', 'elemacy'),
            ],
            'prefix_class' => Css::WRAPPER . 'text-align-',
            'condition'    => ['dropdown!' => NavMenu::BREAKPOINT_NONE],
        ]);
    }

    private static function register_toggle(NavMenu $widget): void
    {
        $widget->add_control('toggle', [
            'label'              => esc_html__('Toggle Button', 'elemacy'),
            'type'               => Controls_Manager::SELECT,
            'default'            => NavMenu::TOGGLE_BURGER,
            'options'            => [
                ''                     => esc_html__('None', 'elemacy'),
                NavMenu::TOGGLE_BURGER => esc_html__('Hamburger', 'elemacy'),
            ],
            'prefix_class'       => Css::WRAPPER . 'toggle ' . Css::WRAPPER,
            'render_type'        => 'template',
            'frontend_available' => true,
            'condition'          => ['dropdown!' => NavMenu::BREAKPOINT_NONE],
        ]);

        $burger = ['toggle' => NavMenu::TOGGLE_BURGER];

        $widget->start_controls_tabs('nav_icon_options');

        $widget->start_controls_tab('nav_icon_normal_options', [
            'label'     => esc_html__('Normal', 'elemacy'),
            'condition' => $burger,
        ]);

        $widget->add_control('toggle_icon_normal', self::toggle_icon_args('eicon-menu-bar', [
            'fa-solid'   => ['plus-square', 'plus', 'plus-circle', 'bars'],
            'fa-regular' => ['plus-square'],
        ]));

        $widget->end_controls_tab();

        $widget->start_controls_tab('nav_icon_hover_options', [
            'label'     => esc_html__('Hover', 'elemacy'),
            'condition' => $burger,
        ]);

        $widget->add_control('toggle_icon_hover_animation', [
            'label'              => esc_html__('Hover Animation', 'elemacy'),
            'type'               => Controls_Manager::HOVER_ANIMATION,
            'frontend_available' => true,
            'render_type'        => 'template',
            'condition'          => $burger,
        ]);

        $widget->end_controls_tab();

        $widget->start_controls_tab('nav_icon_active_options', [
            'label'     => esc_html__('Active', 'elemacy'),
            'condition' => $burger,
        ]);

        $widget->add_control('toggle_icon_active', self::toggle_icon_args('eicon-close', [
            'fa-solid'   => ['window-close', 'times-circle', 'times', 'minus-square', 'minus-circle', 'minus'],
            'fa-regular' => ['window-close', 'times-circle', 'minus-square'],
        ]));

        $widget->end_controls_tab();

        $widget->end_controls_tabs();

        $widget->add_control('toggle_align', [
            'label'                => esc_html__('Toggle Align', 'elemacy'),
            'type'                 => Controls_Manager::CHOOSE,
            'default'              => 'center',
            'options'              => [
                'left'   => [
                    'title' => esc_html__('Left', 'elemacy'),
                    'icon'  => 'eicon-h-align-left',
                ],
                'center' => [
                    'title' => esc_html__('Center', 'elemacy'),
                    'icon'  => 'eicon-h-align-center',
                ],
                'right'  => [
                    'title' => esc_html__('Right', 'elemacy'),
                    'icon'  => 'eicon-h-align-right',
                ],
            ],
            'selectors_dictionary' => [
                'left'   => 'margin-right: auto',
                'center' => 'margin: 0 auto',
                'right'  => 'margin-left: auto',
            ],
            'selectors'            => [
                '{{WRAPPER}} .elemacy-nav-menu__toggle' => '{{VALUE}}',
            ],
            'condition'            => [
                'toggle!'   => '',
                'dropdown!' => NavMenu::BREAKPOINT_NONE,
            ],
            'separator'            => 'before',
        ]);
    }

    /** A toggle icon picker whose inline "Default" option is the given Elementor icon. */
    private static function toggle_icon_args(string $default_icon, array $recommended): array
    {
        return [
            'label'            => esc_html__('Icon', 'elemacy'),
            'type'             => Controls_Manager::ICONS,
            'fa4compatibility' => 'icon',
            'skin'             => 'inline',
            'label_block'      => false,
            'skin_settings'    => [
                'inline' => [
                    'none' => [
                        'label' => esc_html__('Default', 'elemacy'),
                        'icon'  => $default_icon,
                    ],
                    'icon' => [
                        'icon' => 'eicon-star',
                    ],
                ],
            ],
            'recommended'      => $recommended,
            'condition'        => ['toggle' => NavMenu::TOGGLE_BURGER],
        ];
    }
}
