<?php

namespace Elemacy\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Request facts an action may rely on. The user is set only when the request
 * proved it with a nonce, so actions use user_id() rather than the
 * current user. The visitor's IP is deliberately absent.
 */
final class SubmissionMeta
{
    private string $page_url;
    private int $user_id;
    private int $submitted_at;

    public function __construct(string $page_url, int $user_id, int $submitted_at)
    {
        $this->page_url = $page_url;
        $this->user_id = $user_id;
        $this->submitted_at = $submitted_at;
    }

    /** The same-site page the form was sent from, or '' when unknown. */
    public function page_url(): string
    {
        return $this->page_url;
    }

    /** The verified user, or 0 for an anonymous submission. */
    public function user_id(): int
    {
        return $this->user_id;
    }

    public function submitted_at(): int
    {
        return $this->submitted_at;
    }
}
