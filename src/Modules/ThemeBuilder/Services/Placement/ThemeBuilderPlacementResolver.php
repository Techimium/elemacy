<?php

namespace Elemacy\Modules\ThemeBuilder\Services\Placement;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Services\LocationRegistry;
use Elemacy\Modules\Widgets\Contracts\PlacementResolverInterface;
use Elemacy\TemplateLibrary\TemplateResolver;
use WP_Query;
use WP_Term;

/** Proves a Theme Builder document is the one the server actually resolved for this request. */
final class ThemeBuilderPlacementResolver implements PlacementResolverInterface
{
    public function get_key(): string
    {
        return 'theme_builder';
    }

    public function resolve(int $owner_id): ?array
    {
        $resolver = TemplateResolver::instance();

        if ($resolver->resolve('header') === $owner_id) {
            return ['location' => 'header'];
        }
        if ($resolver->resolve('footer') === $owner_id) {
            return ['location' => 'footer'];
        }

        $type = LocationRegistry::instance()->current_type();
        if (null !== $type && $resolver->resolve($type) === $owner_id) {
            return [
                'location' => $type,
                'query' => $this->query_facts()
            ];
        }

        return null;
    }

    /**
     * A safe, typed subset of the active archive/search query so a public
     * source policy can recheck it instead of trusting a submitted claim.
     *
     * @return array
     */
    private function query_facts(): array
    {
        global $wp_query;
        if (!$wp_query instanceof WP_Query) {
            return [];
        }

        $facts = [];

        $search = $wp_query->get('s');
        if ('' !== $search && false !== $search) {
            $facts['s'] = (string) $search;
        }

        $post_type = $wp_query->get('post_type');
        if ($post_type) {
            $facts['post_type'] = array_values((array) $post_type);
        }

        $queried = get_queried_object();
        if ($queried instanceof WP_Term) {
            $facts['taxonomy'] = $queried->taxonomy;
            $facts['term_id'] = $queried->term_id;
        }

        return $facts;
    }
}
