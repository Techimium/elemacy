<?php

namespace Elemacy\Conditions;

defined('ABSPATH') || exit;

use Elemacy\Conditions\ConditionInterface;
use Elemacy\Conditions\ConditionManager;
use Elemacy\Core\Hooks;
use Elemacy\Support\Utils;

/**
 * Registers the display conditions free ships, listed in src/Config/conditions.php.
 * The list includes mock conditions that elemacy-pro overrides (by name) with
 * real implementations when active.
 *
 * Entries are class-strings or ready-made instances. Registration runs on
 * `init` because the config's per-taxonomy factories enumerate taxonomies,
 * which don't exist until then; nothing reads the conditions before `init`
 * (REST + front-end resolver run later).
 *
 * Lives in Core so the conditions registry is populated regardless of whether
 * the ThemeBuilder module is active.
 *
 * After free's own conditions are in place, {@see Hooks::CONDITIONS_REGISTER_ACTION}
 * fires with the manager so extensions (e.g. elemacy-pro) can register or
 * override conditions reliably — without depending on hook priority.
 */
class ConditionsBootstrap
{
    protected bool $hooked = false;

    /**
     * Attaches this class's WordPress hooks. Idempotent: a second call does
     * nothing (design.md D7 — constructors store dependencies, they do not
     * register hooks; this is where that happens instead). Named
     * register_hooks() rather than register() to avoid colliding with this
     * class's own pre-existing register() method below, which registers
     * free's conditions into ConditionManager, not a WordPress hook.
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
