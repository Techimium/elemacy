<?php

namespace Elemacy\Modules\LoopBuilder\PostCard;

defined('ABSPATH') || exit;

/**
 * What a part may need to know about the card and list it renders in.
 *
 * @internal
 */
final class PostCardContext
{
    private bool $whole_card_link;
    private bool $title_is_linked;
    private bool $new_tab;
    private int $occurrence;
    private int $page;

    public function __construct(bool $whole_card_link, bool $title_is_linked, bool $new_tab, int $occurrence, int $page)
    {
        $this->whole_card_link = $whole_card_link;
        $this->title_is_linked = $title_is_linked;
        $this->new_tab = $new_tab;
        $this->occurrence = $occurrence;
        $this->page = $page;
    }

    public function whole_card_link(): bool
    {
        return $this->whole_card_link;
    }

    /** Whether a linked Title part already gives this card its link to the post. */
    public function title_is_linked(): bool
    {
        return $this->title_is_linked;
    }

    public function occurrence(): int
    {
        return $this->occurrence;
    }

    public function page(): int
    {
        return $this->page;
    }

    /** The target and rel attributes of a link, with a leading space, or nothing. */
    public function link_attributes(): string
    {
        return $this->new_tab ? ' target="_blank" rel="noopener"' : '';
    }
}
