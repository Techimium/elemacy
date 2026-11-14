<?php

namespace Elemacy\Modules\LoopBuilder\PostCard\Parts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardText;
use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use WP_Post;

/**
 * The post's featured image, or a fallback.
 *
 * @internal
 */
final class ImagePart extends AbstractPostCardPart
{
    public function key(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return esc_html__('Image', 'elemacy');
    }

    public function defaults(): array
    {
        return [
            'image_size' => 'medium_large',
            'image_fallback' => [
                'url' => '',
                'id' => '',
            ],
            'image_eager_count' => 0,
            'image_link' => 'yes',
        ];
    }

    public function register_row_controls(Repeater $rows, array $condition): void
    {
        $defaults = $this->defaults();
        $sizes = ['full' => esc_html__('Full', 'elemacy')];

        foreach (get_intermediate_image_sizes() as $size) {
            $sizes[$size] = ucwords(str_replace(['_', '-'], ' ', $size));
        }

        $rows->add_control(
            'image_size',
            [
                'label' => esc_html__('Image Size', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $sizes,
                'default' => $defaults['image_size'],
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'image_fallback',
            [
                'label' => esc_html__('Fallback Image', 'elemacy'),
                'type' => Controls_Manager::MEDIA,
                'default' => $defaults['image_fallback'],
                'description' => esc_html__('Shown for posts with no featured image.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'image_eager_count',
            [
                'label' => esc_html__('Images to Load Right Away', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'min' => 0,
                'default' => $defaults['image_eager_count'],
                'description' => esc_html__('How many of the first images load with the page instead of when scrolled to.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'image_link',
            [
                'label' => esc_html__('Link to Post', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => $defaults['image_link'],
                'condition' => $condition,
            ]
        );
    }

    public function register_style_section(BaseWidget $widget): void
    {
        $this->open_style_section($widget);
        $media = '{{WRAPPER}} .elemacy-post-card__media';
        $image = $media . ' img';

        $widget->add_responsive_control(
            'card_image_ratio',
            [
                'label' => esc_html__('Aspect Ratio', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => esc_html__('Original', 'elemacy'),
                    '1 / 1' => '1:1',
                    '4 / 3' => '4:3',
                    '3 / 2' => '3:2',
                    '16 / 9' => '16:9',
                    '3 / 4' => '3:4',
                ],
                'default' => '',
                'selectors' => [$image => 'aspect-ratio: {{VALUE}};'],
            ]
        );

        $widget->add_responsive_control(
            'card_image_fit',
            [
                'label' => esc_html__('Fit', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'cover' => esc_html__('Cover', 'elemacy'),
                    'contain' => esc_html__('Contain', 'elemacy'),
                ],
                'default' => 'cover',
                'selectors' => [$image => 'object-fit: {{VALUE}};'],
            ]
        );

        $widget->add_responsive_control(
            'card_image_width',
            [
                'label' => esc_html__('Width Beside Content', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['%', 'px'],
                'range' => [
                    '%' => [
                        'min' => 10,
                        'max' => 90,
                    ],
                    'px' => [
                        'min' => 50,
                        'max' => 800,
                    ],
                ],
                'selectors' => ['{{WRAPPER}} .elemacy-post-card' => '--elemacy-card-media-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $widget->add_responsive_control(
            'card_image_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [$media => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $widget->add_control(
            'card_image_overlay',
            [
                'label' => esc_html__('Overlay Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [$media . '::after' => 'background-color: {{VALUE}};'],
            ]
        );

        $widget->add_control(
            'card_image_overlay_hover',
            [
                'label' => esc_html__('Overlay Hover Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .elemacy-post-card:hover .elemacy-post-card__media::after' => 'background-color: {{VALUE}};'],
            ]
        );

        $widget->add_control(
            'card_image_zoom',
            [
                'label' => esc_html__('Hover Zoom', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 1.5,
                        'step' => 0.01,
                    ],
                ],
                'selectors' => ['{{WRAPPER}} .elemacy-post-card:hover .elemacy-post-card__media img' => 'transform: scale({{SIZE}});'],
            ]
        );

        $widget->end_controls_section();
    }

    public function render(WP_Post $post, array $row, PostCardContext $card): string
    {
        $id = (int) get_post_thumbnail_id($post);

        if (0 === $id && is_array($row['image_fallback'])) {
            $id = (int) ($row['image_fallback']['id'] ?? 0);
        }

        if (0 === $id) {
            return '';
        }

        $eager = 1 === $card->page() && $card->occurrence() < (int) $row['image_eager_count'];
        $attributes = ['loading' => $eager ? 'eager' : 'lazy'];

        if ($eager && 0 === $card->occurrence()) {
            $attributes['fetchpriority'] = 'high';
        }

        $image = wp_get_attachment_image($id, sanitize_key((string) $row['image_size']), false, $attributes);

        if ('' === $image || 'yes' !== $row['image_link'] || $card->whole_card_link()) {
            return $image;
        }

        // A linked title is already this card's link, so the image adds no second stop.
        $extra = $card->title_is_linked()
            ? ' tabindex="-1" aria-hidden="true"'
            : ' aria-label="' . esc_attr(PostCardText::link_name($post)) . '"';

        return '<a href="' . esc_url(get_permalink($post)) . '"' . $extra . $card->link_attributes() . '>' . $image . '</a>';
    }
}
