<?php

namespace Elemacy\Forms\Http;

use Elemacy\Core\Hooks;
use Elemacy\Forms\Registry;
use Elemacy\Forms\Services\SubmissionService;
use Elemacy\Forms\Submission\SubmissionMeta;
use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Http\SiteRequest as Request;
use Elemacy\Http\SiteResponse;

defined('ABSPATH') || exit;

/**
 * The public submission endpoint (admin-ajax action elemacy_form_submit).
 * Transport only: request shape, visitor identity and the honeypot; the
 * form's rules belong to SubmissionService.
 *
 * There is deliberately no public nonce, so pages cached for any time keep
 * working. A logged-in visitor's identity is kept only when the request
 * carries a valid nonce for that user; otherwise the submission runs as a
 * guest, so a cross-site post can never act as, or reach private posts as,
 * the visitor.
 */
final class SubmissionController
{
    public const ACTION       = 'elemacy_form_submit';
    public const NONCE_ACTION = 'elemacy_form_submit';

    public const ACTION_FIELD   = 'action';
    public const SOURCE_FIELD   = 'form_source';
    public const OWNER_FIELD    = 'form_owner';
    public const FORM_FIELD     = 'form_id';
    public const VALUES_FIELD   = 'form_fields';
    public const HONEYPOT_FIELD = 'elemacy_honeypot';
    public const NONCE_FIELD    = '_wpnonce';

    private SubmissionService $submissions;

    public function __construct(SubmissionService $submissions)
    {
        $this->submissions = $submissions;
    }

    public function index(Request $request)
    {
        // Raw shapes until the engine validates them: a text sanitizer could hide header injection.
        $raw = static function ($value) {
            return $value;
        };
        $source = $request->input(self::SOURCE_FIELD, $raw);
        $owner = $request->input(self::OWNER_FIELD, $raw);
        $form = $request->input(self::FORM_FIELD, $raw);
        $honeypot = $request->input(self::HONEYPOT_FIELD, $raw, '');
        $nonce = $request->input(self::NONCE_FIELD, $raw, '');

        if (
            !is_string($source) || 1 !== preg_match(Registry::KEY_PATTERN, $source)
            || !is_string($owner) || 1 !== preg_match('/\A[1-9][0-9]{0,18}\z/', $owner) || (string) (int) $owner !== $owner
            || !is_string($form) || '' === $form || strlen($form) > 100
            || !is_string($honeypot) || !is_string($nonce)
        ) {
            throw new ValidationException(__('Missing or invalid form parameters.', 'elemacy'), 400); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text JSON; the form runtime inserts it with textContent.
        }

        if (is_user_logged_in() && ('' === $nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION))) {
            wp_set_current_user(0);
        }

        // A filled honeypot is a bot: answer as usual, do nothing.
        if ('' !== $honeypot) {
            return SiteResponse::instance()->json(['message' => __('Form submitted successfully!', 'elemacy')]);
        }

        $meta = new SubmissionMeta((string) wp_get_referer(), get_current_user_id(), time());
        $ip = (string) apply_filters(Hooks::FORM_CLIENT_IP_FILTER, sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? ''))));

        return SiteResponse::instance()->json(
            $this->submissions->submit($source, (int) $owner, $form, $request->input(self::VALUES_FIELD, $raw, []), $meta, $ip)
        );
    }
}
