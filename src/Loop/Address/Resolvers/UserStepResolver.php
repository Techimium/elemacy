<?php

namespace Elemacy\Loop\Address\Resolvers;

defined('ABSPATH') || exit;

use Elemacy\Loop\Address\AddressStep;
use Elemacy\Loop\Address\AddressStepResolverInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Items\UserLoopItem;
use WP_User;

/**
 * `user:{id}` — a user the Users source listed. The address was signed by the
 * server for that loop, so this only checks that the user still exists.
 */
final class UserStepResolver implements AddressStepResolverInterface
{
    public function get_kind(): string
    {
        return 'user';
    }

    public function resolve(AddressStep $step, ?LoopItemInterface $parent): ?LoopItemInterface
    {
        $id = ctype_digit($step->locator()) ? (int) $step->locator() : 0;
        $user = $id > 0 ? get_userdata($id) : false;

        return $user instanceof WP_User ? new UserLoopItem($user) : null;
    }

    public function fingerprint(LoopItemInterface $item): ?string
    {
        return null;
    }
}
