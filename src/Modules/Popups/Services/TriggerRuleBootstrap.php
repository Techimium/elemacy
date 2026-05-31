<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Modules\Popups\Rules\RuleInterface;
use Elemacy\Modules\Popups\Triggers\TriggerInterface;
use Elemacy\Support\Utils;

/**
 * Registers the triggers and advanced rules free ships, listed in
 * src/Modules/Popups/Config/{triggers,rules}.php. Both lists include mock
 * entries that elemacy-pro overrides (by name) with real implementations when
 * active.
 *
 * Registration runs on `init` (priority 99) so that any extension which hooked
 * the register action earlier in the request is in place before the action
 * fires. After free's own entries are registered the matching register action
 * fires with the manager so extensions can register or override reliably —
 * without depending on hook priority.
 */
class TriggerRuleBootstrap
{
    public function register(): void
    {
        add_action('init', function () {
            $this->register_triggers();
            $this->register_rules();
        }, 99);
    }

    protected function register_triggers(): void
    {
        $manager = TriggerManager::instance();

        foreach (require Utils::get_plugin_path('src/Modules/Popups/Config/triggers.php') as $entry) {
            $trigger = is_string($entry) ? new $entry() : $entry;

            if ($trigger instanceof TriggerInterface) {
                $manager->register($trigger);
            }
        }

        do_action(Hooks::POPUP_TRIGGERS_REGISTER_ACTION, $manager);
    }

    protected function register_rules(): void
    {
        $manager = RuleManager::instance();

        foreach (require Utils::get_plugin_path('src/Modules/Popups/Config/rules.php') as $entry) {
            $rule = is_string($entry) ? new $entry() : $entry;

            if ($rule instanceof RuleInterface) {
                $manager->register($rule);
            }
        }

        do_action(Hooks::POPUP_RULES_REGISTER_ACTION, $manager);
    }
}
