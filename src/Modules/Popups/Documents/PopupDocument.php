<?php

namespace Elemacy\Modules\Popups\Documents;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\PostTypes\PopupPostType;
use Elemacy\Modules\Popups\Support\DisplayDefaults;
use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\PageBase;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Modules\PageTemplates\Module as PageTemplatesModule;

/**
 * Custom Elementor document type for popups.
 *
 * Extends Elementor core's PageBase (the same base used by the built-in
 * "Page" / "Post" documents) so popups are edited on a real WordPress post
 * bound to our CPT, with full page-style controls. We force the Elementor
 * Canvas page template so the popup is authored without theme header/footer.
 *
 * PageBase lives in free Elementor core (core/document-types/page-base.php),
 * so this works without Elementor Pro.
 */
class PopupDocument extends PageBase
{
    /**
     * Document type slug, also used as the `_elementor_template_type` meta value.
     */
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

    /**
     * Document properties.
     *
     * - `cpt` binds the document type to our popup CPT.
     * - `support_kit` lets popups inherit global kit (site) settings.
     * - `show_in_library` keeps popups out of Elementor's template library UI.
     * - `admin_tab_group` empty so it is not grouped under another admin tab.
     *
     * The blank canvas is enforced in the constructor (forcing the
     * `_wp_page_template` => `elementor_canvas` setting), matching how
     * PageBase reads the template from `_wp_page_template`.
     *
     * @return array
     */
    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['cpt']             = [PopupPostType::POST_TYPE];
        $properties['support_kit']     = true;
        $properties['show_in_library'] = false;
        $properties['admin_tab_group'] = '';

        return $properties;
    }

    /**
     * Force the Elementor Canvas layout so popups edit with no theme chrome.
     *
     * PageBase's constructor seeds `$data['settings']['template']` from the
     * post's `_wp_page_template` meta (defaulting to `default`). We override
     * that default to canvas so a freshly-created popup opens blank even before
     * the meta is persisted.
     *
     * @param array $data
     */
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
     * Scope the document's settings CSS ({{WRAPPER}}) to the Elementor content
     * wrapper `.elementor-{id}` — the popup box.
     *
     * PageBase defaults this to `body.elementor-page-{id}` (the whole page),
     * which is wrong for a popup: size/style controls would target the page, not
     * the box, and would not match on the frontend (where the popup is injected
     * in the footer). `.elementor-{id}` is the one element present in BOTH the
     * editor canvas and the frontend (the engine tags it `.elemacy-popup__box`),
     * so controls live-preview in the editor and apply on the site.
     *
     * @return string
     */
    public function get_css_wrapper_selector()
    {
        return '.elementor-' . $this->get_main_id();
    }

    /**
     * Register native Elementor controls for the popup's display / layout.
     * These replace our old `_elemacy_popup_display` meta + React form: authors
     * edit them in the builder (Settings + Style tabs) and see them live.
     *
     * Controls drive the preview two ways, both live-updated by Elementor:
     * - Direct CSS `selectors` on `{{WRAPPER}}` (the box) for size/spacing/style.
     * - CSS custom properties (e.g. `--elemacy-ov-color`) consumed by the
     *   editor-preview chrome (see EditorPreview) and the frontend close button,
     *   so overlay / close / position update live without custom JS.
     * Behavior values (position, overlay on/off, close on/off, animation, auto
     *   close, prevent scroll, z-index) are ALSO read in PHP via DocumentDisplay
     *   and handed to the frontend engine.
     *
     * Defaults are seeded per popup type from DisplayDefaults.
     */
    protected function register_controls()
    {
        $type     = get_post_meta($this->get_main_id(), '_elemacy_popup_type', true);
        $defaults = DisplayDefaults::for_type($type ? $type : 'popup');

        $this->register_layout_controls($type ? $type : 'popup', $defaults);
        $this->register_close_style_controls();
    }

    /**
     * Position options relevant to each popup type.
     *
     * @param string $type
     * @return array<string, string>
     */
    protected function position_options(string $type): array
    {
        // A top/bottom bar is inherently edge-anchored.
        if ('topbar' === $type) {
            return [
                'top'    => esc_html__('Top', 'elemacy'),
                'bottom' => esc_html__('Bottom', 'elemacy'),
            ];
        }

        // Popups, floating elements and banners can sit anywhere.
        return [
            'center'       => esc_html__('Center', 'elemacy'),
            'top'          => esc_html__('Top', 'elemacy'),
            'bottom'       => esc_html__('Bottom', 'elemacy'),
            'top-left'     => esc_html__('Top Left', 'elemacy'),
            'top-right'    => esc_html__('Top Right', 'elemacy'),
            'bottom-left'  => esc_html__('Bottom Left', 'elemacy'),
            'bottom-right' => esc_html__('Bottom Right', 'elemacy'),
        ];
    }

    /**
     * Settings tab — layout & behavior.
     *
     * @param string $type
     * @param array  $defaults
     */
    protected function register_layout_controls(string $type, array $defaults)
    {
        $this->start_controls_section(
            'elemacy_popup_section',
            [
                'label' => esc_html__('Popup', 'elemacy'),
                'tab'   => Controls_Manager::TAB_SETTINGS,
            ]
        );

        // NOTE: position/overlay write CSS vars to `body` ONLY to drive the
        // editor-preview live binding (EditorPreview reads them). They are
        // page-global, so the FRONTEND must not consume them — there each popup
        // positions off its own `.elemacy-popup--pos-*` class (engine.js) and
        // the overlay uses per-element inline styles, keeping popups independent.
        $this->add_control(
            'elemacy_position',
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
                ],
                'selectors'            => [
                    'body' => '{{VALUE}}',
                ],
            ]
        );

        if ('topbar' === $type) {
            // A top bar flows in the page by default (pushes content down). When
            // sticky it stays in view while scrolling. Bottom bars are always
            // fixed to the viewport, so sticky only applies to the top position.
            $this->add_control(
                'elemacy_sticky',
                [
                    'label'        => esc_html__('Sticky', 'elemacy'),
                    'type'         => Controls_Manager::SWITCHER,
                    'label_on'     => esc_html__('Yes', 'elemacy'),
                    'label_off'    => esc_html__('No', 'elemacy'),
                    'return_value' => 'yes',
                    'default'      => '',
                    'description'  => esc_html__('Keep the bar visible while scrolling.', 'elemacy'),
                    'condition'    => [
                        'elemacy_position' => 'top',
                    ],
                ]
            );
        }

        $this->add_responsive_control(
            'elemacy_width',
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

        $this->add_responsive_control(
            'elemacy_height',
            [
                'label'      => esc_html__('Height', 'elemacy'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range'      => [
                    'px' => [
                        'min' => 0,
                        'max' => 1200,
                    ],
                    'vh' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}}' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_z_index',
            [
                'label'   => esc_html__('Z-Index', 'elemacy'),
                'type'    => Controls_Manager::NUMBER,
                'default' => $defaults['z_index'],
            ]
        );

        $this->add_control(
            'elemacy_overlay_enabled',
            [
                'label'        => esc_html__('Overlay', 'elemacy'),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__('On', 'elemacy'),
                'label_off'    => esc_html__('Off', 'elemacy'),
                'return_value' => 'yes',
                'default'      => $defaults['overlay']['enabled'] ? 'yes' : '',
                'separator'    => 'before',
                'selectors'    => [
                    'body' => '--elemacy-ov-display: block;',
                ],
            ]
        );

        $this->add_control(
            'elemacy_overlay_color',
            [
                'label'     => esc_html__('Overlay Color', 'elemacy'),
                'type'      => Controls_Manager::COLOR,
                'default'   => $defaults['overlay']['color'],
                'condition' => [
                    'elemacy_overlay_enabled' => 'yes',
                ],
                'selectors' => [
                    'body' => '--elemacy-ov-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_overlay_opacity',
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
                'condition' => [
                    'elemacy_overlay_enabled' => 'yes',
                ],
                'selectors' => [
                    'body' => '--elemacy-ov-opacity: {{SIZE}};',
                ],
            ]
        );

        $this->add_control(
            'elemacy_animation_in',
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
            'elemacy_animation_out',
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
            'elemacy_close_button',
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
            'elemacy_auto_close',
            [
                'label'       => esc_html__('Auto Close (seconds)', 'elemacy'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'default'     => $defaults['close']['auto_close_s'],
                'description' => esc_html__('0 disables auto close.', 'elemacy'),
            ]
        );

        $this->add_control(
            'elemacy_close_on_overlay',
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
            'elemacy_close_on_esc',
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
            'elemacy_prevent_scroll',
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

        $this->end_controls_section();
    }

    /**
     * Style tab — the close button, styled with native Elementor controls
     * (Normal / Hover / Active tabs + Border & Box Shadow group controls).
     *
     * Selectors target the real `.elemacy-popup__close` element, which exists on
     * the frontend (injected by the engine) and in the editor preview (injected
     * by preview.js), so styling — including hover/active states — previews live
     * and renders identically on the site.
     */
    protected function register_close_style_controls()
    {
        $btn = '{{WRAPPER}} .elemacy-popup__close';

        $this->start_controls_section(
            'elemacy_close_style_section',
            [
                'label'     => esc_html__('Close Button', 'elemacy'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'elemacy_close_button' => 'yes',
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
