<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\FormBuilder\Actions\EmailAction;

/**
 * The submit actions free ships, as class-strings (built through the
 * container) or ready-made instances, in the order the editor lists them.
 * Add-ons use Hooks::FORM_ACTIONS_REGISTER_ACTION.
 *
 * @see \Elemacy\Modules\FormBuilder\Services\FormActionRegistry
 */
return [
    EmailAction::class,
];
