<?php

namespace Elemacy\Modules\LoopBuilder\Widgets;

use Elemacy\Core\Container;
use Elemacy\Loop\Contracts\ItemPresenterInterface;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardDefinition;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardParts;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardPresenter;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Plugin;
use Elementor\Repeater;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * What Post Grid and Post Carousel share: post-based sources only, the card's
 * parts and styles, and an empty-list message. No Loop Item template involved.
 *
 * @internal
 */
abstract class PostWidget extends LoopWidget
{
    private const SOURCES = ['posts', 'current_query', 'manual_posts', 'related_posts'];

    private const CARD_IMAGE_POSITIONS = [
        'top' => '--elemacy-card-direction: column; --elemacy-card-media-flex: 0 0 auto; --elemacy-card-media-position: relative; --elemacy-card-media-z: auto; --elemacy-card-media-inset: auto; --elemacy-card-media-pointer: auto; --elemacy-card-image-height: auto; --elemacy-card-content-justify: flex-start; --elemacy-card-footer-margin: auto;',
        'left' => '--elemacy-card-direction: row; --elemacy-card-media-flex: 0 0 var(--elemacy-card-media-width, 40%); --elemacy-card-media-position: relative; --elemacy-card-media-z: auto; --elemacy-card-media-inset: auto; --elemacy-card-media-pointer: auto; --elemacy-card-image-height: 100%; --elemacy-card-content-justify: flex-start; --elemacy-card-footer-margin: auto;',
        'right' => '--elemacy-card-direction: row-reverse; --elemacy-card-media-flex: 0 0 var(--elemacy-card-media-width, 40%); --elemacy-card-media-position: relative; --elemacy-card-media-z: auto; --elemacy-card-media-inset: auto; --elemacy-card-media-pointer: auto; --elemacy-card-image-height: 100%; --elemacy-card-content-justify: flex-start; --elemacy-card-footer-margin: auto;',
        'background' => '--elemacy-card-direction: column; --elemacy-card-media-flex: none; --elemacy-card-media-position: absolute; --elemacy-card-media-z: -1; --elemacy-card-media-inset: 0; --elemacy-card-media-pointer: none; --elemacy-card-image-height: 100%; --elemacy-card-content-justify: flex-end; --elemacy-card-footer-margin: 0;',
    ];

    protected function available_sources(): array
    {
        return array_intersect_key(LoopDataSourceRegistry::instance()->all(), array_flip(self::SOURCES));
    }

    protected function item_presenter(): ItemPresenterInterface
    {
        return Container::instance()->singleton(PostCardPresenter::class);
    }

    /** The page size sits in each Post widget's Layout section instead. */
    protected function register_query_page_size(): void
    {
    }

    protected function page_size_description(): string
    {
        return esc_html__('Also the most picks a Manual Selection shows.', 'elemacy');
    }

    /** The page size, or for Current Query why there is none to set. */
    protected function register_layout_page_size(): void
    {
        $this->register_page_size_control();

        if (!isset($this->available_sources()['current_query'])) {
            return;
        }

        $this->add_control(
            'page_size_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => esc_html__('The number of posts follows the archive\'s own setting (Settings > Reading).', 'elemacy'),
                'content_classes' => 'elementor-descriptor',
                'condition' => ['data_source' => 'current_query'],
            ]
        );
    }

    /** The widget's own outer markup for a list that has items. */
    abstract protected function render_list(array $settings, LoopDataSourceInterface $source, LoopResultDTO $result, int $page): void;

    protected function render_loop(): void
    {
        $settings = $this->get_settings_for_display();
        $sources = $this->available_sources();
        $key = (string) ($settings['data_source'] ?? 'posts');

        if (!isset($sources[$key])) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-warning">' . esc_html__('The selected data source for this widget is not available.', 'elemacy') . '</div>';
            }
            return;
        }

        if (!(new PostCardDefinition($settings))->rows()) {
            if (Plugin::instance()->editor->is_edit_mode()) {
                echo '<div class="elemacy-alert elemacy-alert-info">' . esc_html__('Add at least one part to the card.', 'elemacy') . '</div>';
            }
            return;
        }

        $page = $this->requested_page($settings, $sources[$key]);
        $result = $this->items_for_display($settings, $sources[$key], $page);

        if (empty($result->items)) {
            $this->render_nothing_found($settings);
            return;
        }

        $this->render_list($settings, $sources[$key], $result, $page);
    }

    private function render_nothing_found(array $settings): void
    {
        $this->print_empty_notice();

        $message = (string) ($settings['nothing_found_message'] ?? '');

        if ('' !== $message && !Plugin::instance()->editor->is_edit_mode()) {
            echo '<div class="elemacy-post-widget__empty">' . wp_kses_post($message) . '</div>';
        }
    }

    protected function register_card_content_section(): void
    {
        $this->start_controls_section(
            'section_card',
            [
                'label' => esc_html__('Card', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            PostCardDefinition::PARTS,
            [
                'label' => esc_html__('Parts', 'elemacy'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $this->card_part_rows()->get_controls(),
                'default' => PostCardDefinition::default_rows(),
                'title_field' => '{{{ (' . wp_json_encode($this->part_labels()) . ')[part] || part }}}',
            ]
        );

        $this->add_responsive_control(
            'card_image_position',
            [
                'label' => esc_html__('Image Position', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'separator' => 'before',
                'options' => [
                    'top' => esc_html__('Top', 'elemacy'),
                    'left' => esc_html__('Left', 'elemacy'),
                    'right' => esc_html__('Right', 'elemacy'),
                    'background' => esc_html__('Background', 'elemacy'),
                ],
                'default' => 'top',
                'selectors_dictionary' => self::CARD_IMAGE_POSITIONS,
                'selectors' => ['{{WRAPPER}} .elemacy-post-card' => '{{VALUE}}'],
            ]
        );

        $this->add_control(
            PostCardDefinition::WHOLE_CARD_LINK,
            [
                'label' => esc_html__('Link the Whole Card', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            PostCardDefinition::NEW_TAB,
            [
                'label' => esc_html__('Open Links in a New Tab', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'nothing_found_message',
            [
                'label' => esc_html__('Nothing Found Message', 'elemacy'),
                'type' => Controls_Manager::TEXTAREA,
                'separator' => 'before',
                'default' => '',
                'description' => esc_html__('Shown to visitors when the list is empty. Leave empty to show nothing.', 'elemacy'),
            ]
        );

        $this->end_controls_section();
    }

    private function card_part_rows(): Repeater
    {
        $rows = new Repeater();

        $rows->add_control(
            'part',
            [
                'label' => esc_html__('Part', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->part_labels(),
                'default' => PostCardDefinition::DEFAULT_PART,
            ]
        );

        $rows->add_control(
            'zone',
            [
                'label' => esc_html__('Place In', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    PostCardDefinition::ZONE_MEDIA_TOP => esc_html__('Over Image, Top', 'elemacy'),
                    PostCardDefinition::ZONE_MEDIA_BOTTOM => esc_html__('Over Image, Bottom', 'elemacy'),
                    PostCardDefinition::ZONE_BODY => esc_html__('Card Body', 'elemacy'),
                    PostCardDefinition::ZONE_FOOTER => esc_html__('Card Footer', 'elemacy'),
                ],
                'default' => PostCardDefinition::ZONE_BODY,
                'condition' => ['part!' => 'image'],
            ]
        );

        foreach (PostCardParts::all() as $key => $part) {
            $part->register_row_controls($rows, ['part' => $key]);
        }

        $this->register_row_overrides($rows);

        return $rows;
    }

    /**
     * Applies to this one row, on top of the part's Style section. The card
     * and part classes put it above every Style-tab rule on the same element
     * (a part's text, its links, their hover and the Button's own rules),
     * which the printed order alone would not settle.
     */
    private function register_row_overrides(Repeater $rows): void
    {
        $row = '{{WRAPPER}} .elemacy-post-card .elemacy-post-card__part{{CURRENT_ITEM}}';
        $text = $row . ', ' . $row . ' a';
        $condition = ['row_style' => 'yes'];

        $rows->add_control(
            'row_style',
            [
                'label' => esc_html__('Style This Row', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
                'separator' => 'before',
            ]
        );

        $rows->add_control(
            'row_color',
            [
                'label' => esc_html__('Color', 'elemacy'),
                'type' => Controls_Manager::COLOR,
                'condition' => $condition,
                'selectors' => [$text => 'color: {{VALUE}};'],
            ]
        );

        $rows->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'row_typography',
                'selector' => $text,
                'condition' => $condition,
            ]
        );

        $rows->add_control(
            'row_hide_on',
            [
                'label' => esc_html__('Hide On', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => [
                    'desktop' => esc_html__('Desktop', 'elemacy'),
                    'tablet' => esc_html__('Tablet', 'elemacy'),
                    'mobile' => esc_html__('Mobile', 'elemacy'),
                ],
                'default' => [],
            ]
        );
    }

    /** @return array<string, string> */
    private function part_labels(): array
    {
        $labels = [];

        foreach (PostCardParts::all() as $key => $part) {
            $labels[$key] = $part->label();
        }

        return $labels;
    }

    protected function register_card_style_sections(): void
    {
        $this->register_card_box_style_section();

        foreach (PostCardParts::all() as $part) {
            $part->register_style_section($this);
        }
    }

    private function register_card_box_style_section(): void
    {
        $card = '{{WRAPPER}} .elemacy-post-card';

        $this->start_controls_section(
            'section_card_style',
            [
                'label' => esc_html__('Card', 'elemacy'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'card_background',
                'types' => ['classic', 'gradient'],
                'selector' => $card,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => $card,
            ]
        );

        $this->add_responsive_control(
            'card_radius',
            [
                'label' => esc_html__('Border Radius', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [$card => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'card_padding',
            [
                'label' => esc_html__('Content Padding', 'elemacy'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => ['{{WRAPPER}} .elemacy-post-card__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'card_min_height',
            [
                'label' => esc_html__('Minimum Height', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 1000,
                    ],
                ],
                'selectors' => [$card => 'min-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_shadow',
                'selector' => $card,
            ]
        );

        $this->add_control(
            'card_hover_heading',
            [
                'label' => esc_html__('On Hover', 'elemacy'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_hover_shadow',
                'selector' => $card . ':hover',
            ]
        );

        $this->add_control(
            'card_hover_lift',
            [
                'label' => esc_html__('Lift', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'selectors' => [$card . ':hover' => 'transform: translateY(-{{SIZE}}{{UNIT}});'],
            ]
        );

        $this->add_control(
            'card_transition',
            [
                'label' => esc_html__('Transition Duration', 'elemacy'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['s'],
                'default' => ['unit' => 's'],
                'range' => [
                    's' => [
                        'min' => 0,
                        'max' => 2,
                        'step' => 0.05,
                    ],
                ],
                'selectors' => ['{{WRAPPER}} .elemacy-post-card' => '--elemacy-card-transition: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }
}
