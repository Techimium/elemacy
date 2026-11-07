<?php

namespace Elemacy\Loop\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::LOOP_ADDRESS_STEP_RESOLVERS_REGISTER_ACTION}
 * listeners: one resolver per step kind.
 *
 * @since 1.0.0
 * @api
 */
interface AddressStepResolverRegistrarInterface
{
    public function register(AddressStepResolverInterface $resolver): self;
}
