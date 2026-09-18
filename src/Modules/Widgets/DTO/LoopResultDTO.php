<?php

namespace Elemacy\Modules\Widgets\DTO;

defined('ABSPATH') || exit;

use Elemacy\Loop\DTO\LoopResultDTO as CanonicalLoopResultDTO;

/**
 * Moved to Elemacy\Loop\DTO\LoopResultDTO (design.md D7). This alias is a
 * real class_exists()/instanceof-compatible identity, not a copy — kept at
 * this path for the first supported Free/Pro compatibility window so any
 * code still importing it from here (in either plugin) is unaffected. New
 * code should import the canonical namespace instead.
 *
 * @deprecated Use Elemacy\Loop\DTO\LoopResultDTO.
 */
class_alias(CanonicalLoopResultDTO::class, __NAMESPACE__ . '\LoopResultDTO');
