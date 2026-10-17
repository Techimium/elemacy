<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility;

defined('ABSPATH') || exit;

/**
 * Removes the callbacks a theme attached to one of its own chrome hooks, and
 * only those. A callback belongs to the theme when the function, closure or
 * method is defined in a file inside the active theme (the parent or the
 * child). Anything a plugin attached to the same hook, at any priority,
 * survives, which `remove_all_actions()` would not allow.
 *
 * A closure has no name, so the defining file is the one test that works for
 * functions, methods and closures alike.
 */
final class ThemeHookCleaner
{
    /**
     * @param string $hook         The theme's chrome hook.
     * @param int    $max_priority Callbacks above this priority are left alone (for a theme that hangs
     *                             something unrelated to its chrome high on the same hook).
     * @return int How many callbacks were removed.
     */
    public static function remove_theme_callbacks(string $hook, int $max_priority = PHP_INT_MAX): int
    {
        return self::remove_defined_in($hook, [get_template_directory(), get_stylesheet_directory()], $max_priority);
    }

    /**
     * @param string   $hook        The hook to clean.
     * @param string[] $directories Callbacks defined in a file below any of these are removed.
     * @param int      $max_priority Callbacks above this priority are left alone.
     * @return int How many callbacks were removed.
     */
    public static function remove_defined_in(string $hook, array $directories, int $max_priority = PHP_INT_MAX): int
    {
        global $wp_filter;

        if (empty($wp_filter[$hook]) || !$wp_filter[$hook] instanceof \WP_Hook) {
            return 0;
        }

        $roots = array_map(static fn(string $dir): string => rtrim(wp_normalize_path($dir), '/') . '/', $directories);
        $owned = [];
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            if ($priority > $max_priority) {
                continue;
            }
            foreach ($callbacks as $callback) {
                $file = self::defining_file($callback['function']);
                if (null !== $file && self::is_below($file, $roots)) {
                    $owned[] = [$callback['function'], $priority];
                }
            }
        }

        foreach ($owned as [$function, $priority]) {
            remove_filter($hook, $function, $priority);
        }

        return count($owned);
    }

    /**
     * @param string   $file  A file path.
     * @param string[] $roots Directories, each ending in a slash.
     */
    private static function is_below(string $file, array $roots): bool
    {
        $file = wp_normalize_path($file);
        foreach ($roots as $root) {
            if (0 === strpos($file, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $handler A function name, 'Class::method', [object|class, method], closure or invokable object.
     */
    private static function defining_file($handler): ?string
    {
        try {
            if ($handler instanceof \Closure || (is_string($handler) && false === strpos($handler, '::'))) {
                $file = (new \ReflectionFunction($handler))->getFileName();
            } elseif (is_string($handler)) {
                $file = (new \ReflectionMethod($handler))->getFileName();
            } elseif (is_array($handler) && 2 === count($handler)) {
                $file = (new \ReflectionMethod($handler[0], $handler[1]))->getFileName();
            } elseif (is_object($handler)) {
                $file = (new \ReflectionMethod($handler, '__invoke'))->getFileName();
            } else {
                return null;
            }
        } catch (\ReflectionException $e) {
            return null;
        }

        return false === $file ? null : $file;
    }
}
