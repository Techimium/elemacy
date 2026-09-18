<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Loop\DTO\LoopResultDTO;

/**
 * The query half of a loop data source (design.md D7): resolving and
 * validating items, with no Elementor control/document coupling — usable
 * without an Elementor widget/document instance.
 */
interface LoopSourceQueryInterface
{
    /**
     * Resolves this source's items for one widget render, from that
     * widget's settings array.
     *
     * @param array $settings
     * @return LoopResultDTO
     */
    public function get_items(array $settings): LoopResultDTO;

    /**
     * Validates and normalizes a decoded AJAX pagination request's settings
     * — attacker-controlled, since they round-tripped through the browser —
     * into the shape get_items() expects for the given page. Implementations
     * SHOULD throw \Elemacy\Core\Exceptions\ValidationException for invalid
     * input.
     *
     * @param array $raw_settings
     * @param int $paged
     * @return array
     */
    public function sanitize_ajax_settings(array $raw_settings, int $paged): array;
}
