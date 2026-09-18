<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DataSources\TermsQuery;
use Elemacy\Loop\LoopItems\TermLoopItem;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Pagination\Policies\TermsPolicy;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use WP_Term;
use WP_Term_Query;

/**
 * Built-in Taxonomy Terms data source: loops over a chosen taxonomy's terms
 * instead of posts, so a Loop Grid/Carousel can build a "browse by category"
 * grid or similar. See openspec/changes/loop-data-source-taxonomy/design.md
 * for the term-meta resolution, no-op enter()/exit(), and pagination-total
 * decisions.
 *
 * The runtime query execution and public-context policy live in
 * Loop\DataSources\TermsQuery / Loop\Pagination\Policies\TermsPolicy
 * (design.md D7) — this class keeps only what needs an Elementor
 * widget/document instance plus this source's own identity, delegating
 * everything else to those two collaborators.
 */
class TaxonomyDataSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface
{
    protected TermsQuery $query;
    protected TermsPolicy $policy;

    public function __construct(?TermsQuery $query = null, ?TermsPolicy $policy = null)
    {
        $this->query = $query ?? new TermsQuery();
        $this->policy = $policy ?? new TermsPolicy($this->query);
    }

    public function get_key(): string
    {
        return 'terms';
    }

    public function get_label(): string
    {
        return esc_html__('Terms', 'elemacy');
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        return $this->policy->get_public_settings($settings, $context, $page);
    }

    protected function get_public_taxonomies(): array
    {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options = [];

        foreach ($taxonomies as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->label;
        }

        return $options;
    }

    public function register_controls(Widget_Base $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            'taxonomy',
            [
                'label' => esc_html__('Taxonomy', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_public_taxonomies(),
                'default' => 'category',
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'terms_number',
            [
                'label' => esc_html__('Number of Terms', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'description' => esc_html__('Leave empty to show all terms.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'terms_orderby',
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'name',
                'options' => [
                    'name' => esc_html__('Name', 'elemacy'),
                    'count' => esc_html__('Count', 'elemacy'),
                    'term_id' => esc_html__('Term ID', 'elemacy'),
                    'slug' => esc_html__('Slug', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'terms_order',
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => [
                    'ASC' => esc_html__('ASC', 'elemacy'),
                    'DESC' => esc_html__('DESC', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'hide_empty',
            [
                'label' => esc_html__('Hide Empty Terms', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'exclude_current_term',
            [
                'label' => esc_html__('Exclude Current Term', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => esc_html__('When viewing a term archive, exclude that term from the results.', 'elemacy'),
                'condition' => $condition,
            ]
        );
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return $this->query->get_items($settings);
    }

    public function get_ajax_payload(array $settings): array
    {
        $payload = [
            'taxonomy' => $settings['taxonomy'] ?? 'category',
            'terms_number' => $settings['terms_number'] ?? 6,
            'terms_orderby' => $settings['terms_orderby'] ?? 'name',
            'terms_order' => $settings['terms_order'] ?? 'ASC',
            'hide_empty' => $settings['hide_empty'] ?? 'yes',
            'exclude_current_term' => $settings['exclude_current_term'] ?? '',
        ];

        if ('yes' === ($settings['exclude_current_term'] ?? '')) {
            $current_term_id = $this->query->get_current_queried_term_id();

            if ($current_term_id) {
                $payload['current_term_id'] = $current_term_id;
            }
        }

        return $payload;
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        return $this->query->sanitize_ajax_settings($raw_settings, $paged);
    }

    public function register_preview_controls(PreviewablePageBase $document): void
    {
        $condition = ['preview_data_source' => $this->get_key()];

        $document->add_control(
            'preview_taxonomy',
            [
                'label' => esc_html__('Taxonomy', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_public_taxonomies(),
                'default' => 'category',
                'condition' => $condition,
            ]
        );

        $document->add_control(
            'preview_term',
            [
                'label' => esc_html__('Term', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'options' => $this->get_preview_term_options(),
                'default' => '',
                'placeholder' => esc_html__('Auto (first term)', 'elemacy'),
                'description' => esc_html__("Leave empty to preview the selected taxonomy's first term.", 'elemacy'),
                'condition' => $condition,
            ]
        );
    }

    /**
     * Capped, server-populated SELECT2 (same accepted pattern as
     * AcfRepeaterDataSource::get_post_options() — Elementor core exposes no
     * reusable AJAX term-search endpoint a custom control can hook into).
     * Spans every public taxonomy, prefixed by taxonomy label, since the
     * option list can't reactively re-filter based on the sibling Taxonomy
     * control's value.
     */
    protected function get_preview_term_options(): array
    {
        $terms = get_terms([
            'taxonomy' => array_keys($this->get_public_taxonomies()),
            'hide_empty' => false,
            'number' => 200,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $options = [];

        foreach ($terms as $term) {
            $taxonomy = get_taxonomy($term->taxonomy);
            $taxonomy_label = $taxonomy ? $taxonomy->labels->singular_name : $term->taxonomy;
            $options[$term->term_id] = sprintf('%s: %s', $taxonomy_label, $term->name);
        }

        return $options;
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        $selected = (int) ($settings['preview_term'] ?? 0);

        if ($selected > 0) {
            $term = get_term($selected);

            if ($term instanceof WP_Term) {
                return new TermLoopItem($term);
            }
        }

        $terms = (new WP_Term_Query([
            'taxonomy' => $settings['preview_taxonomy'] ?? 'category',
            'hide_empty' => false,
            'number' => 1,
        ]))->get_terms();

        return $terms ? new TermLoopItem($terms[0]) : null;
    }
}
