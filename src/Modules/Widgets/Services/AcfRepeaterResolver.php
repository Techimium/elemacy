<?php

namespace Elemacy\Modules\Widgets\Services;

defined('ABSPATH') || exit;

use Elemacy\Integrations\Acf\AcfRepeaterResolver as CanonicalAcfRepeaterResolver;

/**
 * Moved to Elemacy\Integrations\Acf\AcfRepeaterResolver (design.md D7). This
 * alias is a real class_exists()/instanceof-compatible identity, not a copy.
 * Kept at this path for the first supported Free/Pro compatibility window
 * (Pro's AcfRepeaterDataSource still imports this exact path — task 7.8's
 * own job to migrate) so any code still importing it from here is
 * unaffected. New code should import the canonical namespace instead.
 *
 * @deprecated Use Elemacy\Integrations\Acf\AcfRepeaterResolver.
 */
class_alias(CanonicalAcfRepeaterResolver::class, __NAMESPACE__ . '\AcfRepeaterResolver');
