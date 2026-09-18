<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/** Optional source capability: public pagination requires explicit opt-in. */
interface PublicLoopSourcePolicyInterface
{
    /**
     * Validate saved settings and add independently authorized host context.
     *
     * Neither input may come directly from a browser. This method must reject
     * unavailable/private source contexts before any source query or rendering.
     * It must not execute a source query itself.
     *
     * @param array $settings Persisted authoring settings.
     * @param array $context Host facts verified by the placement/context policy.
     * @param int   $page Validated one-based page number.
     * @return array Source-specific public query settings.
     */
    public function get_public_settings(array $settings, array $context, int $page): array;
}
