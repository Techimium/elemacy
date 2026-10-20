<?php

namespace Elemacy\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * What a submit action reports. A failure's message is shown to the visitor
 * and stops the remaining actions. Data is returned to the browser under the
 * action's key, so keep it public-safe.
 */
final class ActionResult
{
    private bool $success;
    private string $message;
    private array $data;

    private function __construct(bool $success, string $message, array $data)
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
    }

    public static function success(array $data = []): self
    {
        return new self(true, '', $data);
    }

    /**
     * @param string $message Visitor-facing plain text.
     * @param array  $data    Public-safe data for the browser.
     */
    public static function failure(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }

    public function is_success(): bool
    {
        return $this->success;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function data(): array
    {
        return $this->data;
    }
}
