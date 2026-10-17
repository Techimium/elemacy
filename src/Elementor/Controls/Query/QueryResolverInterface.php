<?php

namespace Elemacy\Elementor\Controls\Query;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\QueryResultDTO;

/**
 * One kind of thing the elemacy_query control can search for (posts, terms,
 * users, ...). A resolver owns everything specific to its kind: what a typed
 * term matches, how an entry is labelled, how a stored id is turned back into
 * a label, and who may ask. QueryService only routes to it by key, so a new
 * picker type is one resolver class and one registration.
 */
interface QueryResolverInterface
{
    /** Most entries a single search returns. */
    public const MAX_RESULTS = 30;

    /**
     * The control's `autocomplete.object` value this resolver answers for,
     * e.g. 'post'. Unique across resolvers.
     */
    public function key(): string;

    /**
     * The capability a person needs for this resolver to answer them.
     */
    public function capability(): string;

    /**
     * Entries matching what the person typed.
     *
     * @param array<string, mixed> $query The control's `autocomplete.query`, which the
     *                                    client sends: narrow it, never trust it.
     * @param string               $term  Already trimmed and never empty.
     *
     * @return QueryResultDTO[]
     */
    public function search(array $query, string $term): array;

    /**
     * Labels for already-stored ids, so the control can show its saved value.
     * An id that no longer resolves, or may not be shown, is left out.
     *
     * @param array<string, mixed> $query The control's `autocomplete.query`.
     * @param int[]                $ids   Positive ids, never empty.
     *
     * @return array<int, string> Label by id.
     */
    public function titles(array $query, array $ids): array;
}
