<?php

namespace Elemacy\Modules\LoopBuilder\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Modules\LoopBuilder\PostCard\PostCardContext;
use Elemacy\Widgets\BaseWidget;
use Elementor\Repeater;
use WP_Post;

/**
 * One kind of element a post card can contain.
 *
 * @internal
 */
interface PostCardPartInterface
{
    /** Stable identifier saved in each row's `part` setting. */
    public function key(): string;

    public function label(): string;

    /**
     * This part's row settings and their defaults, which fill in anything a
     * saved row is missing on every page.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * Adds this part's row controls, each named with its key as a prefix and
     * limited by $condition to rows of this part.
     *
     * @param Repeater $rows
     * @param array    $condition
     * @return void
     */
    public function register_row_controls(Repeater $rows, array $condition): void;

    public function register_style_section(BaseWidget $widget): void;

    /**
     * This part's markup for one post, escaped, without its wrapper. Empty when
     * the post has nothing to show here.
     *
     * @param WP_Post         $post
     * @param array           $row  The row's settings, with defaults filled in.
     * @param PostCardContext $card
     * @return string
     */
    public function render(WP_Post $post, array $row, PostCardContext $card): string;

    /** Whether adjacent rows of this part share one wrapper, with $this->join() between them. */
    public function groups_adjacent_rows(): bool;

    /**
     * What goes between two adjacent rows of this part.
     *
     * @param array $row The later row's settings.
     * @return string
     */
    public function join(array $row): string;
}
