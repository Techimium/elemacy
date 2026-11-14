<?php

namespace Elemacy\Modules\LoopBuilder\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Support\Taxonomies;
use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Contracts\SharedPageSizeSourceInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Sources\RelatedPosts\RelatedPostsPolicy;
use Elemacy\Loop\Sources\RelatedPosts\RelatedPostsQuery;
use Elemacy\Loop\Sources\RelatedPosts\RelatedPostsSettings;
use Elemacy\Widgets\BaseWidget;
use Elementor\Controls_Manager;

/**
 * Other posts of the current post's type that share a term with it.
 *
 * @internal
 */
class RelatedPostsDataSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface, SharedPageSizeSourceInterface
{
    protected RelatedPostsQuery $query;
    protected RelatedPostsPolicy $policy;
    protected LoopDataSourceInterface $preview;

    public function __construct(?RelatedPostsQuery $query = null, ?RelatedPostsPolicy $policy = null, ?LoopDataSourceInterface $preview = null)
    {
        $this->query = $query ?? new RelatedPostsQuery();
        $this->policy = $policy ?? new RelatedPostsPolicy();
        $this->preview = $preview ?? new PostsDataSource();
    }

    public function get_key(): string
    {
        return 'related_posts';
    }

    public function get_label(): string
    {
        return esc_html__('Related Posts', 'elemacy');
    }

    public function register_controls(BaseWidget $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            RelatedPostsSettings::TAXONOMIES,
            [
                'label' => esc_html__('Related By', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => true,
                'options' => $this->taxonomy_options(),
                'default' => [],
                'description' => esc_html__('Leave empty to use every taxonomy of the current post.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            RelatedPostsSettings::ORDER_BY,
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => RelatedPostsSettings::DEFAULT_ORDER_BY,
                'options' => [
                    'date' => esc_html__('Date', 'elemacy'),
                    'modified' => esc_html__('Date Modified', 'elemacy'),
                    'title' => esc_html__('Title', 'elemacy'),
                    'comment_count' => esc_html__('Comment Count', 'elemacy'),
                    'menu_order' => esc_html__('Menu Order', 'elemacy'),
                    'rand' => esc_html__('Random', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            RelatedPostsSettings::ORDER,
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => RelatedPostsSettings::DEFAULT_ORDER,
                'options' => [
                    'DESC' => esc_html__('Descending', 'elemacy'),
                    'ASC' => esc_html__('Ascending', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            RelatedPostsSettings::FALLBACK,
            [
                'label' => esc_html__('Show Recent Posts If None', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => RelatedPostsSettings::DEFAULT_FALLBACK,
                'condition' => $condition,
            ]
        );
    }

    /** @return array<string, string> */
    private function taxonomy_options(): array
    {
        $options = [];

        foreach (Taxonomies::public_objects() as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->label;
        }

        return $options;
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return $this->query->get_items($settings);
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        return $this->policy->get_public_settings($settings, $context, $page);
    }

    public function register_preview_controls(PreviewablePageBase $document): void
    {
        // A Loop Item template previews any post the same way: the Posts source owns that control.
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        return $this->preview->resolve_preview_item([]);
    }
}
