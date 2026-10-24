<?php

namespace Elemacy\Forms\Submission;

defined('ABSPATH') || exit;

/** What a field type made of one submitted value. */
final class FieldResult
{
    private const VALID   = 'valid';
    private const INVALID = 'invalid';
    private const NONE    = 'none';

    private string $state;

    /** @var string|string[] */
    private $value;
    private string $message;

    /**
     * @param string          $state   One of the state constants.
     * @param string|string[] $value   The sanitized value.
     * @param string          $message The invalid reason.
     */
    private function __construct(string $state, $value = '', string $message = '')
    {
        $this->state = $state;
        $this->value = $value;
        $this->message = $message;
    }

    /**
     * The sanitized value actions receive.
     *
     * @param string|string[] $value
     */
    public static function valid($value): self
    {
        return new self(self::VALID, $value);
    }

    /** A visitor-facing, plain-text reason shown next to the field. */
    public static function invalid(string $message): self
    {
        return new self(self::INVALID, '', $message);
    }

    /** The field submits nothing (HTML content). */
    public static function none(): self
    {
        return new self(self::NONE);
    }

    public function is_valid(): bool
    {
        return self::INVALID !== $this->state;
    }

    public function has_value(): bool
    {
        return self::VALID === $this->state;
    }

    /** @return string|string[] */
    public function value()
    {
        return $this->value;
    }

    public function message(): string
    {
        return $this->message;
    }
}
