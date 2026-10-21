<?php

namespace Elemacy\Tests\Integration\Fixtures;

use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopDataSourceInterface;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Pagination\PublicLoopLimits;
use Elemacy\Elementor\Widgets\BaseWidget;

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

    public function register_controls(BaseWidget $widget): void
    {
        $this->source->register_controls($widget);
    }

    public function get_items(array $settings): LoopResultDTO
    {
        ++$this->query_calls;
        $this->queried_settings[] = $settings;
        return $this->source->get_items($settings);
    }

    public function get_public_settings(array $settings, array $context, int $page): array
    {
        if (!$this->source instanceof PublicLoopSourcePolicyInterface) {
            throw PublicLoopLimits::invalid_settings();
        }
        return $this->source->get_public_settings($settings, $context, $page);
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
