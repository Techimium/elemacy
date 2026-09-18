<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Loop\Pagination\PublishedLoopDefinitionResolver as CanonicalPublishedLoopDefinitionResolver;

/**
 * Moved to Elemacy\Loop\Pagination\PublishedLoopDefinitionResolver
 * (design.md D7). This alias is a real class_exists()/instanceof-compatible
 * identity, not a copy. Kept at this path for the first supported Free/Pro
 * compatibility window so any code still importing it from here is
 * unaffected. New code should import the canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\Pagination\PublishedLoopDefinitionResolver.
 */
class_alias(CanonicalPublishedLoopDefinitionResolver::class, __NAMESPACE__ . '\PublishedLoopDefinitionResolver');
