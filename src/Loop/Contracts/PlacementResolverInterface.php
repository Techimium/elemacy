<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * Proves an owner document is the one the server actually resolved for the
 * current request — a page, a Theme Builder location, a matched popup, or the
 * template presently rendering a nested loop item — rather than merely a
 * published document ID a request happens to reference.
 */
interface PlacementResolverInterface
{
    public function get_key(): string;

    /**
     * @param int $owner_id Owner document ID, already passing PublicRenderPolicy::assert_owner().
     * @return array|null Independently-verified host facts when this placement is the one
     *                     actively serving $owner_id right now, else null.
     */
    public function resolve(int $owner_id): ?array;
}
