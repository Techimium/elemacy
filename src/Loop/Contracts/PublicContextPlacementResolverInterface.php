<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/** Evaluates a verified frontend context without invoking frontend display hooks. */
interface PublicContextPlacementResolverInterface extends PlacementResolverInterface
{
    public function resolve_public_context(int $owner_id): ?array;
}
