<?php

namespace Elemacy\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Support\Utils;

/**
 * Each widget's own on/off switch, stored as a `name => bool` map holding only
 * the widgets an administrator has switched; every other widget is on. A
 * module being disabled never rewrites it, so a widget keeps its switch
 * across that module's off and on.
 *
 * @internal
 */
class WidgetSettings
{
    public function is_enabled(string $name): bool
    {
        $statuses = $this->statuses();

        return isset($statuses[$name]) ? filter_var($statuses[$name], FILTER_VALIDATE_BOOLEAN) : true;
    }

    public function set_enabled(string $name, bool $enabled): void
    {
        $statuses = $this->statuses();
        $statuses[$name] = $enabled;

        // Autoload: the status map gates frontend widget registration on every Elementor page.
        update_option(Utils::with_prefix('widgets'), $statuses, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function statuses(): array
    {
        return (array) get_option(Utils::with_prefix('widgets'), []);
    }
}
