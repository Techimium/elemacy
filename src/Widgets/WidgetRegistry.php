<?php

namespace Elemacy\Widgets;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\ModuleManager;
use Elemacy\Http\Exceptions\NotFoundException;
use Elemacy\Integrations\Acf\AcfRepeaterResolver;
use Elemacy\Support\Utils;
use Elemacy\Widgets\DTO\WidgetDTO;
use Elemacy\Widgets\Services\WidgetSettings;

/**
 * The one list of widgets: the platform's own (Config/widgets.php), then the
 * widgets of every active module (Module::get_widgets()), then add-ons through
 * Hooks::WIDGETS_REGISTER_FILTER. Elementor registration and the Widgets
 * screen both read it, so a widget is listed exactly when it can be
 * registered.
 *
 * @internal
 */
class WidgetRegistry
{
    /** Owner of the platform's own widgets, and of add-on entries that name none. */
    public const PLATFORM_OWNER = 'widgets';

    private ModuleManager $modules;

    private WidgetSettings $settings;

    public function __construct(ModuleManager $modules, WidgetSettings $settings)
    {
        $this->modules  = $modules;
        $this->settings = $settings;
    }

    /**
     * Every widget whose owner is available and whose runtime requirements
     * (e.g. ACF) are met, each with its `owner`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_available_widgets(): array
    {
        $widgets = [];

        foreach (require Utils::get_plugin_path('src/Widgets/Config/widgets.php') as $widget) {
            $widgets[] = array_merge($widget, ['owner' => self::PLATFORM_OWNER]);
        }

        foreach ($this->modules->get_active_modules() as $name => $module) {
            foreach ($module->get_widgets() as $widget) {
                $widgets[] = array_merge($widget, ['owner' => $name]);
            }
        }

        $widgets = (array) apply_filters(Hooks::WIDGETS_REGISTER_FILTER, $widgets);

        $available = [];

        foreach ($widgets as $widget) {
            if (is_array($widget) && $this->meets_requirements($widget)) {
                $available[] = $widget + ['owner' => self::PLATFORM_OWNER];
            }
        }

        return $available;
    }

    /**
     * Registers every available, switched-on widget with Elementor.
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_with_elementor($widgets_manager): void
    {
        foreach ($this->get_available_widgets() as $widget) {
            if ($this->settings->is_enabled($widget['name'])) {
                $class = $widget['class'];
                $widgets_manager->register(new $class());
            }
        }
    }

    /**
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function register_category($elements_manager): void
    {
        $elements_manager->add_category(
            'elemacy',
            [
                'title' => esc_html__('Elemacy', 'elemacy'),
                'icon' => 'fa fa-plug',
            ]
        );
    }

    /**
     * Every available widget with its switch, for the Widgets screen.
     *
     * @return WidgetDTO[]
     */
    public function get_all_widgets(): array
    {
        $widgets = [];

        foreach ($this->get_available_widgets() as $widget) {
            $dto = new WidgetDTO();
            $dto->name = $widget['name'];
            $dto->title = $widget['title'];
            $dto->icon = $widget['icon'];
            $dto->description = (string) ($widget['description'] ?? '');
            $dto->class = $widget['class'];
            $dto->is_enabled = $this->settings->is_enabled($widget['name']);
            $widgets[] = $dto;
        }

        return $widgets;
    }

    /**
     * Switches one available widget on or off.
     *
     * @param string $name   The widget name.
     * @param string $action Either `enable` or `disable`.
     * @throws NotFoundException If no available widget matches `$name`.
     */
    public function toggle_widget(string $name, string $action): void
    {
        if (!in_array($name, array_column($this->get_available_widgets(), 'name'), true)) {
            throw new NotFoundException(esc_html__('Widget not found', 'elemacy'));
        }

        $this->settings->set_enabled($name, $action === 'enable');
    }

    /**
     * @param array<string, mixed> $widget The widget config entry.
     */
    private function meets_requirements(array $widget): bool
    {
        $requires = $widget['requires'] ?? null;

        if ($requires === 'acf') {
            return AcfRepeaterResolver::is_acf_active();
        }

        return true;
    }
}
