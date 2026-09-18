<?php

namespace Elemacy\Tests\Integration\Fixtures;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elemacy\Modules\Widgets\Contracts\LoopItemInterface;
use Elemacy\Modules\Widgets\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Modules\Widgets\DTO\LoopResultDTO;
use Elemacy\Modules\Widgets\Support\PublicLoopLimits;
use Elementor\Widget_Base;

/** Counts the real source boundary; queries still run through the real source. */
final class ObservedLoopSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface
{
    private LoopDataSourceInterface $source;
    public int $query_calls = 0;
    public array $queried_settings = [];

    public function __construct(LoopDataSourceInterface $source)
    {
        $this->source = $source;
    }

    public function get_key(): string
    {
        return $this->source->get_key();
    }

    public function get_label(): string
    {
        return $this->source->get_label();
    }

    public function register_controls(Widget_Base $widget): void
    {
        $this->source->register_controls($widget);
    }

    public function get_items(array $settings): LoopResultDTO
    {
        ++$this->query_calls;
        $this->queried_settings[] = $settings;
        return $this->source->get_items($settings);
    }

    public function get_ajax_payload(array $settings): array
    {
        return $this->source->get_ajax_payload($settings);
    }

    public function get_public_settings(array $settings, array $context, int $page): array
    {
        if (!$this->source instanceof PublicLoopSourcePolicyInterface) {
            throw PublicLoopLimits::invalid_settings();
        }
        return $this->source->get_public_settings($settings, $context, $page);
    }

    public function sanitize_ajax_settings(array $settings, int $paged): array
    {
        return $this->source->sanitize_ajax_settings($settings, $paged);
    }

    public function register_preview_controls(PreviewablePageBase $document): void
    {
        $this->source->register_preview_controls($document);
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        return $this->source->resolve_preview_item($settings);
    }
}
