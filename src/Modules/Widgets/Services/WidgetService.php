<?php

namespace Elemacy\Modules\Widgets\Services;

use Elemacy\Modules\Widgets\DTO\WidgetDTO;
use Elemacy\Support\Utils;
use Exception;

class WidgetService
{
    /**
     * Get the list of widgets with their current status
     *
     * @return WidgetDTO[]
     */
    public function get_all_widgets()
    {
        $widgets_config = require dirname(__DIR__, 3) . '/Config/widgets.php';
        $statuses = $this->get_widget_statuses();

        $widgets = [];

        foreach ($widgets_config as $widget) {
            $widgets[] = $this->create_dto($widget, $statuses);
        }

        return $widgets;
    }

    /**
     * Format widget data for API response.
     *
     * @param array $widget
     * @param array $statuses
     * @return WidgetDTO
     */
    protected function create_dto($widget, $statuses)
    {
        $dto = new WidgetDTO();
        $dto->name = $widget['name'];
        $dto->title = $widget['title'];
        $dto->icon = $widget['icon'];
        $dto->class = $widget['class'];
        $dto->is_enabled = isset($statuses[$dto->name]) ? filter_var($statuses[$dto->name], FILTER_VALIDATE_BOOLEAN) : true;

        return $dto;
    }

    /**
     * Get array of widget statuses (name => is_enabled) for registration
     *
     * @return array
     */
    public function get_registered_widgets_status()
    {
        $widgets_config = require dirname(__DIR__, 3) . '/Config/widgets.php';
        $statuses = $this->get_widget_statuses();

        $result = [];

        foreach ($widgets_config as $widget) {
            $name = $widget['name'];
            $result[$name] = isset($statuses[$name]) ? filter_var($statuses[$name], FILTER_VALIDATE_BOOLEAN) : true;
        }

        return $result;
    }

    /**
     * Toggle a widget's status
     *
     * @param string $name
     * @param string $action
     * @return bool
     * @throws Exception
     */
    public function toggle_widget(string $name, string $action)
    {
        $widgets_config = require dirname(__DIR__, 3) . '/Config/widgets.php';
        $widget_exists = false;

        foreach ($widgets_config as $widget) {
            if ($widget['name'] === $name) {
                $widget_exists = true;
                break;
            }
        }

        if (!$widget_exists) {
            throw new Exception(esc_html__('Widget not found', 'elemacy'));
        }

        $statuses = $this->get_widget_statuses();
        $statuses[$name] = ($action === 'enable');

        update_option(Utils::with_prefix('widgets'), $statuses);

        return true;
    }

    /**
     * Get array of widget statuses (name => is_enabled)
     * 
     * @return array
     */
    protected function get_widget_statuses()
    {
        return get_option(Utils::with_prefix('widgets'), []);
    }
}
