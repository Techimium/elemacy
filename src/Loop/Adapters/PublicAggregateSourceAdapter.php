<?php

namespace Elemacy\Loop\Adapters;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Contracts\PublicLoopSourcePolicyInterface;

/**
 * {@see AggregateSourceAdapter}'s own doc comment — the variant used when a
 * SourceDefinition declared a public-context policy, so
 * `$source instanceof PublicLoopSourcePolicyInterface` (PublicRenderPolicy's
 * own guest-pagination gate) resolves true for it, exactly as it already
 * does for a hand-written aggregate source that implements that interface
 * directly.
 *
 * Implements the OLD (`Modules\Widgets\Contracts`) path specifically, not
 * the canonical `Loop\Contracts` one, even though `class_alias()` makes them
 * the exact same interface once both are loaded: `instanceof` against a
 * class/interface name that has never been autoloaded yet simply returns
 * false — unlike a class type-hint or an `implements` clause, `instanceof`
 * does NOT trigger the autoloader for an unresolved name (verified directly:
 * a registered spl_autoload_register() callback is never invoked for the
 * instanceof side of the expression). PublicRenderPolicy checks against the
 * OLD name; the only way to guarantee that name is already loaded by the
 * time such a check runs against one of these adapters is for this class's
 * own declaration — resolved by autoloading at `new PublicAggregateSourceAdapter()`
 * time, well before any instanceof check on the resulting object — to
 * reference that exact name in its `implements` clause. Loading the old
 * alias file this way also loads the canonical interface as a side effect
 * (its own class_alias() call defaults to autoloading its target), so this
 * one direction is sufficient either way.
 */
class PublicAggregateSourceAdapter extends AggregateSourceAdapter implements PublicLoopSourcePolicyInterface
{
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        return $this->definition->public_policy()->get_public_settings($settings, $context, $page);
    }
}
