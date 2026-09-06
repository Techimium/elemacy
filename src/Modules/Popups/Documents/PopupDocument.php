<?php

namespace Elemacy\Modules\Popups\Documents;

defined('ABSPATH') || exit;

use Elemacy\TemplateLibrary\Constants\MetaKeys;
use Elemacy\Modules\Popups\Support\DisplayDefaults;
use Elemacy\Modules\Popups\Support\DisplayKeys;
use Elemacy\Modules\Popups\Support\PopupTypes;
use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\PageBase;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Modules\PageTemplates\Module as PageTemplatesModule;

/**
 * Custom Elementor document type for popups. Extends free-core PageBase (no
 * Elementor Pro needed) so popups edit on a real CPT post with page controls,
 * forced onto the Canvas template (no theme header/footer).
 */
class PopupDocument extends PageBase
{
    public static function get_type()
    {
        return 'elemacy_popup';
    }

    public function get_name()
    {
        return 'elemacy_popup';
    }

    public static function get_title()
    {
        return esc_html__('Popup', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Popups', 'elemacy');
    }

    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['support_kit']     = true;
        $properties['show_in_library'] = false;
        $properties['admin_tab_group'] = '';

        return $properties;
    }

    // Force Canvas so a freshly-created popup opens blank before _wp_page_template is persisted.
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $template = get_post_meta($data['post_id'], '_wp_page_template', true);

            if (empty($template) || 'default' === $template) {
                $template = PageTemplatesModule::TEMPLATE_CANVAS;
            }

            $data['settings']['template'] = $template;
        }

        parent::__construct($data);
    }

    /**
     * Scope settings CSS to `.elementor-{id}` (the popup box), not PageBase's
     * default `body.elementor-page-{id}`: that element exists in both the editor
     * canvas and the frontend footer injection, so controls preview and apply.
     */
    public function get_css_wrapper_selector()
    {
        return '.elementor-' . $this->get_main_id();
    }

    protected function register_controls()
    {
        $type     = get_post_meta($this->get_main_id(), MetaKeys::TEMPLATE_TYPE, true);
        $type     = $type ? $type : PopupTypes::POPUP;
        $defaults = DisplayDefaults::for_type($type);

        $this->register_layout_controls($type, $defaults);
        $this->register_close_style_controls();
    }

    protected function position_options(string $type): array
    {
        if (PopupTypes::TOPBAR === $type) {
            return [
                'top'    => esc_html__('Top', 'elemacy'),
                'bottom' => esc_html__('Bottom', 'elemacy'),
            ];
        }

        return [
            'center'       => esc_html__('Center', 'elemacy'),
            'top'          => esc_html__('Top', 'elemacy'),
            'bottom'       => esc_html__('Bottom', 'elemacy'),
            'top-left'     => esc_html__('Top Left', 'elemacy'),
            'top-right'    => esc_html__('Top Right', 'elemacy'),
            'bottom-left'  => esc_html__('Bottom Left', 'elemacy'),
            'bottom-right' => esc_html__('Bottom Right', 'elemacy'),
            'custom'       => esc_html__('Custom', 'elemacy'),
        ];
    }

    protected function register_layout_controls(string $type, array $defaults)
    {
        // Overlay, scroll-lock and the dialog-style close behaviors only apply to
        // the centered modal popup; topbar/banner/floating are non-modal.
        $is_modal = PopupTypes::POPUP === $type;

        $this->start_controls_section(
            'elemacy_popup_section',
            [
                'label' => esc_html__('Popup', 'elemacy'),
                'tab'   => Controls_Manager::TAB_SETTINGS,
            ]
        );

        // Position/overlay write CSS vars to `body` only for the editor preview;
        // the frontend ignores them and positions each popup off its own class.
        $this->add_control(
            DisplayKeys::POSITION,
            [
                'label'                => esc_html__('Position', 'elemacy'),
                'type'                 => Controls_Manager::SELECT,
                'default'              => $defaults['position'],
                'options'              => $this->position_options($type),
                'selectors_dictionary' => [
                    'center'       => '--elemacy-align:center;--elemacy-justify:center;',
                    'top'          => '--elemacy-align:flex-start;--elemacy-justify:center;',
                    'bottom'       => '--elemacy-align:flex-end;--elemacy-justify:center;',
                    'top-left'     => '--elemacy-align:flex-start;--elemacy-justify:flex-start;',
                    'top-right'    => '--elemacy-align:flex-start;--elemacy-justify:flex-end;',
                    'bottom-left'  => '--elemacy-align:flex-end;--elemacy-justify:flex-start;',
                    'bottom-right' => '--elemacy-align:flex-end;--elemacy-justify:flex-end;',
                    'custom'       => '--elemacy-align:flex-start;--elemacy-justify:flex-start;',
                ],
                'selectors'            => [
                    'body' => '{{VALUE}}',
                ],
            ]
        );

        if (PopupTypes::TOPBAR !== $type) {
            $this->add_control(
                'elemacy_position_custom_notice',
                [
                    'type'        => Controls_Manager::ALERT,
                    'alert_type'  => 'warning',
                    'heading'     => esc_html__('Please note!', 'elemacy'),
                    'content'     => esc_html__('Custom positioning is not recommended for responsive layouts. Use sparingly, and check how it looks on smaller screens.', 'elemacy'),
                    'render_type' => 'ui',
                    'condition'   => [
                        DisplayKeys::POSITION => 'custom',
                    ],
                ]
            );

            $this->add_responsive_control(
                DisplayKeys::OFFSET_X,
                [
                    'label'      => esc_html__('Offset X', 'elemacy'),
                    'type'       => Controls_Manager::SLIDER,
                    'size_units' => ['px', '%'],
                    'range'      => [
                        'px' => [
                            'min' => -1000,
                            'max' => 1000,
                        ],
                        '%'  => [
                            'min' => -100,
                            'max' => 100,
                        ],
                    ],
                    'default'    => [
                        'size' => 0,
                        'unit' => 'px',
                    ],
                    'condition'  => [
                        DisplayKeys::POSITION => 'custom',
                    ],
                    'selectors'  => [
                        '{{WRAPPER}}' => 'left: {{SIZE}}{{UNIT}};',
                    ],
                ]
            );

            $this->add_responsive_control(
                DisplayKeys::OFFSET_Y,
                [
                    'label'      => esc_html__('Offset Y', 'elemacy'),
                    'type'       => Controls_Manager::SLIDER,
                    'size_units' => ['px', '%'],
                    'range'      => [
                        'px' => [
                            'min' => -1000,
                            'max' => 1000,
                        ],
                        '%'  => [
                            'min' => -100,
                            'max' => 100,
                        ],
                    ],
                    'default'    => [
                        'size' => 0,
                        'unit' => 'px',
                    ],
                    'condition'  => [
                        DisplayKeys::POSITION => 'custom',
                    ],
                    'selectors'  => [
                        '{{WRAPPER}}' => 'top: {{SIZE}}{{UNIT}};',
                    ],
                ]
            );
        }

        if (PopupTypes::TOPBAR === $type) {
            // Bottom bars are always viewport-fixed, so sticky only applies to top.
            $this->add_control(
                DisplayKeys::STICKY,
                [
                    'label'        => esc_html__('Sticky', 'elemacy'),
                    'type'         => Controls_Manager::SWITCHER,
                    'label_on'     => esc_html__('Yes', 'elemacy'),
                    'label_off'    => esc_html__('No', 'elemacy'),
                    'return_value' => 'yes',
                    'default'      => '',
                    'description'  => esc_html__('Keep the bar visible while scrolling.', 'elemacy'),
                    'condition'    => [
                        DisplayKeys::POSITION => 'top',
                    ],
                ]
            );
        }

        $this->add_responsive_control(
            DisplayKeys::WIDTH,
            [
                'label'      => esc_html__('Width', 'elemacy'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vw'],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 1200,
                    ],
                    '%'  => [
                        'min' => 0,
                        'max' => 100,
                    ],
                    'vw' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default'    => [
                    'size' => $defaults['width']['value'],
                    'unit' => 'auto' === $defaults['width']['unit'] ? 'px' : $defaults['width']['unit'],
                ],
                'selectors'  => [
                    '{{WRAPPER}}' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            DisplayKeys::Z_INDEX,
            [
                'label'   => esc_html__('Z-Index', 'elemacy'),
                'type'    => Controls_Manager::NUMBER,
                'default' => $defaults['z_index'],
            ]
        );

        if ($is_modal) {
            $this->register_overlay_controls($defaults);
        }

        $this->add_control(
            DisplayKeys::ANIMATION_IN,
            [
                'label'     => esc_html__('Animation In', 'elemacy'),
                'type'      => Controls_Manager::SELECT,
                'default'   => $defaults['animation']['in'],
                'separator' => 'before',
                'options'   => [
                    'none'       => esc_html__('None', 'elemacy'),
                    'fade'       => esc_html__('Fade', 'elemacy'),
                    'slide-down' => esc_html__('Slide Down', 'elemacy'),
                    'slide-up'   => esc_html__('Slide Up', 'elemacy'),
                ],
            ]
        );

        $this->add_control(
            DisplayKeys::ANIMATION_OUT,
            [
                'label'   => esc_html__('Animation Out', 'elemacy'),
                'type'    => Controls_Manager::SELECT,
                'default' => $defaults['animation']['out'],
                'options' => [
                    'none'       => esc_html__('None', 'elemacy'),
                    'fade'       => esc_html__('Fade', 'elemacy'),
                    'slide-down' => esc_html__('Slide Down', 'elemacy'),
                    'slide-up'   => esc_html__('Slide Up', 'elemacy'),
                ],
            ]
        );

        $this->add_control(
            DisplayKeys::CLOSE_BUTTON,
            [
                'label'        => esc_html__('Close Button', 'elemacy'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('On', 'elemacy'),
                'label_off'    => esc_html__('Off', 'elemacy'),
                'return_value' => 'yes',
                'default'      => $defaults['close']['button'] ? 'yes' : '',
                'separator'    => 'before',
                'selectors'    => [
                    '{{WRAPPER}} .elemacy-popup__close' => 'display: flex;',
                ],
            ]
        );

        $this->add_control(
            DisplayKeys::AUTO_CLOSE,
            [
                'label'       => esc_html__('Auto Close (seconds)', 'elemacy'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'default'     => $defaults['close']['auto_close_s'],
                'description' => esc_html__('0 disables auto close.', 'elemacy'),
            ]
        );

        if ($is_modal) {
            $this->add_control(
                DisplayKeys::CLOSE_ON_OVERLAY,
                [
                    'label'        => esc_html__('Close on Overlay Click', 'elemacy'),
                    'type'         => Controls_Manager::SWITCHER,
                    'label_on'     => esc_html__('Yes', 'elemacy'),
                    'label_off'    => esc_html__('No', 'elemacy'),
                    'return_value' => 'yes',
                    'default'      => $defaults['close']['on_overlay_click'] ? 'yes' : '',
                ]
            );

            $this->add_control(
                DisplayKeys::CLOSE_ON_ESC,
                [
                    'label'        => esc_html__('Close on Esc', 'elemacy'),
                    'type'         => Controls_Manager::SWITCHER,
                    'label_on'     => esc_html__('Yes', 'elemacy'),
                    'label_off'    => esc_html__('No', 'elemacy'),
                    'return_value' => 'yes',
                    'default'      => $defaults['close']['on_esc'] ? 'yes' : '',
                ]
            );

            $this->add_control(
                DisplayKeys::PREVENT_SCROLL,
                [
                    'label'        => esc_html__('Prevent Body Scroll', 'elemacy'),
                    'type'         => Controls_Manager::SWITCHER,
                    'label_on'     => esc_html__('Yes', 'elemacy'),
                    'label_off'    => esc_html__('No', 'elemacy'),
                    'return_value' => 'yes',
                    'default'      => $defaults['prevent_body_scroll'] ? 'yes' : '',
                    'separator'    => 'before',
                ]
            );
        }

        $this->end_controls_section();
    }

    /**
     * Overlay (backdrop) controls — modal popups only. The backdrop is always
     * present for a popup (it is part of the modal contract); only its look is
     * configurable. Topbar/banner/floating are non-modal and have no backdrop.
     */
    protected function register_overlay_controls(array $defaults)
    {
        $this->add_control(
            DisplayKeys::OVERLAY_COLOR,
            [
                'label'     => esc_html__('Overlay Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'default'   => $defaults['overlay']['color'],
                'separator' => 'before',
                'selectors' => [
                    'body' => '--elemacy-ov-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            DisplayKeys::OVERLAY_OPACITY,
            [
                'label'     => esc_html__('Overlay Opacity', 'elemacy'),
                'type'      => Controls_Manager::SLIDER,
                'range'     => [
                    'px' => [
                        'min'  => 0,
                        'max'  => 1,
                        'step' => 0.05,
                    ],
                ],
                'default'   => [
                    'size' => $defaults['overlay']['opacity'],
                ],
                'selectors' => [
                    'body' => '--elemacy-ov-opacity: {{SIZE}};',
                ],
            ]
        );

        $this->add_control(
            DisplayKeys::OVERLAY_BLUR,
            [
                'label'       => esc_html__('Background Blur', 'elemacy'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => [
                    'px' => [
                        'min'  => 0,
                        'max'  => DisplayDefaults::MAX_OVERLAY_BLUR,
                        'step' => 1,
                    ],
                ],
                'default'     => [
                    'size' => $defaults['overlay']['blur'] ?? DisplayDefaults::DEFAULT_OVERLAY_BLUR,
                    'unit' => 'px',
                ],
                'description' => esc_html__('Blur the page behind the overlay. Lower values usually feel more natural and render faster.', 'elemacy'),
                'selectors'   => [
                    'body' => '--elemacy-ov-blur: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
    }

    protected function register_close_style_controls()
    {
        $btn = '{{WRAPPER}} .elemacy-popup__close';

        $this->start_controls_section(
            'elemacy_close_style_section',
            [
                'label'     => esc_html__('Close Button', 'elemacy'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => [
                    DisplayKeys::CLOSE_BUTTON => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'elemacy_close_size',
            [
                'label'      => esc_html__('Button Size', 'elemacy'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => [
                        'min' => 16,
                        'max' => 100,
                    ],
                ],
                'default'    => [
                    'size' => 36,
                    'unit' => 'px',
                ],
                'selectors'  => [
                    $btn => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'elemacy_close_font',
            [
                'label'      => esc_html__('Icon Size', 'elemacy'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => [
                        'min' => 10,
                        'max' => 60,
                    ],
                ],
                'default'    => [
                    'size' => 20,
                    'unit' => 'px',
                ],
                'selectors'  => [
                    $btn => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'elemacy_close_offset',
            [
                'label'      => esc_html__('Distance From Edge', 'elemacy'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 80,
                    ],
                ],
                'default'    => [
                    'size' => 10,
                    'unit' => 'px',
                ],
                'selectors'  => [
                    $btn => 'top: {{SIZE}}{{UNIT}}; right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'elemacy_close_radius',
            [
                'label'      => esc_html__('Border Radius', 'elemacy'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                    '%'  => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default'    => [
                    'size' => 50,
                    'unit' => '%',
                ],
                'selectors'  => [
                    $btn => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'      => 'elemacy_close_border',
                'selector'  => $btn,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'elemacy_close_box_shadow',
                'selector' => $btn,
            ]
        );

        $this->start_controls_tabs('elemacy_close_state_tabs', ['separator' => 'before']);

        $this->start_controls_tab(
            'elemacy_close_normal_tab',
            ['label' => esc_html__('Normal', 'elemacy')]
        );

        $this->add_control(
            'elemacy_close_color',
            [
                'label'     => esc_html__('Icon Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    $btn => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_close_bg',
            [
                'label'     => esc_html__('Background', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'default'   => 'rgba(0, 0, 0, 0.55)',
                'selectors' => [
                    $btn => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'elemacy_close_hover_tab',
            ['label' => esc_html__('Hover', 'elemacy')]
        );

        $this->add_control(
            'elemacy_close_color_hover',
            [
                'label'     => esc_html__('Icon Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    $btn . ':hover, ' . $btn . ':focus' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_close_bg_hover',
            [
                'label'     => esc_html__('Background', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    $btn . ':hover, ' . $btn . ':focus' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_close_border_color_hover',
            [
                'label'     => esc_html__('Border Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    $btn . ':hover, ' . $btn . ':focus' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'elemacy_close_active_tab',
            ['label' => esc_html__('Active', 'elemacy')]
        );

        $this->add_control(
            'elemacy_close_color_active',
            [
                'label'     => esc_html__('Icon Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    $btn . ':active' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_close_bg_active',
            [
                'label'     => esc_html__('Background', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    $btn . ':active' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_close_border_color_active',
            [
                'label'     => esc_html__('Border Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    $btn . ':active' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }
}
