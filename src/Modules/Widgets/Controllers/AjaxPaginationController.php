<?php

namespace Elemacy\Modules\Widgets\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\SiteRequest as Request;
use Elemacy\Loop\Pagination\PaginationContextSigner;
use Elemacy\Loop\Pagination\PaginationRenderer;
use Elemacy\Loop\Pagination\PublicRenderPolicy;
use Elemacy\Loop\Pagination\PublishedLoopDefinitionResolver;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\Modules\Widgets\DTO\PublicLoopRequest;

/**
 * Resolves request → authored definition → policy → source query → render,
 * strictly in that order (design.md D1): every authorization check below
 * must complete before the first source query or Elementor render. A legacy
 * or tampered request never reaches either — see PublicLoopRequest,
 * PublishedLoopDefinitionResolver and PublicRenderPolicy for their own
 * boundaries.
 */
class AjaxPaginationController
{
    protected PublicRenderPolicy $policy;
    protected PublishedLoopDefinitionResolver $resolver;
    protected PaginationContextSigner $signer;
    protected LoopRenderer $renderer;
    protected PaginationRenderer $pagination_renderer;

    /**
     * Resolved through the Container (AjaxRouter::register() → make()) —
     * PublicRenderPolicy has an explicit bind() factory registered in
     * CoreServiceProvider specifically so its own singleton-registry
     * dependencies resolve correctly through this same constructor
     * injection (task 6.6); the other four have no such concern (each is a
     * plain, stateless class reflection auto-wires safely on its own).
     */
    public function __construct(
        PublicRenderPolicy $policy,
        PublishedLoopDefinitionResolver $resolver,
        PaginationContextSigner $signer,
        LoopRenderer $renderer,
        PaginationRenderer $pagination_renderer
    ) {
        $this->policy = $policy;
        $this->resolver = $resolver;
        $this->signer = $signer;
        $this->renderer = $renderer;
        $this->pagination_renderer = $pagination_renderer;
    }

    public function index(Request $request)
    {
        $public_request = PublicLoopRequest::from_json($request->get_string('request'));

        $policy = $this->policy;
        $definition = $this->resolver->resolve($public_request);

        $context = [];
        $raw_context = $public_request->context();
        if (get_post_type($definition->owner_id()) === 'elemacy_library' && '' === $raw_context) {
            throw PublicRenderPolicy::unavailable();
        }
        if ('' !== $raw_context) {
            $claims = $this->signer->verify(
                $raw_context,
                $definition->owner_id(),
                $definition->widget_id(),
                $definition->fingerprint()
            );
            $context = $claims['facts'];
        }

        $source = $policy->source($definition->source_key());
        $item_settings = $policy->settings($source, $definition->settings(), $context, $public_request->page());

        $result = $source->get_items($item_settings);

        if (empty($result->items)) {
            wp_send_json_error(esc_html__('No posts found.', 'elemacy'));
        }

        $template_id = $definition->template_id();
        $placement = $definition->owner_id() . '-' . $definition->widget_id();
        $batch = (string) $public_request->page();

        ob_start();
        foreach ($result->items as $occurrence => $item) {
            $this->renderer->render_item($template_id, $item, $placement, $definition->source_key(), $occurrence, $batch);
        }
        $items_html = ob_get_clean();

        // Pagination HTML, using the widget's own saved display settings —
        // never request-supplied except for the cosmetic URL base below.
        $pagination_settings = $definition->settings();

        ob_start();
        if (!empty($pagination_settings['pagination_type']) && $result->max_num_pages > 1) {
            $pagination_settings['paginate_base'] = $request->get_string('paginate_base', '');
            $this->pagination_renderer->render($pagination_settings, $result->max_num_pages, $public_request->page());
        }
        $pagination_html = ob_get_clean();

        wp_send_json_success([
            'items' => $items_html,
            'pagination' => $pagination_html,
        ]);
    }
}
