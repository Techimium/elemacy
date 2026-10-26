<?php

namespace Elemacy\Forms\Services;

use Elemacy\Core\Hooks;
use Elemacy\Forms\FormActionRegistry;
use Elemacy\Forms\FormSourceRegistry;
use Elemacy\Forms\FormUnavailableException;
use Elemacy\Forms\Submission\ActionResult;
use Elemacy\Forms\Submission\Submission;
use Elemacy\Forms\Submission\SubmissionMeta;
use Elemacy\Http\Exceptions\HttpException;

defined('ABSPATH') || exit;

/**
 * One submission, start to finish: find the saved form through its builder's
 * source, check the owner, validate the values, let extensions add errors,
 * apply the rate limit, then run the saved actions in order. The first
 * failing action stops the rest and its own message reaches the visitor.
 */
final class SubmissionService
{
    private FormSourceRegistry $sources;
    private OwnerAccessPolicy $owners;
    private SubmissionValidator $validator;
    private FormActionRegistry $actions;
    private SubmissionRateLimiter $limiter;

    public function __construct(FormSourceRegistry $sources, OwnerAccessPolicy $owners, SubmissionValidator $validator, FormActionRegistry $actions, SubmissionRateLimiter $limiter)
    {
        $this->sources = $sources;
        $this->owners = $owners;
        $this->validator = $validator;
        $this->actions = $actions;
        $this->limiter = $limiter;
    }

    /**
     * @param string         $source  The form source key the request named.
     * @param int            $owner   The post storing the form.
     * @param string         $form_id The form's ID within its owner.
     * @param mixed          $values  The submitted form_fields.
     * @param SubmissionMeta $meta    Request facts for actions.
     * @param string         $ip      The client IP, used only for rate limiting.
     * @return array The success response: message, plus data keyed by action key.
     */
    public function submit(string $source, int $owner, string $form_id, $values, SubmissionMeta $meta, string $ip): array
    {
        $resolver = $this->sources->get($source);

        if (!$resolver) {
            throw FormUnavailableException::create();
        }

        $this->owners->assert_accessible($owner);
        $form = $resolver->resolve($owner, $form_id);
        $submission = new Submission($form, $this->validator->validate($form, $values), $meta);

        $errors = [];

        foreach ((array) apply_filters(Hooks::FORM_VALIDATION_FILTER, [], $submission) as $id => $message) {
            if (is_string($message) && '' !== $message) {
                $errors[(string) $id] = $message;
            }
        }

        if ($errors) {
            throw SubmissionValidator::failure($errors); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text JSON; the form runtime inserts it with textContent.
        }

        $actions = [];

        foreach ($form->actions() as $key) {
            $action = $this->actions->get((string) $key);

            if (!$action) {
                throw new HttpException(__('This form action is unavailable.', 'elemacy'), 400); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text JSON; the form runtime inserts it with textContent.
            }

            $actions[$action->key()] = $action;
        }

        $this->limiter->consume($ip);

        /** @var array<string, ActionResult> $results */
        $results = [];
        $data = [];

        foreach ($actions as $key => $action) {
            $result = $action->run($submission);
            $results[$key] = $result;

            do_action(Hooks::FORM_ACTION_RUN_ACTION, $key, $result, $submission);

            if (!$result->is_success()) {
                $message = '' !== $result->message() ? $result->message() : __('The form could not be submitted. Please try again.', 'elemacy');

                throw new HttpException($message, 400); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text JSON; the form runtime inserts it with textContent.
            }

            if ($result->data()) {
                $data[$key] = $result->data();
            }
        }

        do_action(Hooks::FORM_SUBMITTED_ACTION, $submission, $results);

        $response = ['message' => __('Form submitted successfully!', 'elemacy')];

        if ($data) {
            $response['data'] = $data;
        }

        return $response;
    }
}
