<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\ContextualLoopSourceInterface;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\DTO\LoopResultDTO;

/**
 * The one way core asks a source for items: a whole LoopQuery for a source that
 * reads one, its settings otherwise. The enclosing frame is whatever loop
 * frame is current when the query runs.
 */
final class LoopQueryDispatcher
{
    public static function items(LoopDataSourceInterface $source, array $settings, int $page = 1, bool $is_preview = false): LoopResultDTO
    {
        if ($source instanceof ContextualLoopSourceInterface) {
            return $source->query(new LoopQuery($settings, LoopContext::current_frame(), $page, $is_preview));
        }

        return $source->get_items($settings);
    }
}
