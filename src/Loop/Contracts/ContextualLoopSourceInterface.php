<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopQuery;

/**
 * Optional source capability: resolve items from a whole LoopQuery, including
 * the loop frame the loop renders inside, instead of from settings alone. Core
 * calls query() when a source implements this and get_items() otherwise.
 */
interface ContextualLoopSourceInterface
{
    public function query(LoopQuery $query): LoopResultDTO;
}
