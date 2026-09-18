<?php

namespace Elemacy\Loop\Pagination\DTO;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\ValidationException;
use JsonException;
use stdClass;

/**
 * Validated public pagination claims. Query settings are never part of this DTO.
 *
 * Parsing establishes shape and resource limits only. The definition resolver
 * and render policy must still authorize every referenced document and context.
 */
final class PublicLoopRequest
{
    const VERSION = 1;
    const MAX_PAGE = 10000;
    const MAX_CONTEXT_BYTES = 8192;
    const MAX_REQUEST_BYTES = 12288;
    const INVALID_REQUEST = 'elemacy_loop_invalid_request';
    const REFRESH_REQUIRED = 'elemacy_loop_refresh_required';

    /** @var int */
    private int $owner_id;

    /** @var string */
    private string $widget_id;

    /** @var int */
    private int $page;

    /** @var string */
    private string $fingerprint;

    /** @var string */
    private string $context;

    /**
     * Construct only after validation; consumers cannot mutate accepted claims.
     *
     * @param stdClass $claims Validated flat JSON claims.
     */
    private function __construct(stdClass $claims)
    {
        $this->owner_id = $claims->owner_id;
        $this->widget_id = $claims->widget_id;
        $this->page = $claims->page;
        $this->fingerprint = $claims->fingerprint;
        $this->context = $claims->context ?? '';
    }

    /**
     * Validate an unslashed JSON string without coercing scalar shapes.
     *
     * The outer JSON is bounded before decoding. The opaque context is bounded
     * here before any later signature verification or context-payload decoding.
     *
     * @param mixed $raw Untrusted request value, including malformed nonstrings.
     * @return self
     * @throws ValidationException On unsupported, malformed or oversized input.
     */
    public static function from_json($raw): self
    {
        if (null === $raw || '' === $raw) {
            throw self::refresh_required();
        }
        if (!is_string($raw) || strlen($raw) > self::MAX_REQUEST_BYTES) {
            throw self::invalid();
        }

        try {
            $claims = json_decode($raw, false, 4, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $error) {
            throw self::invalid();
        }
        if (!$claims instanceof stdClass) {
            throw self::invalid();
        }
        if (!property_exists($claims, 'version')) {
            throw self::refresh_required();
        }
        if (!is_int($claims->version)) {
            throw self::invalid();
        }
        if (self::VERSION !== $claims->version) {
            throw self::refresh_required();
        }

        $allowed = ['version', 'owner_id', 'widget_id', 'page', 'fingerprint', 'context'];
        if (array_diff(array_keys(get_object_vars($claims)), $allowed)) {
            throw self::invalid();
        }
        if (!isset($claims->owner_id) || !is_int($claims->owner_id) || $claims->owner_id < 1
            || !isset($claims->page) || !is_int($claims->page) || $claims->page < 1 || $claims->page > self::MAX_PAGE) {
            throw self::invalid();
        }
        if (!isset($claims->widget_id) || !is_string($claims->widget_id)
            || !preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $claims->widget_id)) {
            throw self::invalid();
        }
        if (!isset($claims->fingerprint) || !is_string($claims->fingerprint)
            || !preg_match('/\A[a-f0-9]{64}\z/', $claims->fingerprint)) {
            throw self::invalid();
        }
        if (property_exists($claims, 'context')) {
            if (!is_string($claims->context) || strlen($claims->context) > self::MAX_CONTEXT_BYTES) {
                throw self::invalid();
            }
            if ('' !== $claims->context && !preg_match('/\A[A-Za-z0-9_-]+\.[a-f0-9]{64}\z/', $claims->context)) {
                throw self::invalid();
            }
        }

        return new self($claims);
    }

    /** @return int Authoring document ID, still requiring authorization. */
    public function owner_id(): int
    {
        return $this->owner_id;
    }

    /** @return string Exact saved Elementor widget ID. */
    public function widget_id(): string
    {
        return $this->widget_id;
    }

    /** @return int Bounded one-based page number. */
    public function page(): int
    {
        return $this->page;
    }

    /** @return string Claimed definition revision, still requiring comparison. */
    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /** @return string Opaque context, still requiring signature/policy checks. */
    public function context(): string
    {
        return $this->context;
    }

    /** @return ValidationException Stable stale/legacy-markup error envelope. */
    public static function refresh_required(): ValidationException
    {
        return ValidationException::with_errors(
            ['code' => self::REFRESH_REQUIRED],
            esc_html__('This list has changed. Refresh the page to continue.', 'elemacy')
        );
    }

    /** @return ValidationException Client-safe malformed-request error. */
    private static function invalid(): ValidationException
    {
        return ValidationException::with_errors(
            ['code' => self::INVALID_REQUEST],
            esc_html__('Invalid loop pagination request.', 'elemacy')
        );
    }
}
