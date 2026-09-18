<?php

namespace Elemacy\Loop;

defined('ABSPATH') || exit;

use Elemacy\Loop\Adapters\AggregateSourceAdapter;
use Elemacy\Loop\Adapters\PublicAggregateSourceAdapter;
use Elemacy\Loop\Contracts\LoopSourceControlsInterface;
use Elemacy\Loop\Contracts\LoopSourcePreviewInterface;
use Elemacy\Loop\Contracts\LoopSourceQueryInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;

/**
 * Assembles a loop data source from its narrow query/controls/preview/
 * public-context-policy collaborators (design.md D7), registered once
 * through LoopDataSourceRegistry::register_definition() rather than one
 * class implementing every concern itself. preview()/public_policy() are
 * optional capabilities, mirroring how not every existing aggregate source
 * implements PublicLoopSourcePolicyInterface today — a definition with no
 * public policy still registers and works everywhere except guest
 * pagination, exactly like an existing source lacking that interface.
 */
final class SourceDefinition
{
    protected string $key;
    protected string $label;
    protected LoopSourceQueryInterface $query;
    protected LoopSourceControlsInterface $controls;
    protected ?LoopSourcePreviewInterface $preview;
    protected ?PublicLoopSourcePolicyInterface $public_policy;

    public function __construct(
        string $key,
        string $label,
        LoopSourceQueryInterface $query,
        LoopSourceControlsInterface $controls,
        ?LoopSourcePreviewInterface $preview = null,
        ?PublicLoopSourcePolicyInterface $public_policy = null
    ) {
        $this->key = $key;
        $this->label = $label;
        $this->query = $query;
        $this->controls = $controls;
        $this->preview = $preview;
        $this->public_policy = $public_policy;
    }

    public function get_key(): string
    {
        return $this->key;
    }

    public function get_label(): string
    {
        return $this->label;
    }

    public function query(): LoopSourceQueryInterface
    {
        return $this->query;
    }

    public function controls(): LoopSourceControlsInterface
    {
        return $this->controls;
    }

    public function preview(): ?LoopSourcePreviewInterface
    {
        return $this->preview;
    }

    public function public_policy(): ?PublicLoopSourcePolicyInterface
    {
        return $this->public_policy;
    }

    /**
     * Presents this definition as the legacy aggregate LoopDataSourceInterface
     * every existing consumer (LoopGrid/LoopCarousel, AjaxPaginationController,
     * PublicRenderPolicy, PublishedLoopDefinitionResolver) already talks to —
     * the "legacy aggregate-interface adapter" design.md D7 asks for, so those
     * consumers need no changes at all to accept a narrow-contract-based
     * source. Returns the PublicLoopSourcePolicyInterface-implementing variant
     * only when this definition actually declared a public policy — an
     * instanceof check against the returned object must keep meaning exactly
     * what it means for an existing hand-written aggregate source.
     */
    public function to_legacy_source(): LoopDataSourceInterface
    {
        return $this->public_policy !== null
            ? new PublicAggregateSourceAdapter($this)
            : new AggregateSourceAdapter($this);
    }
}
