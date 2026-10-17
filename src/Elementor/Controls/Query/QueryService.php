<?php

namespace Elemacy\Elementor\Controls\Query;

defined('ABSPATH') || exit;

use Elemacy\Http\DTO\QueryResultDTO;

/**
 * Answers the live-search requests behind the elemacy_query control: searching
 * for options as the user types, and looking up the labels of already-selected
 * values when the control re-renders. It only routes: the control's
 * `autocomplete.object` picks a resolver from the registry, and that resolver
 * does the work, so supporting a new kind of thing never touches this class.
 */
class QueryService
{
    public const OBJECT_POST = 'post';
    public const OBJECT_TERM = 'term';
    public const OBJECT_USER = 'user';

    public const MAX_RESULTS = QueryResolverInterface::MAX_RESULTS;

    protected QueryResolverRegistry $resolvers;

    public function __construct(QueryResolverRegistry $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    /**
     * Whether the current person may use the picker the config asks for.
     * False for an object no resolver answers to.
     *
     * @param array<string, mixed> $autocomplete The control's autocomplete config.
     */
    public function can_query(array $autocomplete): bool
    {
        $resolver = $this->resolver_for($autocomplete);

        return null !== $resolver && current_user_can($resolver->capability());
    }

    /**
     * @param array<string, mixed> $autocomplete The control's autocomplete config.
     *
     * @return QueryResultDTO[]
     */
    public function search(array $autocomplete, string $term): array
    {
        $term     = trim($term);
        $resolver = $this->resolver_for($autocomplete);

        if ('' === $term || null === $resolver) {
            return [];
        }

        return $resolver->search($this->query_of($autocomplete), $term);
    }

    /**
     * Labels for already-selected ids, keyed by id, so the control can show its
     * stored value without that option being in a pre-rendered list.
     *
     * @param array<string, mixed> $get_titles The control's autocomplete config.
     * @param int[]                $ids
     *
     * @return array<int, string>
     */
    public function titles(array $get_titles, array $ids): array
    {
        $ids      = array_values(array_filter(array_map('intval', $ids)));
        $resolver = $this->resolver_for($get_titles);

        if (empty($ids) || null === $resolver) {
            return [];
        }

        return $resolver->titles($this->query_of($get_titles), $ids);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function resolver_for(array $config): ?QueryResolverInterface
    {
        $object = $config['object'] ?? '';

        return is_string($object) ? $this->resolvers->get($object) : null;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected function query_of(array $config): array
    {
        return isset($config['query']) && is_array($config['query']) ? $config['query'] : [];
    }
}
