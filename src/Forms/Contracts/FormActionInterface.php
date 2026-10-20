<?php

namespace Elemacy\Forms\Contracts;

use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\ActionResult;
use Elemacy\Forms\Submission\Submission;

defined('ABSPATH') || exit;

/**
 * Something a valid submission does (send an email, call a webhook, ...).
 * Register one on Hooks::FORM_ACTIONS_REGISTER_ACTION.
 *
 * @api
 */
interface FormActionInterface
{
    /** The saved action identifier, e.g. 'email'. Never change it once released. */
    public function key(): string;

    public function label(): string;

    /**
     * @return Setting[] Shown in the action's own settings section. Every key
     *                   starts with "{key()}_", so actions never collide.
     */
    public function settings(): array;

    /**
     * Read values through the submission and settings through
     * $submission->form()->setting(), never from the request.
     */
    public function run(Submission $submission): ActionResult;
}
