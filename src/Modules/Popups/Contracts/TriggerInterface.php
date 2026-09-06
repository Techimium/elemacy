<?php

namespace Elemacy\Modules\Popups\Contracts;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\TriggerDTO;

interface TriggerInterface
{
    /**
     * Unique machine name (matches the stored TriggerDTO::$type).
     */
    public function get_name(): string;

    /**
     * Human-readable label shown in the admin UI.
     */
    public function get_label(): string;

    /**
     * UI parameter descriptors used to render this trigger's controls.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_config_schema(): array;

    /**
     * Normalize the stored trigger into the flat `{type, ...params}` shape the
     * frontend engine consumes.
     *
     * @return array<string, mixed>
     */
    public function to_js_config(TriggerDTO $dto): array;

    /**
     * Whether this is a non-functional placeholder overridden by an extension
     * (i.e. a pro feature not available in this build). Drives the locked "Pro"
     * state in the admin UI.
     */
    public function is_mock(): bool;
}
