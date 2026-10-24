<?php

namespace Elemacy\Elementor\Controls\Query;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Support\ConfigList;
use Elemacy\Support\Utils;
use InvalidArgumentException;

/**
 * The resolvers elemacy_query can search with, by key. Populated on first use
 * rather than when built, so nothing is loaded on a request that never runs a
 * picker search (every visitor request).
 */
class QueryResolverRegistry
{
    /** @var callable(self): void|null */
    protected $loader;

    /** @var array<string, QueryResolverInterface> */
    protected array $resolvers = [];

    /**
     * @param callable(self): void $loader Registers the resolvers; called once,
     *                                     on the first lookup.
     */
    public function __construct(callable $loader)
    {
        $this->loader = $loader;
    }

    /**
     * The registry the plugin uses: the resolvers listed in
     * src/Config/query_resolvers.php, then whatever add-ons register on
     * Hooks::QUERY_RESOLVERS_REGISTER_ACTION. Both happen on the first lookup.
     */
    public static function from_config(): self
    {
        return new self(static function (self $registry): void {
            foreach (ConfigList::instances(Utils::get_plugin_path('src/Config/query_resolvers.php'), QueryResolverInterface::class) as $resolver) {
                $registry->register($resolver);
            }

            do_action(Hooks::QUERY_RESOLVERS_REGISTER_ACTION, $registry);
        });
    }

    /**
     * @throws InvalidArgumentException If another resolver already has the key.
     */
    public function register(QueryResolverInterface $resolver): void
    {
        $key = $resolver->key();

        if (isset($this->resolvers[$key])) {
            /* translators: %s: query resolver key. */
            throw new InvalidArgumentException(sprintf('A query resolver for "%s" is already registered.', esc_html($key)));
        }

        $this->resolvers[$key] = $resolver;
    }

    public function get(string $key): ?QueryResolverInterface
    {
        $this->load();

        return $this->resolvers[$key] ?? null;
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        $this->load();

        return array_keys($this->resolvers);
    }

    protected function load(): void
    {
        if (null === $this->loader) {
            return;
        }

        $loader       = $this->loader;
        $this->loader = null;

        $loader($this);
    }
}
