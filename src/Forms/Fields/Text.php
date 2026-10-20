<?php

namespace Elemacy\Forms\Fields;

defined('ABSPATH') || exit;

final class Text extends AbstractFieldType
{
    public function key(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return __('Text', 'elemacy');
    }
}
