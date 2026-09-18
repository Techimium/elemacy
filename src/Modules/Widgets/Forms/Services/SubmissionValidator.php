<?php

namespace Elemacy\Modules\Widgets\Forms\Services;

use Elemacy\Http\Exceptions\ValidationException;

defined('ABSPATH') || exit;

/** Validate before sanitizing so malformed shapes and injected headers cannot disappear. */
final class SubmissionValidator
{
    public function validate(array $settings, $values): array
    {
        if (!is_array($values) || count($values) > 200 || strlen((string) wp_json_encode($values)) > 65536) {
            throw ValidationException::with_errors(['form_field' => esc_html__('Invalid form fields.', 'elemacy')]);
        }
        $fields = $settings['form_fields'] ?? [];
        $errors = [];
        $clean = [];
        foreach ($values as $id => $value) {
            if (!array_key_exists($id, $fields)) {
                $errors[(string) $id] = esc_html__('Unknown field.', 'elemacy');
            }
        }
        foreach ($fields as $id => $field) {
            $type = $field['type'] ?? 'text';
            $value = $values[$id] ?? ($type === 'checkbox' ? [] : '');
            $multiple = $type === 'checkbox';
            if (($multiple && (!is_array($value) || array_values($value) !== $value)) || (!$multiple && !is_string($value))) {
                $errors[(string) $id] = esc_html__('Invalid field value.', 'elemacy');
                continue;
            }
            $items = $multiple ? $value : [$value];
            foreach ($items as $item) {
                if (!is_string($item) || strlen($item) > 16384) {
                    $errors[(string) $id] = esc_html__('Invalid field value.', 'elemacy');
                    break;
                }
                // Textarea line breaks remain legitimate message content. Header-bound values never accept them.
                $reply = (string) ($settings['email_reply_to'] ?? '');
                $header_field = $reply !== '' && in_array($reply, [(string) $id, (string) ($field['custom_id'] ?? ''), (string) ($field['label'] ?? '')], true);
                if (strpos($item, "\0") !== false || (($type !== 'textarea' || $header_field) && strpbrk($item, "\r\n") !== false)) {
                    $errors[(string) $id] = esc_html__('Invalid field value.', 'elemacy');
                }
                if ($type === 'email' && $item !== '' && !is_email($item)) {
                    $errors[(string) $id] = esc_html__('Please enter a valid email address.', 'elemacy');
                }
                if (in_array($type, ['checkbox', 'radio', 'select'], true) && ($item !== '' || !empty($field['required']))) {
                    $choices = array_map(static function ($option): string {
                        $parts = explode('|', $option);
                        return trim($parts[1] ?? $parts[0]);
                    }, explode("\n", (string) ($field['options'] ?? '')));
                    if (!in_array($item, $choices, true)) {
                        $errors[(string) $id] = esc_html__('Choose a configured option.', 'elemacy');
                    }
                }
            }
            if (!empty($field['required']) && (!$items || !array_filter($items, static function ($item): bool { return is_string($item) && trim($item) !== ''; }))) {
                $errors[(string) $id] = esc_html__('This field is required.', 'elemacy');
            }
            if (!isset($errors[(string) $id])) {
                $clean[$id] = $multiple ? array_map('sanitize_text_field', $items)
                    : ($type === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value));
            }
        }
        if ($errors) {
            throw ValidationException::with_errors($errors, esc_html__('Please correct the highlighted fields.', 'elemacy')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Every error above is escaped.
        }
        return $clean;
    }
}
