<?php

namespace Elemacy\Forms;

use Elemacy\Core\Container;
use Elemacy\Core\Hooks;
use Elemacy\Forms\Contracts\FieldTypeInterface;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Support\ConfigList;
use Elemacy\Support\Utils;

defined('ABSPATH') || exit;

/**
 * The field types forms can use: src/Config/form_field_types.php, then
 * whatever add-ons register on Hooks::FORM_FIELD_TYPES_REGISTER_ACTION.
 *
 * Field settings share one namespace per field, so a key two types both
 * declare must have the same kind, the standard keys keep their standard
 * kind, and the keys every field has are reserved.
 */
final class FieldTypeRegistry extends Registry
{
    public const RESERVED_SETTINGS = ['_id', 'type', 'label', 'custom_id', 'width'];

    /** @var array<string, string> Setting key => kind, across every registered type. */
    private array $kinds = Setting::STANDARD;

    /**
     * Class-string entries are built through the container, so a built-in
     * may declare constructor dependencies.
     */
    public static function from_config(Container $container): self
    {
        return new self(static function (self $registry) use ($container): void {
            $entries = array_map(static function ($entry) use ($container) {
                return is_string($entry) ? $container->make($entry) : $entry;
            }, require Utils::get_plugin_path('src/Config/form_field_types.php'));

            foreach (ConfigList::build($entries, FieldTypeInterface::class) as $type) {
                $registry->register($type);
            }

            do_action(Hooks::FORM_FIELD_TYPES_REGISTER_ACTION, $registry);
        });
    }

    /** @return bool False when refused (see the class comment). */
    public function register(FieldTypeInterface $type): bool
    {
        $layouts = [FieldTypeInterface::LAYOUT_FIELD, FieldTypeInterface::LAYOUT_GROUP, FieldTypeInterface::LAYOUT_HIDDEN, FieldTypeInterface::LAYOUT_CONTENT];

        if (!in_array($type->layout(), $layouts, true)) {
            return $this->refuse(sprintf('Field type "%s" has an unknown layout.', $type->key()));
        }

        $kinds = $this->kinds;

        foreach ($type->settings() as $setting) {
            if (!$setting instanceof Setting || !in_array($setting->kind(), Setting::KINDS, true)) {
                return $this->refuse(sprintf('Field type "%s" declares an invalid setting.', $type->key()));
            }

            $key = $setting->key();

            if (in_array($key, self::RESERVED_SETTINGS, true) || 1 !== preg_match(self::KEY_PATTERN, $key)) {
                return $this->refuse(sprintf('Field type "%s" may not declare the setting "%s".', $type->key(), $key));
            }

            if (isset($kinds[$key]) && $kinds[$key] !== $setting->kind()) {
                return $this->refuse(sprintf('Field type "%s" declares "%s" as %s, but it is already a %s setting.', $type->key(), $key, $setting->kind(), $kinds[$key]));
            }

            $kinds[$key] = $setting->kind();
        }

        if (!$this->add($type->key(), $type)) {
            return false;
        }

        $this->kinds = $kinds;

        return true;
    }

    public function get(string $key): ?FieldTypeInterface
    {
        $type = $this->find($key);

        return $type instanceof FieldTypeInterface ? $type : null;
    }

    /** @return FieldTypeInterface[] In registration order. */
    public function all(): array
    {
        return $this->items();
    }
}
