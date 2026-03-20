<?php

namespace Elemacy\Modules\Widgets\Services;

use Elemacy\Modules\Widgets\Widgets\NavMenu;

class WidgetManager
{
    protected static $instance = null;

    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    protected function __construct()
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'register_categories']);
    }

    public function register_widgets($widgets_manager)
    {
        $widgets_manager->register(new NavMenu());
        $widgets_manager->register(new \Elemacy\Modules\Widgets\Widgets\LoopBuilder());
    }

    public function register_categories($elements_manager)
    {
        $elements_manager->add_category(
            'elemacy',
            [
                'title' => esc_html__('Elemacy', 'elemacy'),
                'icon' => 'fa fa-plug',
            ]
        );
    }
}
