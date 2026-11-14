<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * Marks a source whose page size is the widget's one saved
 * `PostsSettings::PER_PAGE` setting. The widget registers that control once,
 * shown for every such source it offers, so switching between them keeps the
 * number. The source reads it only through `PostsSettings::page_size()`, so a
 * value that is absent, as it is in the raw settings a later page reads, or
 * malformed, runs the same everywhere.
 *
 * @internal
 */
interface SharedPageSizeSourceInterface
{
}
