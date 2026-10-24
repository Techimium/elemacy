<?php

namespace Elemacy\Elementor\Controls\Query\Resolvers;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Controls\Query\QueryResolverInterface;
use Elemacy\Http\DTO\QueryResultDTO;

/**
 * People, found and shown by display name only: a login or an email is neither
 * searchable nor returned. `query.who` is `authors` (the default: people who can
 * write posts) or `all`.
 */
class UserResolver implements QueryResolverInterface
{
    public const KEY = 'user';

    public const WHO_AUTHORS = 'authors';
    public const WHO_ALL     = 'all';

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
        // Asterisks would act as wildcards in the user search.
        $term = trim(str_replace('*', '', $term));

        if ('' === $term) {
            return [];
        }

        $users = get_users($this->scope($query) + [
            'search'         => '*' . $term . '*',
            'search_columns' => ['display_name'],
            'number'         => self::MAX_RESULTS,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
            'fields'         => ['ID', 'display_name'],
        ]);

        return array_map(fn ($user): QueryResultDTO => $this->result($user), $users);
    }

    public function titles(array $query, array $ids): array
    {
        $users = get_users($this->scope($query) + [
            'include' => $ids,
            'orderby' => 'include',
            'number'  => count($ids),
            'fields'  => ['ID', 'display_name'],
        ]);

        $titles = [];

        foreach ($users as $user) {
            $titles[(int) $user->ID] = $this->result($user)->text;
        }

        return $titles;
    }

    /**
     * @param object $user A row with ID and display_name.
     */
    private function result($user): QueryResultDTO
    {
        $dto       = new QueryResultDTO();
        $dto->id   = (int) $user->ID;
        $dto->text = '' !== $user->display_name ? $user->display_name : __('(no name)', 'elemacy');

        return $dto;
    }

    /**
     * The user-query args that limit who can be found. Anything but `all`
     * means authors, so a malformed request gets the narrower set.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function scope(array $query): array
    {
        if (self::WHO_ALL === ($query['who'] ?? self::WHO_AUTHORS)) {
            return [];
        }

        return ['capability' => ['edit_posts']];
    }
}
