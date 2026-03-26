<?php

namespace Elemacy\Modules\Widgets\Services;

use Elemacy\Support\Utils;

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
        $service = new WidgetService();
        $statuses = $service->get_registered_widgets_status();
        $widgets_config = require Utils::get_plugin_path('src/Modules/Widgets/Config/widgets.php');

        foreach ($widgets_config as $widget) {
            $name = $widget['name'];

            if (!empty($statuses[$name])) {
                $class = $widget['class'];
                $widgets_manager->register(new $class());
            }
        }
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
