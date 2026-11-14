<?php

namespace Elemacy\Modules\LoopBuilder\PostCard;

defined('ABSPATH') || exit;

use WP_Post;

/**
 * A post's own text as plain words. Reads the stored content and never runs
 * the_content, which would re-enter Elementor's rendering for a builder post.
 *
 * @internal
 */
final class PostCardText
{
    public static function plain_content(WP_Post $post): string
    {
        $text = wp_strip_all_tags(strip_shortcodes(excerpt_remove_blocks($post->post_content)));

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
    }

    /** What names a link to this post when no visible text does. */
    public static function link_name(WP_Post $post): string
    {
        $title = get_the_title($post);

        return '' === $title ? __('(no title)', 'elemacy') : $title;
    }

    public static function trim(string $text, int $length, string $unit): string
    {
        if ($length < 1) {
            return $text;
        }

        if ('characters' === $unit) {
            return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)) . '…' : $text;
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return count($words) > $length ? implode(' ', array_slice($words, 0, $length)) . '…' : $text;
    }

    public static function reading_minutes(WP_Post $post): int
    {
        $words = preg_split('/\s+/u', self::plain_content($post), -1, PREG_SPLIT_NO_EMPTY);

        return $words ? (int) max(1, ceil(count($words) / 200)) : 0;
    }
}
