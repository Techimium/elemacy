<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elementor\Widget_Base;

/**
 * The Elementor-controls half of a loop data source (design.md D7): adding
 * this source's own settings to a Loop Grid/Carousel widget's Query section.
 */
interface LoopSourceControlsInterface
{
    /**
     * Adds this source's own Query section controls to the widget, each
     * scoped with `condition => ['data_source' => $key]` (in addition to
     * whatever conditions the control itself needs) so it is only visible
     * when this source is selected.
     *
     * @param Widget_Base $widget
     * @return void
     */
    public function register_controls(Widget_Base $widget): void;
}
