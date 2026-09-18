<?php

namespace Elemacy\Modules\Widgets\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface as CanonicalPublicLoopSourcePolicyInterface;

/**
 * Moved to Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface (design.md
 * D7). This alias is a real interface_exists()/instanceof-compatible
 * identity, not a copy. Kept at this path for the first supported Free/Pro
 * compatibility window so any code still importing it from here (in either
 * plugin) is unaffected. New code should import the canonical namespace
 * instead.
 *
 * @deprecated Use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface.
 */
class_alias(CanonicalPublicLoopSourcePolicyInterface::class, __NAMESPACE__ . '\PublicLoopSourcePolicyInterface');
