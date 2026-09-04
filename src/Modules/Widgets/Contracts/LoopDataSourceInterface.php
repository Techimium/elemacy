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
}
