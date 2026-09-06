<?php

namespace Elemacy\Modules\Popups\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\RuleDTO;

interface RuleInterface
{
    /**
     * Unique machine name (matches the stored RuleDTO::$type).
     */
    public function get_name(): string;

    /**
     * Human-readable label shown in the admin UI.
     */
    public function get_label(): string;

    /**
     * UI parameter descriptors used to render this rule's controls.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_config_schema(): array;

    /**
     * Normalize the stored rule into the `{type, ...params}` shape the
     * frontend engine consumes.
     *
     * @return array<string, mixed>
     */
    public function to_js_config(RuleDTO $dto): array;

    /**
     * Server-side gate: return false to drop the popup before it reaches the
     * client. Only consulted when {@see is_server_evaluable()} is true.
     */
    public function check(RuleDTO $dto): bool;

    /**
     * Whether this rule is enforced server-side (and therefore excluded from
     * the client config).
     */
    public function is_server_evaluable(): bool;

    /**
     * Whether this is a non-functional placeholder overridden by an extension
     * (i.e. a pro feature not available in this build). Drives the locked "Pro"
     * state in the admin UI.
     */
    public function is_mock(): bool;
}
