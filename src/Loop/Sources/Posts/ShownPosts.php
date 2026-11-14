<?php

namespace Elemacy\Loop\Sources\Posts;

defined('ABSPATH') || exit;

/**
 * The posts lists have already shown in this request, for lists that avoid
 * repeating them.
 *
 * @internal
 */
final class ShownPosts
{
    /** @var array<int, true> */
    private static array $ids = [];

    /**
     * @param int[] $ids
     * @return void
     */
    public static function add(array $ids): void
    {
        foreach ($ids as $id) {
            self::$ids[(int) $id] = true;
        }
    }

    /** @return int[] */
    public static function all(): array
    {
        return array_keys(self::$ids);
    }

    public static function reset(): void
    {
        self::$ids = [];
    }
}
