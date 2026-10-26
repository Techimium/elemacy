<?php

namespace Elemacy\Core\Capabilities;

defined('ABSPATH') || exit;

/**
 * Whether a registered capability (condition, rule, trigger, popup type, loop
 * source, ...) can execute at all, kept separate from what it decides. See
 * docs/ARCHITECTURE.md § Shared domains.
 */
final class FeatureAvailability
{
    public const UNKNOWN_IDENTIFIER = 'unknown_identifier';
    public const EXTENSION_INACTIVE = 'extension_inactive';
    public const CLIENT_HANDLER_MISSING = 'client_handler_missing';

    private bool $available;
    private string $reason_code;
    private string $message;

    private function __construct(bool $available, string $reason_code, string $message)
    {
        $this->available   = $available;
        $this->reason_code = $reason_code;
        $this->message     = $message;
    }

    public static function available(): self
    {
        return new self(true, '', '');
    }

    /**
     * The stored identifier is no longer registered by anything: no real
     * implementation and no mock stand-in (e.g. after an extension that
     * used to provide it is removed entirely).
     */
    public static function unknown_identifier(string $identifier): self
    {
        return new self(
            false,
            self::UNKNOWN_IDENTIFIER,
            sprintf(
                /* translators: %s: the unrecognized identifier saved in the configuration. */
                __('"%s" is no longer available. Its saved settings are preserved but it will not run.', 'elemacy'),
                $identifier
            )
        );
    }

    /**
     * The identifier resolves to a registered non-functional stand-in
     * (a Mock condition/rule/trigger) because the extension that provides
     * the real implementation is not currently active.
     */
    public static function inactive_extension(string $identifier): self
    {
        return new self(
            false,
            self::EXTENSION_INACTIVE,
            sprintf(
                /* translators: %s: the identifier that requires an inactive extension. */
                __('"%s" requires an extension that is not currently active. Its saved settings are preserved but it will not run.', 'elemacy'),
                $identifier
            )
        );
    }

    /**
     * The server-side definition is available, but the loaded browser
     * runtime has no handler for it, so it cannot be evaluated client-side.
     */
    public static function client_handler_missing(string $identifier): self
    {
        return new self(
            false,
            self::CLIENT_HANDLER_MISSING,
            sprintf(
                /* translators: %s: the identifier missing a client-side handler. */
                __('"%s" cannot run in the current browser runtime and will not display automatically.', 'elemacy'),
                $identifier
            )
        );
    }

    public function is_available(): bool
    {
        return $this->available;
    }

    public function is_unavailable(): bool
    {
        return !$this->available;
    }

    public function get_reason_code(): string
    {
        return $this->reason_code;
    }

    public function get_message(): string
    {
        return $this->message;
    }

    /**
     * @return array{available: bool, reason_code: string, message: string}
     */
    public function to_array(): array
    {
        return [
            'available'   => $this->available,
            'reason_code' => $this->reason_code,
            'message'     => $this->message,
        ];
    }
}
