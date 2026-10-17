<?php

namespace Elemacy\Modules\Widgets\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Http\SiteRequest as Request;
use Elemacy\Http\SiteResponse;
use Elemacy\Modules\Widgets\Forms\Services\SubmissionService;

/** Transport coordination only; saved form policy, validation and actions belong to Forms. */
class AjaxFormController
{
    private SubmissionService $submissions;

    public function __construct(SubmissionService $submissions)
    {
        $this->submissions = $submissions;
    }

    public function index(Request $request)
    {
        // Preserve raw shapes until application validation; text sanitizers must not hide header injection.
        $raw = static function ($value) {
            return $value;
        };
        $owner = $request->input('post_id', $raw);
        $widget = $request->input('widget_id', $raw);
        $context = $request->input('elemacy_form_context', $raw, '');
        $honeypot = $request->input('elemacy_honeypot', $raw, '');
        if (
            (!is_int($owner) && !is_string($owner)) || !preg_match('/\A[1-9][0-9]*\z/', (string) $owner)
            || (string) (int) $owner !== (string) $owner || !is_string($widget) || !is_string($context) || !is_string($honeypot)
        ) {
            throw new ValidationException(esc_html__('Missing or invalid form parameters.', 'elemacy'));
        }
        foreach (['email_to', 'email_from', 'email_subject', 'email_reply_to', 'email_content', 'submit_actions', 'settings'] as $key) {
            if ($request->has($key)) {
                throw new ValidationException(esc_html__('Form settings cannot be submitted by the browser.', 'elemacy'));
            }
        }
        if ($honeypot === '') {
            $this->submissions->submit(
                (int) $owner,
                $widget,
                $context,
                $request->input('form_field', $raw, []),
                sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')))
            );
        }
        return SiteResponse::instance()->json(['message' => esc_html__('Form submitted successfully!', 'elemacy')]);
    }
}
