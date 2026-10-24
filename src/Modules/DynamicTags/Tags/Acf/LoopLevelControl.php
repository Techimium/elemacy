<?php

namespace Elemacy\Modules\DynamicTags\Tags\Acf;

use Elemacy\Loop\LoopContext;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * The ACF tags' "Loop Level" choice: read the field from the current loop item
 * (the default, unchanged behavior) or from an enclosing one, e.g. a section
 * row's title inside one of its item cards.
 */
trait LoopLevelControl
{
    protected function register_loop_level_control(): void
    {
        $this->add_control(
            'loop_level',
            [
                'label' => esc_html__('Loop Level', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => '0',
                'options' => [
                    '0' => esc_html__('Current Item', 'elemacy'),
                    '1' => esc_html__('Parent Item', 'elemacy'),
                    '2' => esc_html__('2 Levels Up', 'elemacy'),
                    '3' => esc_html__('3 Levels Up', 'elemacy'),
                ],
                'description' => esc_html__('Inside a nested loop, read this field from an enclosing loop item instead.', 'elemacy'),
            ]
        );
    }

    /**
     * @param callable $read Reads the field in the current native context.
     * @return mixed $read's value at the chosen level, or null when there is no item that far up.
     */
    protected function read_at_loop_level(callable $read)
    {
        $level = max(0, min(3, (int) $this->get_settings('loop_level')));

        return LoopContext::at_level($level, $read);
    }
}
