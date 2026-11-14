<?php

namespace Elemacy\Modules\LoopBuilder\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Http\SiteRequest as Request;
use Elemacy\Loop\Access\PaginationContextSigner;
use Elemacy\Loop\Access\SignedParentChain;
use Elemacy\Loop\Address\ItemAddressResolver;
use Elemacy\Loop\Pagination\PageArgument;
use Elemacy\Loop\Pagination\PageLinks;
use Elemacy\Loop\Pagination\PaginationRenderer;
use Elemacy\Loop\Access\PublicRenderPolicy;
use Elemacy\Loop\Access\PublishedLoopDefinitionResolver;
use Elemacy\Loop\Rendering\ItemPlacement;
use Elemacy\Loop\Access\PublicLoopLimits;
use Elemacy\Loop\Access\PublicLoopRequest;
use Elemacy\Loop\LoopQueryDispatcher;

/**
 * Resolves request → authored definition → policy → source query → render,
 * strictly in that order: every authorization check below must complete before
 * the first source query or Elementor render.
 *
 * @internal
 */
class AjaxPaginationController
{
    protected PublicRenderPolicy $policy;
    protected PublishedLoopDefinitionResolver $resolver;
    protected PaginationContextSigner $signer;
    protected PaginationRenderer $pagination_renderer;

    /**
     * Built by the container; the shared registries behind these services
     * resolve to their populated singletons through the bindings each owner
     * registers.
     */
    public function __construct(
        PublicRenderPolicy $policy,
        PublishedLoopDefinitionResolver $resolver,
        PaginationContextSigner $signer,
        PaginationRenderer $pagination_renderer
    ) {
        $this->policy = $policy;
        $this->resolver = $resolver;
        $this->signer = $signer;
        $this->pagination_renderer = $pagination_renderer;
    }

    public function index(Request $request)
    {
        $public_request = PublicLoopRequest::from_json($request->get_string('request'));

        $policy = $this->policy;
        $definition = $this->resolver->resolve($public_request);
        PublicLoopLimits::assert_within_page_limit($definition->settings(), $public_request->page());

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

        $source = $policy->endpoint_source($definition->source_key());

        // A nested loop's later page runs inside the same loop items its
        // first page did, rebuilt from the signed context and checked again,
        // before its policy, query or render sees anything.
        $chain = SignedParentChain::enter($context, new ItemAddressResolver());

        try {
            $item_settings = $policy->settings($source, $definition->settings(), $context, $public_request->page());

            $result = LoopQueryDispatcher::items($source, $item_settings, $public_request->page());

            if (empty($result->items)) {
                wp_send_json_error(esc_html__('No items found.', 'elemacy'));
            }

            $presenter = $definition->presenter();
            $placement = $definition->owner_id() . '-' . $definition->widget_id();
            $batch = (string) $public_request->page();

            ob_start();
            foreach ($result->items as $occurrence => $item) {
                $presenter->render_item($item, $definition->settings(), new ItemPlacement($placement, $definition->source_key(), $occurrence, $batch, $public_request->page()));
            }
            $items_html = ob_get_clean();

            // Pagination HTML, using the widget's own saved display settings —
            // never request-supplied except for the cosmetic URL base below.
            $pagination_settings = $definition->settings();

            ob_start();
            $max_pages = PublicLoopLimits::effective_max_pages($pagination_settings, $result->max_num_pages);

            if (!empty($pagination_settings['pagination_type']) && $max_pages > 1) {
                // The page URL the widget's own links were built from: cosmetic, so only a URL
                // of this site is taken.
                $fallback = home_url('/?paged=%#%');
                $base = wp_validate_redirect(esc_url_raw($request->get_string('paginate_base', '')), $fallback);
                $base = '' !== $base ? $base : $fallback;
                $this->pagination_renderer->render($pagination_settings, $max_pages, $public_request->page(), $this->link_args($base, $definition->widget_id()));
            }
            $pagination_html = ob_get_clean();
        } finally {
            $chain->leave();
        }

        // The server alone decides what comes next (offsets and the page cap
        // included), so the browser never derives it from markup or URLs.
        $page = $public_request->page();
        $has_next = $page < $max_pages && $page < PublicLoopRequest::MAX_PAGE;

        wp_send_json_success([
            'items' => $items_html,
            'pagination' => $pagination_html,
            'next_page' => $has_next ? $page + 1 : 0,
        ]);
    }

    /**
     * Page links for the base a widget's classic render handed the browser:
     * the widget's own argument on the page URL (page one without it), or the
     * site's paged URLs with the page number where `%#%` is.
     *
     * @param string $base      A URL of this site, with `%#%` where the page number goes.
     * @param string $widget_id
     * @return array
     */
    private function link_args(string $base, string $widget_id): array
    {
        $own = PageArgument::name($widget_id) . '=%#%';

        if (false !== strpos($base, $own)) {
            return PageLinks::argument(preg_replace('/[?&]' . preg_quote($own, '/') . '/', '', $base), $widget_id);
        }

        return ['base' => $base];
    }
}
