<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\Pagination\PublicRenderPolicy as CanonicalPublicRenderPolicy;

/**
 * Moved to Elemacy\Loop\Pagination\PublicRenderPolicy (design.md D7). This
 * alias is a real class_exists()/instanceof-compatible identity, not a copy.
 * Kept at this path for the first supported Free/Pro compatibility window so
 * any code still importing it from here (including the Container binding
 * and constructor type hints task 6.6 registered against this exact old
 * name) is unaffected. New code should import the canonical namespace
 * instead.
 *
 * @deprecated Use Elemacy\Loop\Pagination\PublicRenderPolicy.
 */
class_alias(CanonicalPublicRenderPolicy::class, __NAMESPACE__ . '\PublicRenderPolicy');
