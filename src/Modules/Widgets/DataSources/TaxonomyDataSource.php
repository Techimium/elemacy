<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Controls\Query\QueryControl;
use Elemacy\Elementor\Controls\Query\QueryService;
use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Sources\TermsQuery;
use Elemacy\Loop\Items\TermLoopItem;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Pagination\Policies\TermsPolicy;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elementor\Controls_Manager;
use Elemacy\Elementor\Widgets\BaseWidget;
use WP_Term;
use WP_Term_Query;

/**
 * Built-in Taxonomy Terms data source: loops over a chosen taxonomy's terms
 * instead of posts, so a Loop Grid/Carousel can build a "browse by category"
 * grid or similar.
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

    public function register_controls(BaseWidget $widget): void
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
                'type' => QueryControl::TYPE,
                'label_block' => true,
                'placeholder' => esc_html__('Auto (first term)', 'elemacy'),
                'autocomplete' => [
                    'object' => QueryService::OBJECT_TERM,
                    'query' => [],
                ],
                'default' => '',
                'description' => esc_html__("Leave empty to preview the selected taxonomy's first term.", 'elemacy'),
                'condition' => $condition,
            ]
        );
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
