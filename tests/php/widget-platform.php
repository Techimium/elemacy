<?php

/**
 * The widget platform: one widget list built from the platform's own widgets,
 * each active module's get_widgets() and the add-on filter; a module switch
 * and a widget switch combine. Required by run.php.
 */

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Module;
use Elemacy\Core\ModuleManager;
use Elemacy\Widgets\Services\WidgetSettings;
use Elemacy\Widgets\WidgetRegistry;

/** A widget class the fake widgets manager can instantiate without Elementor. */
final class FixturePlatformWidget
{
}

/** A module that ships one widget and is active only when always-active. */
final class FixtureWidgetModule extends Module
{
    private string $name;
    private bool $active;

    public function __construct(string $name, bool $active)
    {
        $this->name = $name;
        $this->active = $active;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_title(): string
    {
        return $this->name;
    }

    public function get_icon(): string
    {
        return '';
    }

    public function get_description(): string
    {
        return '';
    }

    public function is_always_active(): bool
    {
        return $this->active;
    }

    public function boot(): void
    {
    }

    public function get_widgets(): array
    {
        return [['name' => 'elemacy-' . $this->name . '-widget', 'title' => $this->name, 'icon' => '', 'class' => FixturePlatformWidget::class]];
    }
}

/** Collects what the platform hands Elementor's widgets manager. */
final class FixtureWidgetsManager
{
    /** @var object[] */
    public array $registered = [];

    public function register(object $widget): void
    {
        $this->registered[] = $widget;
    }
}

$widget_platform_registry = static function (): WidgetRegistry {
    delete_option(OptionKeys::ACTIVE_MODULES);
    $manager = new ModuleManager();
    $manager->register(new FixtureWidgetModule('fixture-on', true));
    $manager->register(new FixtureWidgetModule('fixture-off', false));
    $manager->init_modules();

    return new WidgetRegistry($manager, new WidgetSettings());
};

check('WidgetRegistry lists the platform widgets and each active module\'s widgets with their owner, and leaves out an inactive module\'s', static function () use ($widget_platform_registry) {
    delete_option('elemacy_widgets');
    $owners = array_column($widget_platform_registry()->get_available_widgets(), 'owner', 'name');

    return ($owners['elemacy-site-logo'] ?? null) === WidgetRegistry::PLATFORM_OWNER
        && ($owners['elemacy-fixture-on-widget'] ?? null) === 'fixture-on'
        && !isset($owners['elemacy-fixture-off-widget']);
});

check('WidgetRegistry registers with Elementor only switched-on widgets of available owners, and a widget switch survives its module being off', static function () use ($widget_platform_registry) {
    delete_option('elemacy_widgets');
    $settings = new WidgetSettings();
    $registry = $widget_platform_registry();

    foreach ($registry->get_available_widgets() as $widget) {
        $settings->set_enabled($widget['name'], false);
    }
    $settings->set_enabled('elemacy-fixture-on-widget', true);
    $settings->set_enabled('elemacy-fixture-off-widget', false);

    $elementor = new FixtureWidgetsManager();
    $registry->register_with_elementor($elementor);
    $listed = array_column(array_map(static fn ($dto) => (array) $dto, $registry->get_all_widgets()), 'is_enabled', 'name');
    $stored = (array) get_option('elemacy_widgets', []);
    delete_option('elemacy_widgets');

    return 1 === count($elementor->registered)
        && $elementor->registered[0] instanceof FixturePlatformWidget
        && true === ($listed['elemacy-fixture-on-widget'] ?? null)
        && false === ($listed['elemacy-search'] ?? null)
        && !isset($listed['elemacy-fixture-off-widget'])
        && false === $stored['elemacy-fixture-off-widget'];
});

check('WidgetRegistry::toggle_widget() refuses a widget whose owner is not available', static function () use ($widget_platform_registry) {
    try {
        $widget_platform_registry()->toggle_widget('elemacy-fixture-off-widget', 'disable');
    } catch (\Elemacy\Http\Exceptions\NotFoundException $error) {
        return true;
    }

    return false;
});
