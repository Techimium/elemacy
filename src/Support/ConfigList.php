<?php

namespace Elemacy\Support;

defined('ABSPATH') || exit;

/**
 * Reads a config file that returns a list of class-strings and/or ready-made
 * instances (src/Config/conditions.php, a module's Config/triggers.php, ...)
 * and turns it into instances of one type. Each caller still registers them
 * with its own manager, whose register signature it alone knows.
 */
class ConfigList
{
    /**
     * Instantiates each class-string entry, passes instances through, and
     * drops any entry that is not a $type.
     *
     * @param string $file Absolute path of a config file returning a list.
     * @param string $type Class or interface every kept entry must satisfy.
     * @return object[]
     */
    public static function instances(string $file, string $type): array
    {
        return static::build(require $file, $type);
    }

    /**
     * Same as instances(), for a list already read from a config file (e.g.
     * one group's entries in a keyed config).
     *
     * @param array  $entries Class-strings and/or ready-made instances.
     * @param string $type    Class or interface every kept entry must satisfy.
     * @return object[]
     */
    public static function build(array $entries, string $type): array
    {
        $instances = [];

        foreach ($entries as $entry) {
            $instance = is_string($entry) ? new $entry() : $entry;

            if ($instance instanceof $type) {
                $instances[] = $instance;
            }
        }

        return $instances;
    }
}
