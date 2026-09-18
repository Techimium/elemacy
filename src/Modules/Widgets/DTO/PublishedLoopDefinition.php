<?php

namespace Elemacy\Modules\Widgets\DTO;

defined('ABSPATH') || exit;

use Elemacy\Loop\Pagination\DTO\PublishedLoopDefinition as CanonicalPublishedLoopDefinition;

/**
 * Moved to Elemacy\Loop\Pagination\DTO\PublishedLoopDefinition (design.md
 * D7). This alias is a real class_exists()/instanceof-compatible identity,
 * not a copy. Kept at this path for the first supported Free/Pro
 * compatibility window so any code still importing it from here is
 * unaffected. New code should import the canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\Pagination\DTO\PublishedLoopDefinition.
 */
class_alias(CanonicalPublishedLoopDefinition::class, __NAMESPACE__ . '\PublishedLoopDefinition');
