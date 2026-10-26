<?php

namespace Elemacy\Forms\Actions;

use Elemacy\Forms\Contracts\FormActionInterface;
use Elemacy\Forms\Services\EmailTemplate;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\ActionResult;
use Elemacy\Forms\Submission\Submission;

defined('ABSPATH') || exit;

/**
 * Sends the submission as an HTML email to the authored recipients. Every
 * header value is sanitized and stripped of line breaks, and Reply-To only
 * takes a field value that is a clean email address.
 */
final class EmailAction implements FormActionInterface
{
    private EmailTemplate $template;

    public function __construct(EmailTemplate $template)
    {
        $this->template = $template;
    }

    public function key(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return __('Email', 'elemacy');
    }

    public function settings(): array
    {
        $admin_email = (string) get_option('admin_email');
        $site_name = (string) get_bloginfo('name');

        return [
            Setting::text('email_to', __('To', 'elemacy'))->with_default($admin_email)->with_placeholder($admin_email)
                ->with_description(__('Separate emails with commas', 'elemacy')),
            /* translators: %s: site name. */
            Setting::text('email_subject', __('Subject', 'elemacy'))->with_default(sprintf(__('New message from %s', 'elemacy'), $site_name)),
            Setting::textarea('email_content', __('Message', 'elemacy'))->with_default(EmailTemplate::ALL_FIELDS)
                ->with_description(__('Use [all-fields] for every field, or [field id="name"] for one field by its ID.', 'elemacy')),
            Setting::text('email_from_name', __('From Name', 'elemacy'))->with_default($site_name),
            Setting::text('email_from', __('From Email', 'elemacy'))->with_default($admin_email),
            Setting::text('email_reply_to', __('Reply-To', 'elemacy'))->with_placeholder('email')
                ->with_description(__('A field ID (such as email) or an email address.', 'elemacy')),
        ];
    }

    public function run(Submission $submission): ActionResult
    {
        $form = $submission->form();
        $admin_email = sanitize_email((string) get_option('admin_email'));

        $to = [];

        foreach (preg_split('/[\s,]+/', self::setting($form->setting('email_to'), $admin_email), -1, PREG_SPLIT_NO_EMPTY) as $address) {
            $address = sanitize_email($address);

            if ($address && is_email($address)) {
                $to[] = $address;
            }
        }

        $subject = sanitize_text_field(str_replace(["\r", "\n"], '', self::setting($form->setting('email_subject'), __('New Form Submission', 'elemacy'))));
        // Angle brackets would break the structured "From: Name <email>" header.
        $from_name = sanitize_text_field(str_replace(["\r", "\n", '<', '>'], '', wp_strip_all_tags(self::setting($form->setting('email_from_name'), (string) get_bloginfo('name')))));
        $from_email = sanitize_email(self::setting($form->setting('email_from'), $admin_email));

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . (is_email($from_email) ? $from_email : $admin_email) . '>',
        ];

        $reply_to = $this->reply_to($submission, self::setting($form->setting('email_reply_to'), ''));

        if ('' !== $reply_to) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        $body = $this->template->render(self::setting($form->setting('email_content'), EmailTemplate::ALL_FIELDS), $submission);

        return wp_mail($to ? implode(',', $to) : $admin_email, $subject, $body, $headers)
            ? ActionResult::success()
            : ActionResult::failure(__('Your message could not be sent. Please try again.', 'elemacy'));
    }

    /** A field's value when the setting names a field, else the setting itself, if a clean address. */
    private function reply_to(Submission $submission, string $setting): string
    {
        $setting = trim($setting);

        if ('' === $setting) {
            return '';
        }

        $candidate = null !== $submission->field($setting) ? $submission->value($setting, '') : $setting;

        return is_string($candidate) && '' !== $candidate && is_email($candidate) && sanitize_email($candidate) === $candidate ? $candidate : '';
    }

    /**
     * A saved text setting, or the fallback when it is empty or not text.
     *
     * @param mixed $value
     */
    private static function setting($value, string $fallback): string
    {
        return is_string($value) && '' !== trim($value) ? $value : $fallback;
    }
}
