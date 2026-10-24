<?php

namespace Elemacy\Forms\Fields;

defined('ABSPATH') || exit;

/** A phone number. Formats vary by country, so any single line is accepted. */
final class Tel extends AbstractFieldType
{
    public function key(): string
    {
        return 'tel';
    }

    public function label(): string
    {
        return __('Tel', 'elemacy');
    }

    protected function input_type(): string
    {
        return 'tel';
    }
}
