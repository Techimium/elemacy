<?php

namespace Elemacy\Modules\LoopBuilder\PostCard;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\Contracts\PostCardPartInterface;
use Elemacy\Support\Utils;

/**
 * Every part a post card can use, in the order the editor offers them.
 *
 * @internal
 */
final class PostCardParts
{
    /** @var array<string, PostCardPartInterface>|null */
    private static ?array $parts = null;

    /** @return array<string, PostCardPartInterface> */
    public static function all(): array
    {
        if (null === self::$parts) {
            self::$parts = [];

            foreach (require Utils::get_plugin_path('src/Modules/LoopBuilder/Config/post_card_parts.php') as $class) {
                $part = new $class();
                self::$parts[$part->key()] = $part;
            }
        }

        return self::$parts;
    }

    public static function get(string $key): ?PostCardPartInterface
    {
        return self::all()[$key] ?? null;
    }
}
