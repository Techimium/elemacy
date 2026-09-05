<?php

namespace Elemacy\Modules\Widgets\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\DTO\LoopResultDTO;
use Elementor\Widget_Base;

/**
 * Supplies the items a Loop Grid / Loop Carousel widget renders. Implement
 * this and register an instance on LoopDataSourceRegistry (typically via
 * Hooks::LOOP_DATA_SOURCES_REGISTER_ACTION) to make a new kind of loop item
 * source selectable, with zero changes to the widgets themselves.
 */
interface LoopDataSourceInterface
{
    /**
     * Stable identifier stored in the widget's `data_source` setting.
     *
     * @return string
     */
    public function get_key(): string;

    /**
     * Label shown for this source in the Data Source control.
     *
     * @return string
     */
    public function get_label(): string;

    /**
     * Adds this source's own Query section controls to the widget, each
     * scoped with `condition => ['data_source' => $this->get_key()]` (in
     * addition to whatever conditions the control itself needs) so it is
     * only visible when this source is selected.
     *
     * @param Widget_Base $widget
     * @return void
     */
    public function register_controls(Widget_Base $widget): void;

    /**
     * Resolves this source's items for one widget render, from that
     * widget's settings array.
     *
     * @param array $settings
     * @return LoopResultDTO
     */
    public function get_items(array $settings): LoopResultDTO;

    /**
     * Builds the settings subset (plus any ambient "current" context this
     * source needs, e.g. the current post/term ID) to round-trip to the
     * browser and back for AJAX pagination. Called from LoopGrid::render()/
     * LoopCarousel::render() when building the data-elemacy-loop-settings
     * payload; only the keys this source actually needs should be returned,
     * not the widget's full settings array.
     *
     * @param array $settings
     * @return array
     */
    public function get_ajax_payload(array $settings): array;

    /**
     * Validates and normalizes a decoded AJAX pagination request's settings
     * — attacker-controlled, since they round-tripped through the browser —
     * into the shape get_items() expects for the given page. Implementations
     * SHOULD throw \Elemacy\Core\Exceptions\ValidationException for invalid
     * input, mirroring how AjaxPaginationController validated input directly
     * before this method existed.
     *
     * @param array $raw_settings
     * @param int $paged
     * @return array
     */
    public function sanitize_ajax_settings(array $raw_settings, int $paged): array;
}
