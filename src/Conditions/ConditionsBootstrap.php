<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionInterface;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Core\Hooks;
use Elemacy\Support\Utils;

/**
 * Registers the display conditions free ships, listed in
 * src/Config/conditions.php. The list includes mock conditions that elemacy-
 * pro overrides (by name) with real implementations when active.
 */
class ConditionsBootstrap
{
    protected bool $hooked = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing.
     */
    public function register_hooks(): void
    {
        if ($this->hooked) {
            return;
        }

        $this->hooked = true;

        add_action('init', [$this, 'register'], 99);
    }

    public function register()
    {
        $manager = ConditionManager::instance();

        foreach (require Utils::get_plugin_path('src/Config/conditions.php') as $entry) {
            $condition = is_string($entry) ? new $entry() : $entry;

            if ($condition instanceof ConditionInterface) {
                $manager->register($condition);
            }
        }

        do_action(Hooks::CONDITIONS_REGISTER_ACTION, $manager);
    }
}
