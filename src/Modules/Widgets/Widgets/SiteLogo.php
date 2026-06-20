<?php

namespace Elemacy\Modules\Widgets\Widgets;

defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Plugin;
use Elemacy\Modules\Widgets\Services\LogoResolver;

class SiteLogo extends BaseWidget
{
    private LogoResolver $logo_resolver;

    public function __construct(array $data = [], $args = null)
    {
        parent::__construct($data, $args);
        $this->logo_resolver = new LogoResolver();
    }

    public function get_name(): string
    {
        return 'elemacy-site-logo';
    }

    public function get_title(): string
    {
        return esc_html__('Site Logo', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'eicon-site-logo';
    }

    public function get_keywords(): array
    {
        return ['logo', 'site', 'brand', 'image', 'header'];
    }

    protected function is_dynamic_content(): bool
    {
        return true;
    }

    protected function register_controls(): void
    {
        $this->register_logo_section();
        $this->register_image_style_section();
        $this->register_caption_style_section();
    }

    // ─── Content Controls ────────────────────────────────────────────────────

    private function register_logo_section(): void
    {
        $this->start_controls_section('section_logo', [
            'label' => esc_html__('Logo', 'elemacy'),
        ]);

        $this->add_control('logo_source', [
            'label'   => esc_html__('Source', 'elemacy'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'custom_logo',
            'options' => [
                'custom_logo' => esc_html__('Custom Logo (Customizer)', 'elemacy'),
                'custom'      => esc_html__('Custom Image', 'elemacy'),
            ],
        ]);

        $this->add_control('customizer_link', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url(admin_url('customize.php?autofocus[section]=title_tagline')),
                esc_html__('Edit Site Identity →', 'elemacy')
            ),
            'content_classes' => 'elementor-descriptor',
            'condition'       => ['logo_source' => 'custom_logo'],
        ]);

        $this->add_control('image', [
            'label'     => esc_html__('Choose Image', 'elemacy'),
            'type'      => Controls_Manager::MEDIA,
            'dynamic'   => ['active' => true],
            'condition' => ['logo_source' => 'custom'],
        ]);

        // Generates image_size and image_custom_dimension settings used across all sources.
        $this->add_group_control(Group_Control_Image_Size::get_type(), [
            'name'    => 'image',
            'default' => 'full',
        ]);

        $this->add_control('caption_source', [
            'label'     => esc_html__('Caption', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'none',
            'options'   => [
                'none'       => esc_html__('None', 'elemacy'),
                'attachment' => esc_html__('Attachment Caption', 'elemacy'),
                'custom'     => esc_html__('Custom Caption', 'elemacy'),
            ],
            'separator' => 'before',
        ]);

        $this->add_control('caption', [
            'label'       => esc_html__('Custom Caption', 'elemacy'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => esc_html__('Enter caption text', 'elemacy'),
            'dynamic'     => ['active' => true],
            'condition'   => ['caption_source' => 'custom'],
        ]);

        $this->add_control('link_to', [
            'label'     => esc_html__('Link', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'home',
            'options'   => [
                'home'   => esc_html__('Homepage', 'elemacy'),
                'file'   => esc_html__('Image File', 'elemacy'),
                'custom' => esc_html__('Custom URL', 'elemacy'),
                'none'   => esc_html__('None', 'elemacy'),
            ],
            'separator' => 'before',
        ]);

        $this->add_control('link', [
            'label'      => esc_html__('Link', 'elemacy'),
            'type'       => Controls_Manager::URL,
            'dynamic'    => ['active' => true],
            'show_label' => false,
            'condition'  => ['link_to' => 'custom'],
        ]);

        $this->add_control('open_lightbox', [
            'label'     => esc_html__('Lightbox', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'default',
            'options'   => [
                'default' => esc_html__('Default', 'elemacy'),
                'yes'     => esc_html__('Yes', 'elemacy'),
                'no'      => esc_html__('No', 'elemacy'),
            ],
            'condition' => ['link_to' => 'file'],
        ]);

        $this->end_controls_section();
    }

    // ─── Style Controls ───────────────────────────────────────────────────────

    private function register_image_style_section(): void
    {
        $this->start_controls_section('section_style_image', [
            'label' => esc_html__('Image', 'elemacy'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('align', [
            'label'                => esc_html__('Alignment', 'elemacy'),
            'type'                 => Controls_Manager::CHOOSE,
            'options'              => [
                'start'  => [
                    'title' => esc_html__('Start', 'elemacy'),
                    'icon' => 'eicon-text-align-left'
                ],
                'center' => [
                    'title' => esc_html__('Center', 'elemacy'),
                    'icon' => 'eicon-text-align-center'
                ],
                'end'    => [
                    'title' => esc_html__('End', 'elemacy'),
                    'icon' => 'eicon-text-align-right'
                ],
            ],
            'classes'              => 'elementor-control-start-end',
            'selectors_dictionary' => [
                'left'  => is_rtl() ? 'end' : 'start',
                'right' => is_rtl() ? 'start' : 'end',
            ],
            'selectors'            => [
                '{{WRAPPER}}' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('width', [
            'label'          => esc_html__('Width', 'elemacy'),
            'type'           => Controls_Manager::SLIDER,
            'default'        => ['unit' => '%'],
            'tablet_default' => ['unit' => '%'],
            'mobile_default' => ['unit' => '%'],
            'size_units'     => ['px', '%', 'em', 'rem', 'vw', 'custom'],
            'range'          => [
                '%'  => [
                    'min' => 1,
                    'max' => 100
                ],
                'px' => [
                    'min' => 1,
                    'max' => 1000
                ],
                'vw' => [
                    'min' => 1,
                    'max' => 100
                ],
            ],
            'selectors'      => [
                '{{WRAPPER}} img' => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('max_width', [
            'label'          => esc_html__('Max Width', 'elemacy'),
            'type'           => Controls_Manager::SLIDER,
            'default'        => ['unit' => '%'],
            'tablet_default' => ['unit' => '%'],
            'mobile_default' => ['unit' => '%'],
            'size_units'     => ['px', '%', 'em', 'rem', 'vw', 'custom'],
            'range'          => [
                '%'  => [
                    'min' => 1,
                    'max' => 100
                ],
                'px' => [
                    'min' => 1,
                    'max' => 1000
                ],
                'vw' => [
                    'min' => 1,
                    'max' => 100
                ],
            ],
            'selectors'      => [
                '{{WRAPPER}} img' => 'max-width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('height', [
            'label'      => esc_html__('Height', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em', 'rem', 'vh', 'custom'],
            'range'      => [
                'px' => [
                    'min' => 1,
                    'max' => 500
                ],
                'vh' => [
                    'min' => 1,
                    'max' => 100
                ],
            ],
            'selectors'  => [
                '{{WRAPPER}} img' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('object-fit', [
            'label'     => esc_html__('Object Fit', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'condition' => ['height[size]!' => ''],
            'options'   => [
                ''           => esc_html__('Default', 'elemacy'),
                'fill'       => esc_html__('Fill', 'elemacy'),
                'cover'      => esc_html__('Cover', 'elemacy'),
                'contain'    => esc_html__('Contain', 'elemacy'),
                'scale-down' => esc_html__('Scale Down', 'elemacy'),
            ],
            'default'   => '',
            'selectors' => [
                '{{WRAPPER}} img' => 'object-fit: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('object-position', [
            'label'     => esc_html__('Object Position', 'elemacy'),
            'type'      => Controls_Manager::SELECT,
            'condition' => [
                'height[size]!' => '',
                'object-fit'    => ['cover', 'contain', 'scale-down'],
            ],
            'options'   => [
                'center center' => esc_html__('Center Center', 'elemacy'),
                'center left'   => esc_html__('Center Left', 'elemacy'),
                'center right'  => esc_html__('Center Right', 'elemacy'),
                'top center'    => esc_html__('Top Center', 'elemacy'),
                'top left'      => esc_html__('Top Left', 'elemacy'),
                'top right'     => esc_html__('Top Right', 'elemacy'),
                'bottom center' => esc_html__('Bottom Center', 'elemacy'),
                'bottom left'   => esc_html__('Bottom Left', 'elemacy'),
                'bottom right'  => esc_html__('Bottom Right', 'elemacy'),
            ],
            'default'   => 'center center',
            'selectors' => [
                '{{WRAPPER}} img' => 'object-position: {{VALUE}};',
            ],
        ]);

        $this->add_control('separator_panel_style', [
            'type'  => Controls_Manager::DIVIDER,
            'style' => 'thick',
        ]);

        $this->start_controls_tabs('image_effects');

        $this->start_controls_tab('normal', ['label' => esc_html__('Normal', 'elemacy')]);

        $this->add_control('opacity', [
            'label'     => esc_html__('Opacity', 'elemacy'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => [
                'px' => [
                    'max' => 1,
                    'min' => 0.10,
                    'step' => 0.01
                ],
            ],
            'selectors' => ['{{WRAPPER}} img' => 'opacity: {{SIZE}};'],
        ]);

        $this->add_group_control(Group_Control_Css_Filter::get_type(), [
            'name'     => 'css_filters',
            'selector' => '{{WRAPPER}} img',
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('hover', ['label' => esc_html__('Hover', 'elemacy')]);

        $this->add_control('opacity_hover', [
            'label'     => esc_html__('Opacity', 'elemacy'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => [
                'px' => [
                    'max' => 1,
                    'min' => 0.10,
                    'step' => 0.01
                ],
            ],
            'selectors' => ['{{WRAPPER}}:hover img' => 'opacity: {{SIZE}};'],
        ]);

        $this->add_group_control(Group_Control_Css_Filter::get_type(), [
            'name'     => 'css_filters_hover',
            'selector' => '{{WRAPPER}}:hover img',
        ]);

        $this->add_control('background_hover_transition', [
            'label'     => esc_html__('Transition Duration', 'elemacy') . ' (s)',
            'type'      => Controls_Manager::SLIDER,
            'range'     => [
                'px' => [
                    'min' => 0,
                    'max' => 3,
                    'step' => 0.1
                ],
            ],
            'selectors' => ['{{WRAPPER}} img' => 'transition-duration: {{SIZE}}s'],
        ]);

        $this->add_control('hover_animation', [
            'label' => esc_html__('Hover Animation', 'elemacy'),
            'type'  => Controls_Manager::HOVER_ANIMATION,
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'image_border',
            'selector'  => '{{WRAPPER}} img',
            'separator' => 'before',
        ]);

        $this->add_responsive_control('image_border_radius', [
            'label'      => esc_html__('Border Radius', 'elemacy'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%', 'em', 'rem', 'custom'],
            'selectors'  => [
                '{{WRAPPER}} img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'image_box_shadow',
            'exclude'  => ['box_shadow_position'],
            'selector' => '{{WRAPPER}} img',
        ]);

        $this->end_controls_section();
    }

    private function register_caption_style_section(): void
    {
        $this->start_controls_section('section_style_caption', [
            'label'     => esc_html__('Caption', 'elemacy'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['caption_source!' => 'none'],
        ]);

        $this->add_responsive_control('caption_align', [
            'label'                => esc_html__('Alignment', 'elemacy'),
            'type'                 => Controls_Manager::CHOOSE,
            'options'              => [
                'start'   => [
                    'title' => esc_html__('Start', 'elemacy'),
                    'icon' => 'eicon-text-align-left'
                ],
                'center'  => [
                    'title' => esc_html__('Center', 'elemacy'),
                    'icon' => 'eicon-text-align-center'
                ],
                'end'     => [
                    'title' => esc_html__('End', 'elemacy'),
                    'icon' => 'eicon-text-align-right'
                ],
                'justify' => [
                    'title' => esc_html__('Justified', 'elemacy'),
                    'icon' => 'eicon-text-align-justify'
                ],
            ],
            'classes'              => 'elementor-control-start-end',
            'selectors_dictionary' => [
                'left'  => is_rtl() ? 'end' : 'start',
                'right' => is_rtl() ? 'start' : 'end',
            ],
            'default'              => '',
            'selectors'            => [
                '{{WRAPPER}} .elemacy-site-logo__caption' => 'text-align: {{VALUE}};',
            ],
        ]);

        $this->add_control('caption_color', [
            'label'     => esc_html__('Text Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'global'    => ['default' => Global_Colors::COLOR_TEXT],
            'selectors' => [
                '{{WRAPPER}} .elemacy-site-logo__caption' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('caption_background_color', [
            'label'     => esc_html__('Background Color', 'elemacy'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .elemacy-site-logo__caption' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'caption_typography',
            'selector' => '{{WRAPPER}} .elemacy-site-logo__caption',
            'global'   => ['default' => Global_Typography::TYPOGRAPHY_TEXT],
        ]);

        $this->add_group_control(Group_Control_Text_Shadow::get_type(), [
            'name'     => 'caption_text_shadow',
            'selector' => '{{WRAPPER}} .elemacy-site-logo__caption',
        ]);

        $this->add_responsive_control('caption_space', [
            'label'      => esc_html__('Spacing', 'elemacy'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem', 'custom'],
            'range'      => [
                'px'  => ['max' => 100],
                'em'  => [
                    'min' => 0,
                    'max' => 10
                ],
                'rem' => [
                    'min' => 0,
                    'max' => 10
                ],
            ],
            'selectors'  => [
                '{{WRAPPER}} .elemacy-site-logo__caption' => 'margin-block-start: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    // ─── Rendering ───────────────────────────────────────────────────────────

    protected function render(): void
    {
        $settings      = $this->get_settings_for_display();
        $attachment_id = $this->logo_resolver->resolve($settings);

        if (!$attachment_id) {
            return;
        }

        $image_size = $this->resolve_image_size($settings);
        $image_html = $this->get_attachment_image_html($attachment_id, $image_size, $settings);

        if (!$image_html) {
            return;
        }

        $link        = $this->resolve_link_url($settings, $attachment_id);
        $has_caption = $this->has_caption($settings);

        if ($link) {
            $this->add_link_attributes('logo_link', $link);

            if (Plugin::$instance->editor->is_edit_mode()) {
                $this->add_render_attribute('logo_link', 'class', 'elementor-clickable');
            }

            if ('file' === $settings['link_to']) {
                $this->add_lightbox_data_attributes('logo_link', $attachment_id, $settings['open_lightbox']);
            }
        }

        if ($has_caption) {
            echo '<figure class="elemacy-site-logo wp-caption">';
        }

        if ($link) {
            echo '<a ' . $this->get_render_attribute_string('logo_link') . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ($link) {
            echo '</a>';
        }

        if ($has_caption) {
            echo '<figcaption class="elemacy-site-logo__caption wp-caption-text">';
            echo wp_kses_post($this->get_caption_text($settings, $attachment_id));
            echo '</figcaption></figure>';
        }
    }

    protected function content_template(): void
    {
        ?>
        <#
        var logoUrl = '';

        if ( 'custom' === settings.logo_source && settings.image && settings.image.url ) {
            logoUrl = settings.image.url;
        }

        if ( ! logoUrl ) {
            #><div class="elemacy-site-logo__placeholder" style="border:2px dashed #ccc;padding:20px;text-align:center;color:#aaa;">
                <?php echo esc_html__('Site Logo', 'elemacy'); ?>
            </div><#
            return;
        }

        var imgClass = settings.hover_animation ? 'elementor-animation-' + settings.hover_animation : '';
        var hasCaption = settings.caption_source && 'none' !== settings.caption_source;
        var caption = 'custom' === settings.caption_source ? settings.caption : '';

        var linkUrl = '';
        if ( 'custom' === settings.link_to && settings.link && settings.link.url ) {
            linkUrl = settings.link.url;
        } else if ( 'file' === settings.link_to ) {
            linkUrl = logoUrl;
        } else if ( 'home' === settings.link_to ) {
            linkUrl = '/';
        }
        #>

        <# if ( hasCaption ) { #><figure class="elemacy-site-logo wp-caption"><# } #>
        <# if ( linkUrl ) { #><a class="elementor-clickable" href="{{ elementor.helpers.sanitizeUrl( linkUrl ) }}"><# } #>
        <img src="{{ logoUrl }}" class="{{ imgClass }}" />
        <# if ( linkUrl ) { #></a><# } #>
        <# if ( hasCaption && caption ) { #>
        <figcaption class="elemacy-site-logo__caption wp-caption-text">{{{ caption }}}</figcaption>
        <# } #>
        <# if ( hasCaption ) { #></figure><# } #>
        <?php
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * @return string|array
     */
    private function resolve_image_size(array $settings)
    {
        $size = $settings['image_size'] ?? 'full';

        if ('custom' === $size) {
            return [
                (int) ($settings['image_custom_dimension']['width']  ?? 0),
                (int) ($settings['image_custom_dimension']['height'] ?? 0),
            ];
        }

        return $size;
    }

    /**
     * @param string|array $size
     */
    private function get_attachment_image_html(int $attachment_id, $size, array $settings): string
    {
        $hover_class  = $settings['hover_animation']
            ? 'elementor-animation-' . $settings['hover_animation']
            : '';
        $append_class = null;

        if ($hover_class) {
            $append_class = static function (array $attr) use ($hover_class): array {
                $attr['class'] = trim(($attr['class'] ?? '') . ' ' . $hover_class);
                return $attr;
            };
            add_filter('wp_get_attachment_image_attributes', $append_class);
        }

        $html = wp_get_attachment_image($attachment_id, $size);

        if ($append_class) {
            remove_filter('wp_get_attachment_image_attributes', $append_class);
        }

        return $html;
    }

    private function resolve_link_url(array $settings, int $attachment_id)
    {
        switch ($settings['link_to']) {
            case 'home':
                return ['url' => home_url('/')];
            case 'file':
                return ['url' => (string) wp_get_attachment_url($attachment_id)];
            case 'custom':
                return !empty($settings['link']['url']) ? $settings['link'] : false;
            default:
                return false;
        }
    }

    private function has_caption(array $settings): bool
    {
        return !empty($settings['caption_source']) && 'none' !== $settings['caption_source'];
    }

    private function get_caption_text(array $settings, int $attachment_id): string
    {
        switch ($settings['caption_source'] ?? '') {
            case 'attachment':
                return (string) wp_get_attachment_caption($attachment_id);
            case 'custom':
                return (string) ($settings['caption'] ?? '');
            default:
                return '';
        }
    }
}
