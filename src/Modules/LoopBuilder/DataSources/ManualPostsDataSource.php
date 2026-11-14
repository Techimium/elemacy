<?php

namespace Elemacy\Modules\LoopBuilder\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Controls\Query\QueryControl;
use Elemacy\Elementor\Controls\Query\QueryService;
use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Contracts\SharedPageSizeSourceInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Sources\ManualPosts\ManualPostsPolicy;
use Elemacy\Loop\Sources\ManualPosts\ManualPostsQuery;
use Elemacy\Loop\Sources\ManualPosts\ManualPostsSettings;
use Elemacy\Support\PostTypes;
use Elemacy\Widgets\BaseWidget;

/**
 * Posts an editor picks by hand, from any public post type, in the picked order.
 *
 * @internal
 */
class ManualPostsDataSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface, SharedPageSizeSourceInterface
{
    protected ManualPostsQuery $query;
    protected ManualPostsPolicy $policy;
    protected LoopDataSourceInterface $preview;

    public function __construct(?ManualPostsQuery $query = null, ?ManualPostsPolicy $policy = null, ?LoopDataSourceInterface $preview = null)
    {
        $this->query = $query ?? new ManualPostsQuery();
        $this->policy = $policy ?? new ManualPostsPolicy();
        $this->preview = $preview ?? new PostsDataSource();
    }

    public function get_key(): string
    {
        return 'manual_posts';
    }

    public function get_label(): string
    {
        return esc_html__('Manual Selection', 'elemacy');
    }

    public function register_controls(BaseWidget $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            ManualPostsSettings::IDS,
            [
                'label' => esc_html__('Posts', 'elemacy'),
                'type' => QueryControl::TYPE,
                'label_block' => true,
                'multiple' => true,
                'autocomplete' => [
                    'object' => QueryService::OBJECT_POST,
                    'query' => ['post_type' => PostTypes::public_names()],
                ],
                'default' => [],
                'description' => esc_html__('Posts appear in the order you pick them.', 'elemacy'),
                'condition' => $condition,
            ]
        );
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
