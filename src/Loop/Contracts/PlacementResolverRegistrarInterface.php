<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION}
 * listeners. Adds a placement resolver, keyed by get_key(); a later one for
 * the same key replaces the earlier one.
 *
 * @since 1.0.0
 * @api
 */
interface PlacementResolverRegistrarInterface
{
    public function register(PlacementResolverInterface $resolver): void;
}
