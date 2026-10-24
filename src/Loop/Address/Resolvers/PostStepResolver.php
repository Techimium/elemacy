<?php

namespace Elemacy\Loop\Address\Resolvers;

defined('ABSPATH') || exit;

use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Address\AddressStepResolverInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\NativePostItemInterface;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Placement\HostFacts;

/** `post:{id}` — a post, while it is still publicly viewable. */
final class PostStepResolver implements AddressStepResolverInterface
{
    public function get_kind(): string
    {
        return 'post';
    }

    public function resolve(AddressStep $step, ?LoopItemInterface $parent): ?LoopItemInterface
    {
        $id = ctype_digit($step->locator()) ? (int) $step->locator() : 0;
        $post = $id > 0 ? get_post($id) : null;

        return HostFacts::is_public_post($post) ? new PostLoopItem($post) : null;
    }

    public function fingerprint(LoopItemInterface $item): ?string
    {
        return $item instanceof NativePostItemInterface ? (string) $item->get_post()->post_modified_gmt : null;
    }
}
