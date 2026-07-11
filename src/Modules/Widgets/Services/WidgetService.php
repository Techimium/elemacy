<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\NotFoundException;
use Elemacy\Modules\Widgets\DTO\WidgetDTO;
use Elemacy\Support\Utils;

class WidgetService
{
    /**
     * Gets the list of widgets with their current status.
     *
     * @return WidgetDTO[]
     */
    public function get_all_widgets()
    {
        $widgets_config = $this->get_available_widgets();
        $statuses       = $this->get_widget_statuses();

        $widgets = [];

        foreach ($widgets_config as $widget) {
            $widgets[] = $this->create_dto($widget, $statuses);
        }

        return $widgets;
    }

    /**
     * Return only widgets whose runtime requirements (e.g. ACF) are satisfied.
     * Used as the single source of truth for both Elementor registration and
     * the admin toggle list, so unavailable widgets stay hidden everywhere.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_available_widgets(): array
    {
        $widgets_config = require Utils::get_plugin_path('src/Modules/Widgets/Config/widgets.php');

        return array_values(array_filter(
            $widgets_config,
            fn($widget) => $this->meets_requirements($widget)
        ));
    }

    /**
     * Whether a widget's runtime requirements (e.g. ACF) are currently satisfied.
     *
     * @param array<string, mixed> $widget The widget config entry.
     * @return bool
     */
    private function meets_requirements(array $widget): bool
    {
        $requires = $widget['requires'] ?? null;

        if ($requires === 'acf') {
            return AcfRepeaterResolver::is_acf_active();
        }

        return true;
    }

    /**
     * Formats a single widget's config into a DTO for the API response.
     *
     * @param array<string, mixed>  $widget   The widget config entry.
     * @param array<string, mixed>  $statuses The stored `name => is_enabled` status map.
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
     * Gets the `name => is_enabled` status map for every available widget,
     * for Elementor registration.
     *
     * @return array<string, bool>
     */
    public function get_registered_widgets_status()
    {
        $widgets_config = $this->get_available_widgets();
        $statuses       = $this->get_widget_statuses();

        $result = [];

        foreach ($widgets_config as $widget) {
            $name = $widget['name'];
            $result[$name] = isset($statuses[$name]) ? filter_var($statuses[$name], FILTER_VALIDATE_BOOLEAN) : true;
        }

        return $result;
    }

    /**
     * Toggles a widget's enabled status.
     *
     * @param string $name   The widget slug.
     * @param string $action Either `enable` or `disable`.
     * @return bool
     * @throws NotFoundException If no available widget matches `$name`.
     */
    public function toggle_widget(string $name, string $action)
    {
        $widgets_config = $this->get_available_widgets();
        $widget_exists = false;

        foreach ($widgets_config as $widget) {
            if ($widget['name'] === $name) {
                $widget_exists = true;
                break;
            }
        }

        if (!$widget_exists) {
            throw new NotFoundException(esc_html__('Widget not found', 'elemacy'));
        }

        $statuses = $this->get_widget_statuses();
        $statuses[$name] = ($action === 'enable');

        update_option(Utils::with_prefix('widgets'), $statuses);

        return true;
    }

    /**
     * Gets the stored `name => is_enabled` status map (only widgets the user
     * has explicitly toggled; available widgets default to enabled).
     *
     * @return array<string, bool>
     */
    protected function get_widget_statuses()
    {
        return get_option(Utils::with_prefix('widgets'), []);
    }
}
