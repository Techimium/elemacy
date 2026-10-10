<?php

namespace Elemacy\Modules\ThemeBuilder\Services\Placement;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Services\LocationRegistry;
use Elemacy\Loop\Contracts\PlacementResolverInterface;
use Elemacy\TemplateLibrary\TemplateResolver;
use WP_Query;
use WP_Term;
use WP_User;

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
                'query' => $this->query_facts(),
            ];
        }

        return null;
    }

    /**
     * A safe, typed subset of the active archive/search main query, read from
     * the server's own resolved query and queried object only (never from the
     * request), so a public source policy can rebuild the archive's own
     * constraints instead of trusting a submitted claim.
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

        // Only public types can ever be rendered publicly, and WordPress
        // itself widens a search to every searchable type, public or not.
        $post_type = $wp_query->get('post_type');
        if ($post_type) {
            $public = array_values(get_post_types(['public' => true], 'names'));
            $types = 'any' === $post_type ? $public : array_values(array_intersect((array) $post_type, $public));
            if ($types) {
                $facts['post_type'] = $types;
            }
        }

        $queried = get_queried_object();
        if ($queried instanceof WP_Term) {
            $facts['taxonomy'] = $queried->taxonomy;
            $facts['term_id'] = (int) $queried->term_id;

            // The key WP_Query itself uses for each taxonomy kind, so the
            // rebuilt query is constrained to this exact term.
            if ('category' === $queried->taxonomy) {
                $facts['cat'] = (int) $queried->term_id;
            } elseif ('post_tag' === $queried->taxonomy) {
                $facts['tag_id'] = (int) $queried->term_id;
            } else {
                $facts['term'] = (string) $queried->slug;
            }
        } elseif ($queried instanceof WP_User) {
            $facts['author'] = (int) $queried->ID;
        }

        return $facts + $this->date_facts($wp_query);
    }

    /**
     * Year, month and day of a date archive, from the split query vars
     * (pretty permalinks) or the compact `m` var (plain permalinks).
     *
     * @return array<string, int>
     */
    private function date_facts(WP_Query $wp_query): array
    {
        $facts = [];
        foreach (['year', 'monthnum', 'day'] as $key) {
            $value = (int) $wp_query->get($key);
            if ($value > 0) {
                $facts[$key] = $value;
            }
        }
        if ($facts) {
            return $facts;
        }

        $compact = (string) $wp_query->get('m');
        if ('' === $compact || !ctype_digit($compact)) {
            return [];
        }
        $parts = [
            'year' => [0, 4],
            'monthnum' => [4, 2],
            'day' => [6, 2],
        ];
        foreach ($parts as $key => [$offset, $length]) {
            if (strlen($compact) >= $offset + $length) {
                $value = (int) substr($compact, $offset, $length);
                if ($value > 0) {
                    $facts[$key] = $value;
                }
            }
        }

        return $facts;
    }
}
