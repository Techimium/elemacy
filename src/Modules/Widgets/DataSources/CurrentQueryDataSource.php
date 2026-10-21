<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Elementor\Widgets\BaseWidget;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PageUrlPaginatedSourceInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Sources\CurrentQuery\CurrentQueryQuery;
use Elementor\Controls_Manager;

/**
 * Built-in Current Query data source: lists exactly what the page's own main
 * query returned, so a loop in an archive, search, shop, blog or singular
 * template shows what that page shows without the author knowing the query in
 * advance. Later pages are the page's own paginated URLs (see
 * PageUrlPaginatedSourceInterface), so nothing is rebuilt and nothing the
 * browser sends can widen the result.
 */
class CurrentQueryDataSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface, PageUrlPaginatedSourceInterface
{
    protected CurrentQueryQuery $query;
    protected LoopDataSourceInterface $preview;

    /**
     * @param CurrentQueryQuery|null       $query   Runs the main-query listing.
     * @param LoopDataSourceInterface|null $preview Supplies the standalone preview
     *                                              item; defaults to the Posts
     *                                              source's most-recent-post fallback.
     */
    public function __construct(?CurrentQueryQuery $query = null, ?LoopDataSourceInterface $preview = null)
    {
        $this->query = $query ?? new CurrentQueryQuery();
        $this->preview = $preview ?? new PostsDataSource();
    }

    public function get_key(): string
    {
        return 'current_query';
    }

    public function get_label(): string
    {
        return esc_html__('Current Query', 'elemacy');
    }

    public function register_controls(BaseWidget $widget): void
    {
        $widget->add_control(
            'current_query_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => esc_html__('Lists the posts of the page this widget is on (an archive, search, shop, blog or single page), with that page\'s own order and page size. Loops on one page share the page number.', 'elemacy'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
                'condition' => ['data_source' => $this->get_key()],
            ]
        );
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return $this->query->get_items($settings);
    }

    /**
     * Nothing to validate or carry: the page of results is whatever page the
     * visitor requested, and WordPress's main query already holds it. No
     * authored setting is read, so none reaches the query.
     *
     * {@inheritDoc}
     */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        return [];
    }

    public function register_preview_controls(PreviewablePageBase $document): void
    {
        // The page decides the posts, so there is nothing to choose.
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        return $this->preview->resolve_preview_item([]);
    }
}
