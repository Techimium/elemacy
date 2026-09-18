<?php

namespace Elemacy\Modules\Forms\Actions;

use Elemacy\Modules\Forms\Contracts\FormActionInterface;

defined('ABSPATH') || exit;

/** Synchronous delivery using the existing authored destinations and formatting. */
final class EmailAction implements FormActionInterface
{
    public function key(): string
    {
        return 'email';
    }

    public function execute(array $settings, array $form_data): bool
    {
        $to_raw = !empty($settings['email_to']) ? $settings['email_to'] : get_option('admin_email');
        $to_candidates = is_array($to_raw) ? $to_raw : preg_split('/[\s,]+/', (string) $to_raw, -1, PREG_SPLIT_NO_EMPTY);
        $to_list = [];
        foreach ($to_candidates as $addr) {
            $e = sanitize_email((string) $addr);
            if ($e && is_email($e)) {
                $to_list[] = $e;
            }
        }
        $to = !empty($to_list) ? implode(',', $to_list) : sanitize_email((string) get_option('admin_email'));

        $subject = !empty($settings['email_subject']) ? $settings['email_subject'] : esc_html__('New Form Submission', 'elemacy');
        $subject = sanitize_text_field(str_replace(["\r", "\n"], '', (string) $subject));

        $content = !empty($settings['email_content']) ? $settings['email_content'] : '[all-fields]';
        $from_name = !empty($settings['email_from_name']) ? $settings['email_from_name'] : get_bloginfo('name');
        // Strip CR/LF (header injection) and angle brackets (which would break the
        // structured "From: Name <email>" header) from the display name.
        $from_name = sanitize_text_field(str_replace(["\r", "\n", '<', '>'], '', wp_strip_all_tags((string) $from_name)));

        $from_email = !empty($settings['email_from']) ? $settings['email_from'] : get_option('admin_email');
        $from_email = sanitize_email((string) $from_email);
        if (!is_email($from_email)) {
            $from_email = sanitize_email((string) get_option('admin_email'));
        }
        $reply_to = '';

        $form_fields = isset($settings['form_fields']) && is_array($settings['form_fields']) ? $settings['form_fields'] : [];

        // Handle [all-fields] and specific field shortcodes
        if (strpos($content, '[all-fields]') !== false) {
            $all_fields_text = '';
            foreach ($form_fields as $index => $field) {
                $val = isset($form_data[$index]) ? $form_data[$index] : '';
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $all_fields_text .= '<strong>' . esc_html($field['label'] ?? '') . ':</strong> ' . esc_html($val) . '<br>';
            }
            $content = str_replace('[all-fields]', $all_fields_text, $content);
        }

        // Handle specific field shortcodes
        foreach ($form_fields as $index => $field) {
            $val = isset($form_data[$index]) ? $form_data[$index] : '';
            if (is_array($val)) {
                $val = implode(', ', $val);
            }

            $field_val = esc_html($val);

            // Replace by Index [field_0]
            $content = str_replace('[field_' . $index . ']', $field_val, $content);

            // Replace by Custom ID [name]
            if (!empty($field['custom_id'])) {
                $content = str_replace('[' . $field['custom_id'] . ']', $field_val, $content);
            }
        }

        // Also handle Reply-To if it's a field ID or email
        if (!empty($settings['email_reply_to'])) {
            // If reply_to is an index of form_fields
            foreach ($form_fields as $index => $field) {
                if (($field['label'] ?? '') === $settings['email_reply_to'] ||
                    (string) $index === (string) $settings['email_reply_to'] ||
                    (!empty($field['custom_id']) && $field['custom_id'] === $settings['email_reply_to'])) {
                    $reply_to = isset($form_data[$index]) && is_string($form_data[$index]) ? sanitize_email($form_data[$index]) : '';
                    break;
                }
            }
            if (empty($reply_to)) {
                $reply_candidate = sanitize_email((string) $settings['email_reply_to']);

                if ($reply_candidate && is_email($reply_candidate)) {
                    $reply_to = $reply_candidate;
                }
            }
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        if (!empty($reply_to) && is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . sanitize_email($reply_to);
        }

        return wp_mail($to, $subject, $content, $headers);
    }
}
