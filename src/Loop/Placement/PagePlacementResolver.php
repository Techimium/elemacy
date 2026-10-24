<?php

namespace Elemacy\Loop\Placement;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PlacementResolverInterface;

/** An ordinary singular page/post built with Elementor is its own placement. */
final class PagePlacementResolver implements PlacementResolverInterface
{
    public function get_key(): string
    {
        return 'page';
    }

    public function resolve(int $owner_id): ?array
    {
        if (!is_singular() || get_queried_object_id() !== $owner_id) {
            return null;
        }

        return ['post_id' => $owner_id];
    }
}
