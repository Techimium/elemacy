<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/** A web address; only http and https are accepted. */
final class Url extends AbstractFieldType
{
    public function key(): string
    {
        return 'url';
    }

    public function label(): string
    {
        return __('URL', 'elemacy');
    }

    protected function input_type(): string
    {
        return 'url';
    }

    protected function check(Field $field, string $value): FieldResult
    {
        $value = trim($value);
        $scheme = strtolower((string) wp_parse_url($value, PHP_URL_SCHEME));

        if (false === filter_var($value, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            return FieldResult::invalid(__('Enter a valid URL starting with http:// or https://.', 'elemacy'));
        }

        return FieldResult::valid(esc_url_raw($value, ['http', 'https']));
    }
}
