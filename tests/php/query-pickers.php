<?php

/**
 * The elemacy_query picker pattern (openspec theme-builder/archive-preview): a
 * registry of one resolver per object type behind QueryService. Pure routing
 * logic with stub resolvers; what each real resolver finds is covered against
 * real WordPress in tests/integration/query-pickers.php.
 */

use Elemacy\Elementor\Controls\Query\QueryResolverInterface;
use Elemacy\Elementor\Controls\Query\QueryResolverRegistry;
use Elemacy\Elementor\Controls\Query\QueryService;
use Elemacy\Http\DTO\QueryResultDTO;

if (!function_exists('current_user_can')) {
    /** Tests grant capabilities through $GLOBALS['__user_caps']. */
    function current_user_can($capability)
    {
        return in_array($capability, $GLOBALS['__user_caps'] ?? [], true);
    }
}

/** Records what the service hands it, and answers with canned results. */
final class FixtureQueryResolver implements QueryResolverInterface
{
    /** @var array<int, array{string, array, mixed}> */
    public array $calls = [];

    private string $key;
    private string $capability;

    public function __construct(string $key = 'fixture', string $capability = 'edit_posts')
    {
        $this->key = $key;
        $this->capability = $capability;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function capability(): string
    {
        return $this->capability;
    }

    public function search(array $query, string $term): array
    {
        $this->calls[] = ['search', $query, $term];
        $dto = new QueryResultDTO();
        $dto->id = 7;
        $dto->text = 'Seven';

        return [$dto];
    }

    public function titles(array $query, array $ids): array
    {
        $this->calls[] = ['titles', $query, $ids];

        return array_combine($ids, array_map(static fn (int $id): string => 'T' . $id, $ids));
    }
}

check('QueryResolverRegistry returns a registered resolver by key and null for an unknown key', static function () {
    $resolver = new FixtureQueryResolver('thing');
    $registry = new QueryResolverRegistry(static function (QueryResolverRegistry $registry) use ($resolver) {
        $registry->register($resolver);
    });

    return $resolver === $registry->get('thing') && null === $registry->get('other') && ['thing'] === $registry->keys();
});

check('QueryResolverRegistry rejects a second resolver for the same key instead of letting the last one win', static function () {
    $registry = new QueryResolverRegistry(static function (QueryResolverRegistry $registry) {
        $registry->register(new FixtureQueryResolver('dup'));
        $registry->register(new FixtureQueryResolver('dup'));
    });

    try {
        $registry->get('dup');
    } catch (\InvalidArgumentException $e) {
        return false !== strpos($e->getMessage(), 'dup');
    }

    return false;
});

check('QueryResolverRegistry loads its resolvers on first use only, and only once', static function () {
    $loads = 0;
    $registry = new QueryResolverRegistry(static function () use (&$loads) {
        $loads++;
    });

    $untouched = 0 === $loads;
    $registry->get('a');
    $registry->keys();
    $registry->get('b');

    return $untouched && 1 === $loads;
});

check('QueryService.search() delegates to the resolver for the config object, passing its query and the trimmed term', static function () {
    $resolver = new FixtureQueryResolver('fixture');
    $service = new QueryService(new QueryResolverRegistry(static fn (QueryResolverRegistry $r) => $r->register($resolver)));

    $results = $service->search(['object' => 'fixture', 'query' => ['a' => 1]], '  hello ');

    return 1 === count($results)
        && 'Seven' === $results[0]->text
        && [['search', ['a' => 1], 'hello']] === $resolver->calls;
});

check('QueryService.search() returns nothing, without calling a resolver, for a blank term, an unknown or missing object', static function () {
    $resolver = new FixtureQueryResolver('fixture');
    $service = new QueryService(new QueryResolverRegistry(static fn (QueryResolverRegistry $r) => $r->register($resolver)));

    return [] === $service->search(['object' => 'fixture'], '   ')
        && [] === $service->search(['object' => 'unknown'], 'x')
        && [] === $service->search([], 'x')
        && [] === $resolver->calls;
});

check('QueryService.search() treats a non-array query as empty', static function () {
    $resolver = new FixtureQueryResolver('fixture');
    $service = new QueryService(new QueryResolverRegistry(static fn (QueryResolverRegistry $r) => $r->register($resolver)));

    $service->search(['object' => 'fixture', 'query' => 'nonsense'], 'x');

    return [['search', [], 'x']] === $resolver->calls;
});

check('QueryService.titles() normalises ids to positive ints and skips the resolver when none are left', static function () {
    $resolver = new FixtureQueryResolver('fixture');
    $service = new QueryService(new QueryResolverRegistry(static fn (QueryResolverRegistry $r) => $r->register($resolver)));

    $titles = $service->titles(['object' => 'fixture'], ['5', 0, '', 9, 'abc']);
    $none = $service->titles(['object' => 'fixture'], [0, '']);
    $unknown = $service->titles(['object' => 'unknown'], [5]);

    return [5 => 'T5', 9 => 'T9'] === $titles
        && [] === $none
        && [] === $unknown
        && [['titles', [], [5, 9]]] === $resolver->calls;
});

check('QueryService.can_query() requires a registered object and the capability that resolver declares', static function () {
    $service = new QueryService(new QueryResolverRegistry(static function (QueryResolverRegistry $r) {
        $r->register(new FixtureQueryResolver('open', 'edit_posts'));
        $r->register(new FixtureQueryResolver('strict', 'list_users'));
    }));

    $GLOBALS['__user_caps'] = ['edit_posts'];
    $editor = $service->can_query(['object' => 'open']) && !$service->can_query(['object' => 'strict']);

    $GLOBALS['__user_caps'] = ['edit_posts', 'list_users'];
    $admin = $service->can_query(['object' => 'strict']);

    $GLOBALS['__user_caps'] = [];
    $nobody = !$service->can_query(['object' => 'open']);

    return $editor && $admin && $nobody && !$service->can_query(['object' => 'missing']) && !$service->can_query([]);
});

check('QueryService keeps the object constants other code references', static function () {
    return 'post' === QueryService::OBJECT_POST
        && 'term' === QueryService::OBJECT_TERM
        && 'user' === QueryService::OBJECT_USER
        && QueryResolverInterface::MAX_RESULTS === QueryService::MAX_RESULTS;
});
