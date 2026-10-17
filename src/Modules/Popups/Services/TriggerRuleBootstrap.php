<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\Contracts\RuleInterface;
use Elemacy\Modules\Popups\Contracts\TriggerInterface;
use Elemacy\Support\ConfigList;
use Elemacy\Support\Utils;

/**
 * Registers the triggers and advanced rules free ships, listed in
 * src/Modules/Popups/Config/{triggers,rules}.php. Both lists include mock
 * entries that elemacy-pro overrides (by name) with real implementations when
 * active.
 */
class TriggerRuleBootstrap
{
    protected TriggerManager $triggers;
    protected RuleManager $rules;

    public function __construct(?TriggerManager $triggers = null, ?RuleManager $rules = null)
    {
        $this->triggers = $triggers ?? TriggerManager::instance();
        $this->rules = $rules ?? RuleManager::instance();
    }

    public function register(): void
    {
        add_action('init', function () {
            $this->register_triggers();
            $this->register_rules();
        }, 99);
    }

    protected function register_triggers(): void
    {
        $manager = $this->triggers;

        foreach (ConfigList::instances(Utils::get_plugin_path('src/Modules/Popups/Config/triggers.php'), TriggerInterface::class) as $trigger) {
            $manager->register($trigger);
        }

        do_action(Hooks::POPUP_TRIGGERS_REGISTER_ACTION, $manager);
    }

    protected function register_rules(): void
    {
        $manager = $this->rules;

        foreach (ConfigList::instances(Utils::get_plugin_path('src/Modules/Popups/Config/rules.php'), RuleInterface::class) as $rule) {
            $manager->register($rule);
        }

        do_action(Hooks::POPUP_RULES_REGISTER_ACTION, $manager);
    }
}
