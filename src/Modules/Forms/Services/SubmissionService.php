<?php

namespace Elemacy\Modules\Forms\Services;

use Elemacy\Core\Exceptions\HttpException;
use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\Forms\Contracts\FormActionInterface;

defined('ABSPATH') || exit;

final class SubmissionService
{
    private PublishedFormDefinitionResolver $definitions;
    private SubmissionValidator $validator;
    private SubmissionRateLimiter $limiter;
    private array $actions;

    /** @param FormActionInterface[] $actions Supported synchronous actions, keyed by their authored identifiers. */
    public function __construct(PublishedFormDefinitionResolver $definitions, SubmissionValidator $validator, SubmissionRateLimiter $limiter, array $actions)
    {
        $this->definitions = $definitions;
        $this->validator = $validator;
        $this->limiter = $limiter;
        $this->actions = [];
        foreach ($actions as $action) {
            $this->actions[$action->key()] = $action;
        }
    }

    public function submit(int $owner, string $widget, string $context, $values, string $ip): void
    {
        $settings = $this->definitions->resolve($owner, $widget, $context);
        $clean = $this->validator->validate($settings, $values);
        $selected = $settings['submit_actions'] ?? [];
        if (!is_array($selected)) {
            throw new ValidationException(esc_html__('Invalid form actions.', 'elemacy'));
        }
        foreach ($selected as $key) {
            if (!is_string($key) || !isset($this->actions[$key])) {
                throw new ValidationException(esc_html__('This form action is unavailable.', 'elemacy'));
            }
        }
        $this->limiter->consume($ip);
        foreach (array_unique($selected) as $key) {
            if (!$this->actions[$key]->execute($settings, $clean)) {
                throw new HttpException(esc_html__('Failed to send email. Please try again.', 'elemacy'), 400);
            }
        }
    }
}
