<?php

namespace Elemacy\Elementor\Controls\Query\Resolvers;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Support\Taxonomies;
use Elemacy\Elementor\Controls\Query\QueryResolverInterface;
use Elemacy\Http\DTO\QueryResultDTO;
use WP_Term;

/**
 * Terms of any public taxonomy, found by name whether or not they have posts.
 * `query.taxonomy` may narrow the taxonomies.
 */
class TermResolver implements QueryResolverInterface
{
    public const KEY = 'term';

    public function key(): string
    {
        return self::KEY;
    }

    public function capability(): string
    {
        return 'edit_posts';
    }

    public function search(array $query, string $term): array
    {
        $taxonomies = $this->resolve_taxonomies($query);

        if (empty($taxonomies)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomies,
            'name__like' => $term,
            'hide_empty' => false,
            'number'     => self::MAX_RESULTS,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (!is_array($terms)) {
            return [];
        }

        return array_map(fn (WP_Term $item): QueryResultDTO => $this->result($item), $terms);
    }

    public function titles(array $query, array $ids): array
    {
        $taxonomies = $this->resolve_taxonomies($query);

        if (empty($taxonomies)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomies,
            'include'    => $ids,
            'hide_empty' => false,
            'number'     => count($ids),
            'orderby'    => 'include',
        ]);

        if (!is_array($terms)) {
            return [];
        }

        $titles = [];

        foreach ($terms as $item) {
            $titles[(int) $item->term_id] = $this->result($item)->text;
        }

        return $titles;
    }

    private function result(WP_Term $term): QueryResultDTO
    {
        $taxonomy       = get_taxonomy($term->taxonomy);
        $taxonomy_label = $taxonomy ? $taxonomy->labels->singular_name : $term->taxonomy;

        $dto       = new QueryResultDTO();
        $dto->id   = (int) $term->term_id;
        /* translators: 1: term name, 2: taxonomy singular name. */
        $dto->text = sprintf(__('%1$s (%2$s)', 'elemacy'), $term->name, $taxonomy_label);

        return $dto;
    }

    /**
     * Taxonomies to search, intersected with the public ones so a crafted
     * request can't reach a private or internal taxonomy's terms.
     *
     * @param array<string, mixed> $query
     *
     * @return string[]
     */
    private function resolve_taxonomies(array $query): array
    {
        $allowed = array_map(
            static fn ($taxonomy): string => $taxonomy->name,
            Taxonomies::public_objects()
        );

        $requested = array_filter(array_map('sanitize_key', (array) ($query['taxonomy'] ?? [])));

        if (empty($requested)) {
            return $allowed;
        }

        $valid = array_values(array_intersect($requested, $allowed));

        return empty($valid) ? $allowed : $valid;
    }
}
