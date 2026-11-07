<?php

namespace Elemacy\Elementor\Contracts;

defined('ABSPATH') || exit;

/**
 * Handed to {@see \Elemacy\Core\Hooks::TEMPLATE_ASSETS_COLLECT_ACTION}
 * listeners. Declares that a library item will render on the current page, so its Elementor CSS is printed. Idempotent per request.
 *
 * @since 1.0.0
 * @api
 */
interface TemplateAssetsRegistrarInterface
{
    public function register(int $id): void;
}
