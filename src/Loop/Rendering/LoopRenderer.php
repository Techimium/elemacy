<?php

namespace Elemacy\Loop\Rendering;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Rendering\TemplateRenderer;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Exceptions\TemplateRecursionException;

/**
 * Owns rendering exactly one loop item's fragment — the per-item body
 * Grid/Carousel/AJAX each used to duplicate independently. Each caller keeps
 * its own outer layout (wrapper markup, pagination, navigation) and calls
 * render_item() once per item. See docs/ARCHITECTURE.md § Shared domains.
 *
 * @internal
 */
final class LoopRenderer
{
    private LoopItemStyles $styles;
    private TemplateRenderer $templates;
    private LoopItemScope $scope;

    public function __construct(?LoopItemStyles $styles = null, ?TemplateRenderer $templates = null, ?LoopItemScope $scope = null)
    {
        $this->styles = $styles ?? new LoopItemStyles();
        $this->templates = $templates ?? new TemplateRenderer();
        $this->scope = $scope ?? new LoopItemScope();
    }

    public function render_item(
        int $template_id,
        LoopItemInterface $item,
        string $placement,
        string $source,
        int $occurrence = 0,
        string $batch = ''
    ): bool {
        if (!$this->template_exists($template_id)) {
            return false;
        }

        try {
            TemplateRecursionGuard::enter($template_id);
        } catch (TemplateRecursionException $e) {
            return false;
        }

        $parent = LoopContext::current_frame();
        $identity = new LoopRenderIdentity(
            $placement,
            (string) $template_id,
            $source,
            $item->get_identity(),
            $occurrence,
            $batch,
            $parent ? $parent->render_key() : ''
        );

        try {
            return $this->render_entered_item($template_id, $item, $identity, $source, $occurrence);
        } finally {
            TemplateRecursionGuard::exit();
        }
    }

    private function render_entered_item(int $template_id, LoopItemInterface $item, LoopRenderIdentity $identity, string $source, int $occurrence): bool
    {
        $this->styles->print_base_css($template_id);

        return $this->scope->render(
            $item,
            $identity,
            $source,
            $template_id,
            $occurrence,
            function () use ($template_id): void {
                LoopTemplateRenderStack::push($template_id);
                try {
                    echo $this->templates->render($template_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- TemplateRenderer returns Elementor's fully rendered and escaped HTML.
                } finally {
                    LoopTemplateRenderStack::pop();
                }
            },
            function (string $selector) use ($template_id, $item): void {
                $this->styles->print_item_css($template_id, $item->get_identity(), $selector);
            }
        );
    }

    private function template_exists(int $template_id): bool
    {
        return $this->templates->exists($template_id);
    }
}
