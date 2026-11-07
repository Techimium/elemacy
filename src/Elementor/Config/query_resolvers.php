<?php

defined('ABSPATH') || exit;

use Elemacy\Elementor\Controls\Query\Resolvers\PostResolver;
use Elemacy\Elementor\Controls\Query\Resolvers\TermResolver;
use Elemacy\Elementor\Controls\Query\Resolvers\UserResolver;

/**
 * The kinds of thing the elemacy_query control can live-search, as class-strings
 * or ready-made instances. Add one line per kind; add-ons use
 * Hooks::QUERY_RESOLVERS_REGISTER_ACTION instead.
 *
 * @see \Elemacy\Elementor\Controls\Query\QueryResolverRegistry
 */
return [
    PostResolver::class,
    TermResolver::class,
    UserResolver::class,
];
