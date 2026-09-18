<?php

namespace Elemacy\Loop\Adapters;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\SourceDefinition;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elementor\Widget_Base;

/**
 * Presents a SourceDefinition's narrow query/controls/(preview) collaborators
 * as the legacy aggregate LoopDataSourceInterface (design.md D7's "legacy
 * aggregate-interface adapter"), so every existing consumer of
 * LoopDataSourceRegistry::get()/all() keeps working unchanged regardless of
 * whether a given source was hand-written against the aggregate interface or
 * assembled from narrow contracts through SourceDefinition.
 *
 * {@see PublicAggregateSourceAdapter} is the equivalent for a definition that
 * also declared a public-context policy — kept as a separate class rather
 * than a runtime branch here, since PublicRenderPolicy's own `instanceof
 * PublicLoopSourcePolicyInterface` gate must resolve statically per instance,
 * exactly as it already does for a hand-written aggregate source that either
 * does or does not implement that interface.
 */
class AggregateSourceAdapter implements LoopDataSourceInterface
{
    protected SourceDefinition $definition;

    public function __construct(SourceDefinition $definition)
    {
        $this->definition = $definition;
    }

    public function get_key(): string
    {
        return $this->definition->get_key();
    }

    public function get_label(): string
    {
        return $this->definition->get_label();
    }

    public function register_controls(Widget_Base $widget): void
    {
        $this->definition->controls()->register_controls($widget);
    }

    public function get_items(array $settings): LoopResultDTO
    {
        return $this->definition->query()->get_items($settings);
    }

    /**
     * @deprecated see LoopDataSourceInterface::get_ajax_payload()'s own doc
     * comment — unused since design.md D1's signed request envelope
     * replaced it; a narrow-contract source never implements this at all, so
     * the adapter has nothing to delegate to.
     */
    public function get_ajax_payload(array $settings): array
    {
        return [];
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        return $this->definition->query()->sanitize_ajax_settings($raw_settings, $paged);
    }

    public function register_preview_controls(PreviewablePageBase $document): void
    {
        $preview = $this->definition->preview();

        if ($preview !== null) {
            $preview->register_preview_controls($document);
        }
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        $preview = $this->definition->preview();

        return $preview !== null ? $preview->resolve_preview_item($settings) : null;
    }
}
