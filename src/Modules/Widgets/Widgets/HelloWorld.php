<?php

namespace Elemacy\Modules\Widgets\Widgets;

use Elementor\Controls_Manager;

class HelloWorld extends BaseWidget
{
    public function get_name()
    {
        return 'elemacy_hello_world';
    }

    public function get_title()
    {
        return esc_html__('Hello World', 'elemacy');
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Content', 'elemacy'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => esc_html__('Title', 'elemacy'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Hello World!', 'elemacy'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        echo '<div class="elemacy-hello-world">';
        echo '<h2>' . esc_html($settings['title']) . '</h2>';
        echo '</div>';
    }
}
