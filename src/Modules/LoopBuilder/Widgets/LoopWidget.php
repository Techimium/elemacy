<?php

namespace Elemacy\Modules\LoopBuilder\Widgets;

use Elemacy\Core\Container;
use Elemacy\Elementor\Preview\PreviewManager;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\LoopQueryDispatcher;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Contracts\ItemPresenterInterface;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Support\LoopLimits;
use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Access\PublicLoopLimits;
use Elemacy\Loop\Access\PublicLoopRequest;
use Elemacy\Loop\Access\PublishedLoopDefinitionResolver;
use Elemacy\Loop\Contracts\PageUrlPaginatedSourceInterface;
use Elemacy\Loop\Contracts\SharedPageSizeSourceInterface;
use Elemacy\Loop\Pagination\PageArgument;
use Elemacy\Loop\Pagination\PageLinks;
use Elemacy\Loop\Pagination\PaginatedWidgets;
use Elemacy\Loop\Pagination\PaginationRenderer;
use Elemacy\Loop\Pagination\PaginationType;
use Elemacy\Loop\Placement\PlacementResolverRegistry;
use Elemacy\Loop\Sources\Posts\PostsSettings;
use Elemacy\Loop\Rendering\ItemPlacement;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elemacy\Widgets\BaseWidget;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * What every loop widget shares: the Query section its data sources contribute
 * to, resolving this render's items under the same public policy the AJAX
 * endpoint applies, and the outer markup of a paginated list or a slider. Each
 * widget keeps its own panel and decides what renders its items.
 *
 * @internal
 */
abstract class LoopWidget extends BaseWidget
{
    /**
     * Why visitors cannot see this loop, computed only for a user who can
     * edit the owning document; null when the loop is eligible.
     *
     * @var string|null
     */
    private ?string $ineligibility_notice = null;

    /**
     * The source's own explanation for an empty editor render, if it gave one.
     *
     * @var string|null
     */
    private ?string $editor_notice = null;

    private $loop_templates;

    protected function get_loop_templates()
    {
        if ($this->loop_templates === null) {
            $this->loop_templates = Container::instance()->singleton(BlockTemplateService::class)->get_all_unbounded(
                BlockTemplateListFilterDTO::from_array(['type' => 'loop'])
            );
        }

        return $this->loop_templates;
    }

    protected function get_elementor_templates()
    {
        $options = [
            '' => esc_html__('Select Template', 'elemacy'),
        ];

        foreach ($this->get_loop_templates() as $template) {
            $options[$template->id] = $template->title;
        }

        return $options;
    }

    /**
     * The sources this widget offers, keyed by source key: every registered
     * one unless a widget narrows it.
     *
     * @return array<string, LoopDataSourceInterface>
     */
    protected function available_sources(): array
    {
        return LoopDataSourceRegistry::instance()->all();
    }

    /** The label of the page size control on this widget. */
    public function items_count_label(): string
    {
        return esc_html__('Posts Per Page', 'elemacy');
    }

    /**
     * The one page size control, shown for every offered source that reads
     * the shared page size. Called once per widget: Elementor drops a second
     * control of the same name.
     */
    protected function register_page_size_control(): void
    {
        $keys = [];

        foreach ($this->available_sources() as $key => $source) {
            if ($source instanceof SharedPageSizeSourceInterface) {
                $keys[] = (string) $key;
            }
        }

        if (!$keys) {
            return;
        }

        $control = [
            'label' => $this->items_count_label(),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => PublicLoopLimits::MAX_ITEMS,
            'default' => PostsSettings::DEFAULT_PER_PAGE,
            'condition' => ['data_source' => $keys],
        ];

        if ('' !== $this->page_size_description()) {
            $control['description'] = $this->page_size_description();
        }

        $this->add_control(PostsSettings::PER_PAGE, $control);
    }

    /** A line under the page size control; none by default. */
    protected function page_size_description(): string
    {
        return '';
    }

    /** Where the Query section holds the page size control; a widget that places it elsewhere registers nothing here. */
    protected function register_query_page_size(): void
    {
        $this->register_page_size_control();
    }

    protected function register_query_section()
    {
        $this->start_controls_section(
            'section_query',
            [
                'label' => esc_html__('Query', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $sources = $this->available_sources();

        $options = [];
        foreach ($sources as $key => $source) {
            $options[$key] = $source->get_label();
        }

        $this->add_control(
            'data_source',
            [
                'label' => esc_html__('Data Source', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'options' => $options,
                'default' => 'posts',
            ]
        );

        $this->register_query_page_size();

        // Every registered source's controls are always registered; each
        // scopes its own visibility with a `data_source` condition, the
        // same way `post_type!` conditions already worked before this.
        foreach ($sources as $source) {
            $source->register_controls($this);
        }

        $this->end_controls_section();
    }

    /**
     * The document actually rendering this widget right now — a Theme
     * Builder header/footer, popup, or loop template document Elementor has
     * switched to, not the viewed page (get_the_ID()), when this widget
     * lives in one of those.
     */
    protected function current_owner_id(): int
    {
        $document = Plugin::$instance->documents->get_current();

        return $document ? (int) $document->get_main_id() : (int) get_the_ID();
    }

    /**
     * Applies the same public eligibility policy the AJAX endpoint enforces to
     * this widget's own classic output, so an authored template/source that
     * has since become inaccessible cannot appear there either.
     *
     * @param array                   $settings
     * @param LoopDataSourceInterface $source
     * @param int                     $page The page of the loop this render shows.
     * @return LoopResultDTO
     */
    protected function items_for_display(array $settings, LoopDataSourceInterface $source, int $page = 1): LoopResultDTO
    {
        if (Plugin::instance()->editor->is_edit_mode()) {
            if (!$source instanceof PageUrlPaginatedSourceInterface) {
                $settings['paged'] = $page;
            }

            $result = LoopQueryDispatcher::items($source, $settings, $page);
            $this->editor_notice = $result->notice;

            return $result;
        }

        $policy = Container::instance()->singleton(PublicRenderPolicy::class);
        $owner_id = $this->current_owner_id();

        try {
            return $policy->eligible_items($this->item_presenter(), $settings, $owner_id, $page);
        } catch (ValidationException $error) {
            if (current_user_can('edit_post', $owner_id)) {
                $this->ineligibility_notice = $policy->describe_ineligibility($this->item_presenter(), $settings);
            }

            return new LoopResultDTO([], 0, 0, false);
        }
    }

    /**
     * In the editor, a loop inside a Loop Item template renders inside that
     * template's preview item, as it will render nested inside real items on
     * the page — on the editor's first render and when re-rendered alone.
     */
    protected function render()
    {
        // One more loop item inside this many would pass the nesting limit:
        // render nothing, and tell an editor why.
        if (count(LoopContext::frames()) >= LoopLimits::MAX_DEPTH) {
            if (Plugin::instance()->editor->is_edit_mode() || current_user_can('edit_post', $this->current_owner_id())) {
                /* translators: %d: the maximum number of loops inside one another */
                echo '<div class="elemacy-alert elemacy-alert-warning">' . esc_html(sprintf(__('This loop is nested more than %d levels deep, so it is not shown.', 'elemacy'), LoopLimits::MAX_DEPTH)) . '</div>';
            }

            return;
        }

        if (!Plugin::instance()->editor->is_edit_mode()) {
            $this->render_loop();

            return;
        }

        Container::instance()->singleton(PreviewManager::class)->within_preview_item(
            $this->current_owner_id(),
            function (): void {
                $this->render_loop();
            }
        );
    }

    /**
     * Prints a paginated list: its items, then its pagination.
     *
     * @param array                   $settings
     * @param LoopDataSourceInterface $source
     * @param LoopResultDTO           $result
     * @param int                     $page       The page this render shows.
     * @param string                  $list_class Extra classes for the element that holds the items.
     */
    protected function render_paginated_list(array $settings, LoopDataSourceInterface $source, LoopResultDTO $result, int $page, string $list_class = ''): void
    {
        $wrapper_classes = 'elemacy-loop-builder-grid';
        $wrapper_attrs = '';
        $max_pages = PublicLoopLimits::effective_max_pages($settings, $result->max_num_pages);

        if (PublicLoopLimits::is_ajax_paginated($settings)) {
            $wrapper_classes .= ' elemacy-ajax-pagination';
            $wrapper_attrs .= $source instanceof PageUrlPaginatedSourceInterface
                ? $this->page_url_attrs()
                : $this->ajax_request_attrs($max_pages > 1);
            $wrapper_attrs .= $this->pagination_style_attrs($settings, $page);
        }

        echo '<div class="' . esc_attr($wrapper_classes) . '"' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_attrs is built with esc_attr; remaining HTML structure is hardcoded
        echo '<div class="' . esc_attr(trim('elemacy-loop-grid ' . $list_class)) . '">';

        $this->render_items($settings, $result, $page);

        echo '</div>';

        $pagination = Container::instance()->singleton(PaginationRenderer::class);

        if (!empty($settings['pagination_type']) && $max_pages > 1) {
            $pagination->render($settings, $max_pages, $page, $this->page_link_args($settings, $source));
        }

        if (PaginationType::is_incremental($settings['pagination_type'] ?? '')) {
            $pagination->render_incremental_status($settings);
        }

        echo '</div>'; // End elemacy-loop-builder-grid.
    }

    /** Prints a slider of the items, with its arrows and dots. */
    protected function render_slider(array $settings, LoopResultDTO $result): void
    {
        echo '<div class="elemacy-loop-carousel-container">';
        echo '<div class="swiper elemacy-loop-carousel">';
        echo '<div class="swiper-wrapper">';

        $this->render_items($settings, $result, 1, '<div class="swiper-slide">', '</div>');

        echo '</div>'; // End swiper-wrapper.

        // Render Navigation Elements.
        if (in_array($settings['navigation'], ['both', 'pagination'], true)) {
            echo '<div class="swiper-pagination"></div>';
        }

        if (in_array($settings['navigation'], ['both', 'arrows'], true)) {
            echo '<div class="elemacy-swiper-button elemacy-swiper-button-prev swiper-button-prev"><i class="eicon-chevron-left"></i></div>';
            echo '<div class="elemacy-swiper-button elemacy-swiper-button-next swiper-button-next"><i class="eicon-chevron-right"></i></div>';
        }

        echo '</div>'; // End swiper element.
        echo '</div>'; // End elemacy-loop-carousel-container.
    }

    private function render_items(array $settings, LoopResultDTO $result, int $page, string $before_item = '', string $after_item = ''): void
    {
        $presenter = $this->item_presenter();
        $placement = $this->current_owner_id() . '-' . $this->get_id();
        $source_key = $settings['data_source'] ?? 'posts';

        foreach ($result->items as $occurrence => $item) {
            echo $before_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded markup from this class.
            $presenter->render_item($item, $settings, new ItemPlacement($placement, $source_key, $occurrence, '', $page));
            echo $after_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded markup from this class.
        }
    }

    /**
     * How the frontend script presents the loop's later pages: `load_more`,
     * `infinite_scroll`, or `numbers` for any link type. Also the page this
     * render shows, which an incremental loop continues from.
     *
     * @param array $settings
     * @return string
     */
    protected function pagination_style_attrs(array $settings, int $page): string
    {
        $type = $settings['pagination_type'] ?? '';
        $style = PaginationType::is_incremental($type) ? $type : 'numbers';

        return sprintf(' data-elemacy-loop-style="%s" data-elemacy-loop-page="%d"', esc_attr($style), $page);
    }

    /**
     * The page of this loop the visitor asked for, for a paginated loop, and
     * page one for a loop without pagination (which shows the same items on
     * every URL of its page). It is the page of the site's paginated URL the
     * visitor opened (`/page/N/` on an archive, `/slug/N/` on a singular
     * page), or, for a widget that pages through its own argument, the
     * widget's `elemacy-page-<id>`, which no other widget shares.
     *
     * @param array                   $settings
     * @param LoopDataSourceInterface $source
     * @return int
     */
    protected function requested_page(array $settings, LoopDataSourceInterface $source): int
    {
        if (empty($settings['pagination_type'])) {
            return 1;
        }

        $site_page = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

        // A page-URL source's pages belong to the site: its limit only caps
        // the pagination it offers.
        if ($source instanceof PageUrlPaginatedSourceInterface) {
            return $site_page;
        }

        if (PaginatedWidgets::uses_own_argument($this->current_owner_id(), $settings)) {
            return PublicLoopLimits::effective_max_pages($settings, PageArgument::read($this->get_id()));
        }

        return PublicLoopLimits::effective_max_pages($settings, $site_page);
    }

    /**
     * Where this render's page links point, as `base` and `format` for
     * paginate_links(): the widget's own argument on the current URL, the
     * viewed post's own paged URLs on a singular page, and null for the
     * site's `/page/N/` URLs (an archive, blog home or search, and a
     * page-URL source everywhere).
     *
     * @param array                   $settings
     * @param LoopDataSourceInterface $source
     * @return array|null
     */
    protected function page_link_args(array $settings, LoopDataSourceInterface $source): ?array
    {
        if ($source instanceof PageUrlPaginatedSourceInterface) {
            return null;
        }

        if (PaginatedWidgets::uses_own_argument($this->current_owner_id(), $settings)) {
            return PageLinks::argument(PageArgument::current_url(), $this->get_id());
        }

        return is_singular() && get_queried_object_id() ? PageLinks::singular(get_queried_object_id()) : null;
    }

    /**
     * Marks a page-URL-paginated loop (Current Query) for the frontend script:
     * it fetches the clicked pagination link's own URL and swaps this loop,
     * found again in the response by this identity. Nothing here is a
     * request the server replays, so there is no signed payload.
     */
    protected function page_url_attrs(): string
    {
        return sprintf(
            ' data-elemacy-loop-mode="page" data-elemacy-loop-id="%s"',
            esc_attr($this->current_owner_id() . '-' . $this->get_id())
        );
    }

    /**
     * The signed request envelope subsequent AJAX pages replay: owner/widget
     * identity and definition revision, resolved and signed the same way
     * PublishedLoopDefinitionResolver/PaginationContextSigner verify them,
     * plus an independently-verified host-context token when a contributed
     * placement resolver recognizes the current request. A loop with no next
     * page never makes a request, so it carries no signed context (inside a
     * repeater row that token would otherwise repeat once per row).
     *
     * @param bool $has_next_page
     */
    protected function ajax_request_attrs(bool $has_next_page = true): string
    {
        try {
            $policy = Container::instance()->singleton(PublicRenderPolicy::class);
            $definition = Container::instance()->singleton(PublishedLoopDefinitionResolver::class)->find($this->current_owner_id(), $this->get_id());

            $context = '';
            $placement = $has_next_page ? PlacementResolverRegistry::instance()->resolve_current($definition->owner_id()) : null;
            if (null !== $placement) {
                $context = Container::instance()->singleton(PaginationContextSigner::class)->sign(
                    $definition->owner_id(),
                    $definition->widget_id(),
                    $definition->fingerprint(),
                    $placement['type'],
                    $placement['facts']
                );
            }

            $payload = wp_json_encode([
                'version' => PublicLoopRequest::VERSION,
                'owner_id' => $definition->owner_id(),
                'widget_id' => $definition->widget_id(),
                'page' => 1,
                'fingerprint' => $definition->fingerprint(),
                'context' => $context,
            ]);
        } catch (ValidationException $error) {
            return '';
        }

        return sprintf(
            ' data-elemacy-loop-request="%s" data-elemacy-loop-base="%s"%s',
            esc_attr($payload),
            esc_attr($this->page_base_template()),
            $this->page_mode_attrs()
        );
    }

    /**
     * The page URL the endpoint's pagination links are built from, with `%#%`
     * where the page number goes: the widget's own argument on the current
     * URL, the viewed post's own paged URL on a singular page, and the site's
     * `/page/N/` otherwise. Cosmetic (the endpoint takes it only as a URL of
     * this site), so the links it returns equal the classic render's.
     */
    private function page_base_template(): string
    {
        if (PaginatedWidgets::uses_own_argument($this->current_owner_id(), $this->get_settings_for_display())) {
            $url = PageArgument::url(PageArgument::current_url(), $this->get_id(), 1);

            return $url . (false === strpos($url, '?') ? '?' : '&') . PageArgument::name($this->get_id()) . '=%#%';
        }

        if (is_singular() && get_queried_object_id()) {
            $links = PageLinks::singular(get_queried_object_id());

            return str_replace('%_%', $links['format'], $links['base']);
        }

        return str_replace('999999999', '%#%', get_pagenum_link(999999999, false));
    }

    /** What the script needs to read a page number out of this widget's links. */
    private function page_mode_attrs(): string
    {
        if (PaginatedWidgets::uses_own_argument($this->current_owner_id(), $this->get_settings_for_display())) {
            return sprintf(' data-elemacy-loop-page-arg="%s"', esc_attr(PageArgument::name($this->get_id())));
        }

        return is_singular() ? ' data-elemacy-loop-singular="1"' : '';
    }

    /** What this widget renders each of its items with. */
    abstract protected function item_presenter(): ItemPresenterInterface;

    /** The widget's own markup: layout, items and pagination. */
    abstract protected function render_loop(): void;

    /** What a render with no items shows: an editor hint, or why visitors see nothing. */
    protected function print_empty_notice(): void
    {
        if (Plugin::instance()->editor->is_edit_mode()) {
            $notice = null !== $this->editor_notice ? $this->editor_notice : __('No items found matching this query.', 'elemacy');
            echo '<div class="elemacy-alert elemacy-alert-info">' . esc_html($notice) . '</div>';
        } elseif (null !== $this->ineligibility_notice) {
            echo '<div class="elemacy-loop-notice" role="status">' . esc_html($this->ineligibility_notice) . '</div>';
        }
    }
}
