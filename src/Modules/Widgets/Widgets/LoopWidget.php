<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elemacy\Core\Container;
use Elemacy\Elementor\Preview\PreviewManager;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopDataSourceRegistry;
use Elemacy\Loop\LoopQueryDispatcher;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Support\LoopLimits;
use Elemacy\TemplateLibrary\DTO\BlockTemplateListFilterDTO;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elemacy\Elementor\Widgets\BaseWidget;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * What Loop Grid and Loop Carousel share: the loop template choice, the
 * Query section every registered data source contributes to, and resolving
 * this render's items under the same public policy the AJAX endpoint applies.
 * Each widget keeps its own layout, pagination and render markup.
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

    protected function register_query_section()
    {
        $this->start_controls_section(
            'section_query',
            [
                'label' => esc_html__('Query', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $registry = LoopDataSourceRegistry::instance();

        $options = [];
        foreach ($registry->all() as $key => $source) {
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

        // Every registered source's controls are always registered; each
        // scopes its own visibility with a `data_source` condition, the
        // same way `post_type!` conditions already worked before this.
        foreach ($registry->all() as $source) {
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
            $result = LoopQueryDispatcher::items($source, $settings, $page);
            $this->editor_notice = $result->notice;

            return $result;
        }

        $policy = Container::instance()->singleton(PublicRenderPolicy::class);
        $owner_id = $this->current_owner_id();

        try {
            return $policy->eligible_items($settings, $owner_id, $page);
        } catch (ValidationException $error) {
            if (current_user_can('edit_post', $owner_id)) {
                $this->ineligibility_notice = $policy->describe_ineligibility($settings);
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
