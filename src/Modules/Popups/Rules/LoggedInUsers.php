<?php

namespace Elemacy\Modules\Popups\Rules;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\DTO\RuleDTO;

/**
 * Hide the popup for logged-in users. Enforced server-side: when a logged-in
 * user is detected the popup is dropped before it ever reaches the client, so
 * this rule is excluded from the frontend config.
 */
class LoggedInUsers extends BaseRule
{
    public function get_name(): string
    {
        return 'logged_in';
    }

    public function get_label(): string
    {
        return __('Hide for logged-in users', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [];
    }

    public function is_server_evaluable(): bool
    {
        return true;
    }

    public function check(RuleDTO $dto): bool
    {
        return ! is_user_logged_in();
    }

    public function to_js_config(RuleDTO $dto): array
    {
        return ['type' => 'logged_in'];
    }
}
