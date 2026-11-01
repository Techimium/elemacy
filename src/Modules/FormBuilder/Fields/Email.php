<?php

namespace Elemacy\Modules\FormBuilder\Fields;

use Elemacy\Modules\FormBuilder\Definition\Field;
use Elemacy\Modules\FormBuilder\Submission\FieldResult;

defined('ABSPATH') || exit;

/**
 * @internal
 */
final class Email extends AbstractFieldType
{
    public function key(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return __('Email', 'elemacy');
    }

    protected function input_type(): string
    {
        return 'email';
    }

    protected function check(Field $field, string $value): FieldResult
    {
        $value = trim($value);

        return is_email($value)
            ? FieldResult::valid(sanitize_email($value))
            : FieldResult::invalid(__('Please enter a valid email address.', 'elemacy'));
    }
}
