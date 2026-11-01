<?php

namespace Elemacy\Elementor\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::QUERY_RESOLVERS_REGISTER_ACTION}
 * listeners. Adds a live-search picker kind.
 *
 * @since 1.0.0
 * @api
 */
interface QueryResolverRegistrarInterface
{
    /**
     * @throws \InvalidArgumentException If another resolver already has the key.
     */
    public function register(QueryResolverInterface $resolver): void;
}
