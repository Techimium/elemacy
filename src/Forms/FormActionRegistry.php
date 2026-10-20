<?php

namespace Elemacy\Forms;

use Elemacy\Core\Container;
use Elemacy\Core\Hooks;
use Elemacy\Forms\Contracts\FormActionInterface;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Support\ConfigList;
use Elemacy\Support\Utils;

defined('ABSPATH') || exit;

/**
 * The submit actions forms can select: src/Config/form_actions.php, then
 * whatever add-ons register on Hooks::FORM_ACTIONS_REGISTER_ACTION. Every
 * setting key of an action starts with "{key}_".
 */
final class FormActionRegistry extends Registry
{
    /**
     * Class-string entries are built through the container, so a built-in
     * may declare constructor dependencies.
     */
    public static function from_config(Container $container): self
    {
        return new self(static function (self $registry) use ($container): void {
            $entries = array_map(static function ($entry) use ($container) {
                return is_string($entry) ? $container->make($entry) : $entry;
            }, require Utils::get_plugin_path('src/Config/form_actions.php'));

            foreach (ConfigList::build($entries, FormActionInterface::class) as $action) {
                $registry->register($action);
            }

            do_action(Hooks::FORM_ACTIONS_REGISTER_ACTION, $registry);
        });
    }

    /** @return bool False when refused. */
    public function register(FormActionInterface $action): bool
    {
        $prefix = $action->key() . '_';

        foreach ($action->settings() as $setting) {
            if (!$setting instanceof Setting || !in_array($setting->kind(), Setting::KINDS, true)) {
                return $this->refuse(sprintf('Form action "%s" declares an invalid setting.', $action->key()));
            }

            if (0 !== strpos($setting->key(), $prefix) || 1 !== preg_match(self::KEY_PATTERN, $setting->key())) {
                return $this->refuse(sprintf('Form action "%s" setting "%s" must start with "%s".', $action->key(), $setting->key(), $prefix));
            }
        }

        return $this->add($action->key(), $action);
    }

    public function get(string $key): ?FormActionInterface
    {
        $action = $this->find($key);

        return $action instanceof FormActionInterface ? $action : null;
    }

    /** @return FormActionInterface[] In registration order. */
    public function all(): array
    {
        return $this->items();
    }
}
